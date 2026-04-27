<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('items')->get();
        return response()->json($orders);
    }

    public function show($id)
    {
        $order = Order::with('items')->find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Ambil data user dari user-service
        $userResponse = Http::get(env('USER_SERVICE_URL') . '/api/users/' . $order->user_id);
        $user = $userResponse->successful() ? $userResponse->json() : null;

        // Ambil data produk tiap item dari product-service
        $items = $order->items->map(function ($item) {
            $productResponse = Http::get(env('PRODUCT_SERVICE_URL') . '/api/products/' . $item->product_id);
            $product = $productResponse->successful() ? $productResponse->json() : null;
            return array_merge($item->toArray(), ['product' => $product]);
        });

        return response()->json([
            'order' => $order,
            'user'  => $user,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'            => 'required|integer',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        // Validasi user ke user-service
        $userResponse = Http::get(env('USER_SERVICE_URL') . '/api/users/' . $request->user_id);
        if (!$userResponse->successful()) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $totalPrice = 0;
        $itemsData  = [];

        // Validasi produk & hitung total ke product-service
        foreach ($request->items as $item) {
            $productResponse = Http::get(env('PRODUCT_SERVICE_URL') . '/api/products/' . $item['product_id']);
            if (!$productResponse->successful()) {
                return response()->json(['message' => 'Produk ID ' . $item['product_id'] . ' tidak ditemukan'], 404);
            }

            $product = $productResponse->json();

            if ($product['stock'] < $item['quantity']) {
                return response()->json(['message' => 'Stok produk ' . $product['name'] . ' tidak cukup'], 400);
            }

            $subtotal    = $product['price'] * $item['quantity'];
            $totalPrice += $subtotal;

            $itemsData[] = [
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $product['price'],
            ];
        }

        // Buat order
        $order = Order::create([
            'user_id'     => $request->user_id,
            'total_price' => $totalPrice,
            'status'      => 'pending',
        ]);

        // Simpan order items & kurangi stok
        foreach ($itemsData as $itemData) {
            OrderItem::create(array_merge($itemData, ['order_id' => $order->id]));

            Http::post(
                env('PRODUCT_SERVICE_URL') . '/api/products/' . $itemData['product_id'] . '/reduce-stock',
                ['quantity' => $itemData['quantity']]
            );
        }

        return response()->json($order->load('items'), 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $request->validate(['status' => 'required|in:pending,processing,completed,cancelled']);
        $order->update(['status' => $request->status]);

        return response()->json($order);
    }
}
