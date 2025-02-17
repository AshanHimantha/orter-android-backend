<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

       public function destroy(Order $order)
    {
        if ($order->payment_status === 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'Paid orders cannot be deleted'
            ], 403);
        }
    
        $order->delete();
    
        return response()->json([
            'status' => true,
            'message' => 'Order deleted successfully'
        ]);
    }


    public function updatePaymentStatus(Request $request)
    {
        try {
            // Log the entire request for debugging
            Log::info('PayHere Callback Data:', $request->all());

            // Validate the request with order_id matching order_number
            $request->validate([
                'order_id' => 'required|exists:orders,order_number',
                'merchant_id' => 'required',
                'payhere_amount' => 'required|numeric',
                'payhere_currency' => 'required|string',
                'status_code' => 'required|integer',
                'md5sig' => 'required|string',
                'payment_id' => 'required|string'
            ]);

            // Find order by order_number instead of id
            $order = Order::where('order_number', $request->order_id)->first();

            if (!$order) {
                Log::error('Order not found:', ['order_number' => $request->order_id]);
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Verify MD5 signature
            $merchant_secret = env('PAYHERE_MERCHANT_SECRET');
            $md5sig = strtoupper(md5(
                $request->merchant_id .
                $request->order_id .
                $request->payhere_amount .
                $request->payhere_currency .
                $request->status_code .
                strtoupper(md5($merchant_secret))
            ));

            if ($md5sig != $request->md5sig) {
                Log::error('MD5 Signature Mismatch! Possible fraud.');
                return response()->json([
                    'status' => false,
                    'message' => 'MD5 signature mismatch'
                ], 400);
            }

            if ($request->status_code == 2) { // Payment Success
                // Update order status
                $order->update([
                    'transaction_id' => $request->payment_id,
                    'payment_status' => 'paid',
                    'status' => 'confirmed'
                ]);

                // Clear cart items and log the operation
                $deletedCount = Cart::where('firebase_uid', $order->firebase_uid)->delete();
                Log::info('Cart items deleted:', [
                    'order_number' => $order->order_number,
                    'firebase_uid' => $order->firebase_uid,
                    'items_deleted' => $deletedCount
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment completed successfully'
                ]);
            
            } else { // Failed or Cancelled
                // Restore stock quantities before deleting the order
                foreach ($order->items as $item) {
                    $stock = $item->stock;
                    $sizeColumn = strtolower($item->size) . '_quantity';
                    $stock->$sizeColumn += $item->quantity;
                    $stock->save();
                }

                // Delete order items and order
                $order->items()->delete();
                $order->delete();

                Log::info('Order deleted due to failed payment:', [
                    'order_number' => $order->order_number,
                    'payment_status_code' => $request->status_code
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Payment failed, order removed'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Payment processing error:', [
                'message' => $e->getMessage(),
                'order_id' => $request->order_id ?? null
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}