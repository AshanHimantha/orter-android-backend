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
            'xs_quantity' => 'required|integer|min:0',
            's_quantity' => 'required|integer|min:0',
            'm_quantity' => 'required|integer|min:0',
            'l_quantity' => 'required|integer|min:0',
            'xl_quantity' => 'required|integer|min:0',
            'xxl_quantity' => 'required|integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Check if stock exists for product
        $existingStock = Stock::where('product_id', $request->product_id)->first();
        if ($existingStock) {
            return response()->json([
                'status' => false,
                'message' => 'Stock already exists for this product'
            ], 400);
        }

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
            'xs_quantity' => 'integer|min:0',
            's_quantity' => 'integer|min:0',
            'm_quantity' => 'integer|min:0',
            'l_quantity' => 'integer|min:0',
            'xl_quantity' => 'integer|min:0',
            'xxl_quantity' => 'integer|min:0',
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

    public function stockList($limit = 10)
    {
        $stocks = Stock::with(['product' => function($query) {
            $query->select('id', 'name', 'price', 'main_image', 'collection_id');
        }, 'product.collection:id,name'])
        ->whereHas('product', function($query) {
            $query->where('is_active', "1");
        })
        ->latest()
        ->take($limit)
        ->get();
    
        $formattedStocks = $stocks->map(function ($stock) {
            return [
                'id' => $stock->id,
                'product_name' => $stock->product->name,
                'price' => (int)$stock->product->price, // Cast to integer
                'main_image' => asset('storage/' . $stock->product->main_image),
                'collection_name' => $stock->product->collection->name ?? null,
                'total_quantity' => $stock->total_quantity,
            ];
        });
    
        return response()->json([
            'status' => true,
            'count' => $stocks->count(),
            'data' => $formattedStocks
        ]);
    }

    public function filterByCategory($categoryId, $limit = 10)
{
    $stocks = Stock::with(['product' => function($query) use ($categoryId) {
        $query->where('category_id', $categoryId)
            ->select('id', 'name', 'price', 'main_image', 'collection_id', 'category_id');
    }, 'product.collection:id,name'])
        ->whereHas('product', function($query) use ($categoryId) {
            $query->where('category_id', $categoryId);
        })
        ->latest()
        ->take($limit)
        ->get();

    $formattedStocks = $stocks->map(function ($stock) {
        return [
            'id' => $stock->id,
            'product_name' => $stock->product->name,
            'price' => (int)$stock->product->price,
            'main_image' => asset('storage/' . $stock->product->main_image),
            'collection_name' => $stock->product->collection->name ?? null,
            'total_quantity' => $stock->total_quantity,
        ];
    });

    return response()->json([
        'status' => true,
        'count' => $stocks->count(),
        'data' => $formattedStocks
    ]);
}


public function getLatestStocks($limit = 10)
{
    $stocks = Stock::with(['product' => function($query) {
        $query->select('id', 'name', 'price', 'main_image', 'collection_id', 'category_id');
    }, 'product.collection:id,name'])
        ->whereHas('product', function($query) {
            $query->where('is_active', "1");
        })
        ->latest()
        ->take($limit)
        ->get();

    $formattedStocks = $stocks->map(function ($stock) {
        return [
            'id' => $stock->id,
            'product_name' => $stock->product->name,
            'price' => (int)$stock->product->price,
            'main_image' => asset('storage/' . $stock->product->main_image),
            'collection_name' => $stock->product->collection->name ?? null,
            'total_quantity' => $stock->total_quantity,
        ];
    });

    return response()->json([
        'status' => true,
        'count' => $stocks->count(),
        'data' => $formattedStocks
    ]);
}
}
