<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $firebaseUid = $request->firebase_uid;
        
        $orders = Order::where('firebase_uid', $firebaseUid)
            ->with(['items', 'branch'])
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'delivery_type' => 'required|in:delivery,pickup',
            'branch_id' => 'required_if:delivery_type,pickup|exists:branches,id',
            'delivery_name' => 'required',
            'delivery_phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
            'delivery_address' => 'required_if:delivery_type,delivery',
            'delivery_city' => 'required_if:delivery_type,delivery',
            'payment_method' => 'required|in:cash,card'
        ]);

        $firebaseUid = $request->firebase_uid;
        
        $cartItems = Cart::where('firebase_uid', $firebaseUid)
            ->with(['stock.product'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // Generate pickup ID if needed
        $pickupId = null;
        if ($request->delivery_type === 'pickup') {
            $pickupId = 'PU-' . strtoupper(Str::random(6));
        }

        // Calculate total weight and shipping
        $totalWeight = $cartItems->sum(function($item) {
            return $item->quantity * $item->stock->product->weight;
        });

        $weightInKg = $totalWeight / 1000;
        
        // Calculate shipping with base charge 400 and 100 per extra kg
        $courier = \App\Models\curriers::where('is_active', true)->first();
        $shippingFee = 400; // Base charge

        if ($weightInKg > 1) {
            $extraKgs = ceil($weightInKg - 1);
            $shippingFee += ($extraKgs * 100); // 100 per extra kg
        }

        // Set shipping fee to 0 if pickup
        if ($request->delivery_type === 'pickup') {
            $shippingFee = 0;
        }

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'pickup_id' => $pickupId,
            'firebase_uid' => $firebaseUid,
            'delivery_type' => $request->delivery_type,
            'branch_id' => $request->branch_id,
            'delivery_name' => $request->delivery_name,
            'delivery_phone' => $request->delivery_phone,
            'delivery_address' => $request->delivery_address,
            'delivery_city' => $request->delivery_city,
            'payment_method' => $request->payment_method,
            'payment_status' => 'pending',
            'status' => 'pending'
        ]);

        $subTotal = 0;
        foreach ($cartItems as $item) {
            $itemTotal = $item->quantity * $item->stock->product->price;
            $subTotal += $itemTotal;
            
            OrderItem::create([
                'order_id' => $order->id,
                'stock_id' => $item->stock_id,
                'product_name' => $item->stock->product->name,
                'product_image' => $item->stock->product->main_image,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'selling_price' => $item->stock->product->price,
                'cost_price' => $item->stock->product->cost_price ?? 0,
                'total' => $itemTotal
            ]);

            // Decrease stock quantity
            $stock = $item->stock;
            $sizeColumn = strtolower($item->size) . '_quantity';
            $stock->$sizeColumn -= $item->quantity;
            $stock->save();
        }

        // Clear cart after order creation
        // Cart::where('firebase_uid', $firebaseUid)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order created successfully',
            'data' => [
                'order' => $order->load(['items', 'branch']),
                'summary' => [
                    'subtotal' => (int)$subTotal,
                    'shipping_fee' => (int)$shippingFee,
                    'total' => (int)($subTotal + $shippingFee)
                ]
            ]
        ], 201);
    }

    public function show(Order $order)
    {
        return response()->json([
            'status' => true,
            'data' => $order->load(['items', 'branch'])
        ]);
    }

    public function updateStatus(Order $order, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,ready_for_pickup,picked_up,completed,cancelled'
        ]);

        $order->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->fresh()
        ]);
    }

    public function updatePaymentStatus(Request $request)
{
    $request->validate([
        'order_id' => 'required|exists:orders,id',
        'transaction_id' => 'nullable|string',
        'payment_status' => 'required|in:paid,failed'
    ]);

    $order = Order::where('id', $request->order_id)
        ->where('firebase_uid', $request->firebase_uid)
        ->first();

    if (!$order) {
        return response()->json([
            'status' => false,
            'message' => 'Order not found'
        ], 404);
    }

    if ($request->payment_status === 'paid') {
        $order->update([
            'transaction_id' => $request->transaction_id,
            'payment_status' => 'paid'
        ]);
        
        // Clear cart items after successful payment
        Cart::where('firebase_uid', $request->firebase_uid)->delete();
        
    } else {
        // Delete order items first due to foreign key constraint
        OrderItem::where('order_id', $order->id)->delete();
        
        // Then delete the order
        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Payment failed, order cancelled'
        ]);
    }

    return response()->json([
        'status' => true,
        'message' => 'Payment completed successfully'
    ]);
}
}