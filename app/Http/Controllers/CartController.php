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

        // Check stock availability
        $stock = Stock::findOrFail($request->stock_id);
        $sizeColumn = strtolower($request->size) . '_quantity';
        
        if ($stock->$sizeColumn < $request->quantity) {
            return response()->json([
                'status' => false,
                'message' => 'Insufficient stock quantity'
            ], 400);
        }

        // Check if item already exists in cart
        $existingCart = Cart::where([
            'firebase_uid' => $request->firebase_uid,
            'stock_id' => $request->stock_id,
            'size' => $request->size
        ])->first();

        if ($existingCart) {
            $existingCart->update([
                'quantity' => $existingCart->quantity + $request->quantity
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
        $cart->delete();
        return response()->json([
            'status' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    public function getUserCart(Request $request)
    {
        $carts = Cart::where('firebase_uid', $request->firebase_uid)
            ->with(['stock.product'])
            ->get()
            ->map(function ($cart) {
                return [
                    'id' => $cart->id,
                    'size' => $cart->size,
                    'quantity' => $cart->quantity,
                    'product' => [
                        'name' => $cart->stock->product->name,
                        'price' => (int)$cart->stock->product->price,
                        'main_image' => $cart->stock->product->main_image_url
                    ]
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $carts
        ]);
    }
}