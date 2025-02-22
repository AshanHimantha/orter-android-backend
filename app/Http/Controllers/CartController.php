<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Stock;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $carts = Cart::where('firebase_uid', $request->firebase_uid)
            ->with(['stock.product', 'user'])
            ->get();

        return response()->json(['status' => true, 'data' => $carts]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'firebase_uid' => 'required|string',
            'stock_id' => 'required|exists:stocks,id',
            'size' => 'required|in:XS,S,M,L,XL,XXL',
            'quantity' => 'required|integer|min:1'
        ]);

        // Check if item already exists in cart
        $existingCart = Cart::where([
            'firebase_uid' => $request->firebase_uid,
            'stock_id' => $request->stock_id,
            'size' => $request->size
        ])->first();

        // Check stock availability
        $stock = Stock::findOrFail($request->stock_id);
        $sizeColumn = strtolower($request->size) . '_quantity';
        
        $totalRequestedQuantity = $request->quantity;
        if ($existingCart) {
            $totalRequestedQuantity += $existingCart->quantity;
        }

        if ($stock->$sizeColumn < $totalRequestedQuantity) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient stock quantity'
            ], 400);
        }

        if ($existingCart) {
            $existingCart->update([
                'quantity' => $totalRequestedQuantity
            ]);
            $cart = $existingCart;
        } else {
            $cart = Cart::create($request->all());
        }

        return response()->json([
            'status' => true,
            'data' => $cart->load('stock.product')
        ], 201);
    }

    public function update(Request $request, Cart $cart)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $stock = $cart->stock;
        $sizeColumn = strtolower($cart->size) . '_quantity';

        if ($stock->$sizeColumn < $request->quantity) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient stock quantity'
            ], 400);
        }

        $cart->update($request->only('quantity'));

        return response()->json([
            'status' => true,
            'data' => $cart->load('stock.product')
        ]);
    }

    public function destroy(Cart $cart)
    {
        $firebaseUid = $cart->firebase_uid;
        $cart->delete();

        // Get updated cart summary after deletion
        $userCarts = Cart::where('firebase_uid', $firebaseUid)
            ->with(['stock.product'])
            ->get();

        $totalItems = $userCarts->sum('quantity');
        $subTotal = $userCarts->sum(function($item) {
            return $item->quantity * (int)$item->stock->product->price;
        });

        $totalWeight = $userCarts->sum(function($item) {
            return $item->quantity * $item->stock->product->weight;
        });

        $weightInKg = $totalWeight / 1000;
        
        // Calculate shipping
        $courier = \App\Models\curriers::where('is_active', true)->first();
        $shippingFee = $courier->charge;

        if ($weightInKg > 1) {
            $extraKgs = ceil($weightInKg - 1);
            $shippingFee += ($extraKgs * $courier->extra_per_kg);
        }

        return response()->json([
            'status' => true,
            'message' => 'Item removed from cart',
            'summary' => [
                'item_count' => $totalItems,
                'subtotal' => (int)$subTotal,
                'shipping_fee' => (int)$shippingFee,
                'total' => (int)($subTotal + $shippingFee)
            ]
        ]);
    }

    public function getUserCart(Request $request)
    {
        $firebaseUid = $request->firebase_uid;
    
        if (!$firebaseUid) {
            return response()->json([
                'status' => false,
                'message' => 'Firebase UID not found (Middleware issue)'
            ], 401);
        }
    
        $carts = Cart::where('firebase_uid', $firebaseUid)
            ->with(['stock.product.category'])
            ->get()
            ->map(function ($cart) {
                return [
                    'id' => $cart->id,
                    'size' => $cart->size,
                    'quantity' => $cart->quantity,
                    'product' => [
                        'id' => $cart->stock->product->id,
                        'name' => $cart->stock->product->name,
                        'price' => (int)$cart->stock->product->price,
                        'main_image' => $cart->stock->product->main_image_url,
                        'color' => $cart->stock->product->color,
                        'weight' => $cart->stock->product->weight,
                        'category' => [
                            'id' => $cart->stock->product->category->id,
                            'name' => $cart->stock->product->category->name
                        ]
                    ],
                    'stock' => [
                        'id' => $cart->stock->id,
                        $cart->size => $cart->stock->{strtolower($cart->size).'_quantity'}
                    ]
                ];
            });
    
        $totalItems = $carts->sum('quantity');
        $totalAmount = $carts->sum(function($cart) {
            return $cart['quantity'] * $cart['product']['price'];
        });
    
        // Calculate total weight in grams
        $totalWeight = $carts->sum(function($cart) {
            return $cart['quantity'] * $cart['product']['weight'];
        });
    
        // Convert to kg
        $weightInKg = $totalWeight / 1000;
    
        // Get courier charges
        $courier = \App\Models\curriers::where('is_active', true)->first();
        $courierCharge = $courier->charge; // Base charge for up to 1kg
    
        // Add extra charge if weight is more than 1kg
        if ($weightInKg > 1) {
            $extraKgs = ceil($weightInKg - 1);
            $courierCharge += ($extraKgs * $courier->extra_per_kg);
        }
    
        return response()->json([
            'status' => true,
            'total_items' => $totalItems,
            'sub_total' => $totalAmount,
            'total_weight' => $totalWeight,
            'weight_in_kg' => $weightInKg,
            'courier_charge' => (int)$courierCharge,
            'total_amount' => $totalAmount + (int)$courierCharge,
            'data' => $carts
        ]);
    }

    public function increaseQuantity(Cart $cart)
    {
        $stock = $cart->stock;
        $sizeColumn = strtolower($cart->size) . '_quantity';
        
        if ($stock->$sizeColumn < ($cart->quantity + 1)) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient stock quantity'
            ], 400);
        }
    
        $cart->increment('quantity');
    
        // Get updated cart summary
        $userCarts = Cart::where('firebase_uid', $cart->firebase_uid)
            ->with(['stock.product'])
            ->get();
    
        $totalItems = $userCarts->sum('quantity');
        $subTotal = $userCarts->sum(function($item) {
            return $item->quantity * (int)$item->stock->product->price;
        });
    
        $totalWeight = $userCarts->sum(function($item) {
            return $item->quantity * $item->stock->product->weight;
        });
    
        $weightInKg = $totalWeight / 1000;
        
        // Calculate shipping
        $courier = \App\Models\curriers::where('is_active', true)->first();
        $shippingFee = $courier->charge;
    
        if ($weightInKg > 1) {
            $extraKgs = ceil($weightInKg - 1);
            $shippingFee += ($extraKgs * $courier->extra_per_kg);
        }
    
        return response()->json([
            'status' => true,
            'cart_item' => [
                'id' => $cart->id,
                'quantity' => $cart->quantity,
                'size' => $cart->size,
                'subtotal' => $cart->quantity * (int)$cart->stock->product->price
            ],
            'summary' => [
                'item_count' => $totalItems,
                'subtotal' => (int)$subTotal,
                'shipping_fee' => (int)$shippingFee,
                'total' => (int)($subTotal + $shippingFee)
            ]
        ]);
    }
    
    public function decreaseQuantity(Cart $cart)
    {
        if ($cart->quantity <= 1) {
            return response()->json([
                'status' => false,
                'message' => 'Quantity cannot be less than 1'
            ], 400);
        }
    
        $cart->decrement('quantity');
    
        // Get updated cart summary
        $userCarts = Cart::where('firebase_uid', $cart->firebase_uid)
            ->with(['stock.product'])
            ->get();
    
        $totalItems = $userCarts->sum('quantity');
        $subTotal = $userCarts->sum(function($item) {
            return $item->quantity * (int)$item->stock->product->price;
        });
    
        $totalWeight = $userCarts->sum(function($item) {
            return $item->quantity * $item->stock->product->weight;
        });
    
        $weightInKg = $totalWeight / 1000;
        
        // Calculate shipping
        $courier = \App\Models\curriers::where('is_active', true)->first();
        $shippingFee = $courier->charge;
    
        if ($weightInKg > 1) {
            $extraKgs = ceil($weightInKg - 1);
            $shippingFee += ($extraKgs * $courier->extra_per_kg);
        }
    
        return response()->json([
            'status' => true,
            'cart_item' => [
                'id' => $cart->id,
                'quantity' => $cart->quantity,
                'size' => $cart->size,
                'subtotal' => $cart->quantity * (int)$cart->stock->product->price
            ],
            'summary' => [
                'item_count' => $totalItems,
                'subtotal' => (int)$subTotal,
                'shipping_fee' => (int)$shippingFee,
                'total' => (int)($subTotal + $shippingFee)
            ]
        ]);
    }
}