<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        $stocks = Stock::with('product')->get();
        return response()->json(['status' => true, 'data' => $stocks]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'size' => 'required|in:XS,S,M,L,XL,XXL',
            'quantity' => 'required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        $stock = Stock::create($request->all());
        return response()->json([
            'status' => true,
            'data' => $stock->load('product')
        ], 201);
    }

    public function show(Stock $stock)
    {
        return response()->json([
            'status' => true,
            'data' => $stock->load('product')
        ]);
    }

    public function update(Request $request, Stock $stock)
    {
        $request->validate([
            'product_id' => 'exists:products,id',
            'size' => 'in:XS,S,M,L,XL,XXL',
            'quantity' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);

        $stock->update($request->all());
        return response()->json([
            'status' => true,
            'data' => $stock->load('product')
        ]);
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();
        return response()->json([
            'status' => true,
            'message' => 'Stock deleted successfully'
        ]);
    }

    public function updateQuantity(Request $request, Stock $stock)
    {
        $request->validate(['quantity' => 'required|integer|min:0']);

        $stock->update(['quantity' => $request->quantity]);
        return response()->json([
            'status' => true,
            'data' => $stock->load('product')
        ]);
    }
}
