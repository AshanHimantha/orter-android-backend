<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['gender', 'category', 'collection', 'addedBy'])->get();
        return response()->json(['status' => true, 'data' => $products]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'gender_id' => 'required|exists:genders,id',
            'category_id' => 'required|exists:product_categories,id',
            'collection_id' => 'nullable|exists:collections,id',
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'weight' => 'required|numeric|min:0',  // Add weight validation
            'material' => 'required|string',
            'color' => 'required|string',
            'main_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'image_1' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'image_2' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $data = $request->except(['main_image', 'image_1', 'image_2']);

        // Store main image
        if ($request->hasFile('main_image')) {
            $data['main_image'] = $request->file('main_image')->store('products', 'public');
        }

        // Store additional images
        if ($request->hasFile('image_1')) {
            $data['image_1'] = $request->file('image_1')->store('products', 'public');
        }

        if ($request->hasFile('image_2')) {
            $data['image_2'] = $request->file('image_2')->store('products', 'public');
        }

        $product = Product::create([
            'added_by' => $request->user()->id,
            ...$data
        ]);

        return response()->json([
            'status' => true,
            'data' => $product->load(['gender', 'category', 'collection', 'addedBy'])
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'status' => true,
            'data' => $product->load(['gender', 'category', 'collection', 'addedBy'])
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'gender_id' => 'exists:genders,id',
            'category_id' => 'exists:product_categories,id',
            'collection_id' => 'nullable|exists:collections,id',
            'name' => 'string',
            'description' => 'string',
            'price' => 'numeric',
            'weight' => 'numeric|min:0',  // Add weight validation
            'material' => 'string',
            'color' => 'string',
            'main_image' => 'string',
            'image_1' => 'nullable|string',
            'image_2' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $product->update($request->all());

        return response()->json([
            'status' => true,
            'data' => $product->load(['gender', 'category', 'collection', 'addedBy'])
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    public function getImage($filename)
    {
        $path = 'products/' . $filename;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json([
                'status' => false,
                'message' => 'Image not found'
            ], 404);
        }

        $file = Storage::disk('public')->get($path);
        $type = mime_content_type(Storage::disk('public')->path($path));

        return response($file, 200)
            ->header('Content-Type', $type)
            ->header('Cache-Control', 'public, max-age=31536000');
    }
}
