<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::with('gender')->get();
        return response()->json(['status' => true, 'data' => $categories]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'gender_id' => 'required|exists:genders,id',
            'name' => 'required|string|unique:product_categories',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $category = ProductCategory::create($request->all());
        return response()->json(['status' => true, 'data' => $category], 201);
    }

    public function show(ProductCategory $category)
    {
        return response()->json(['status' => true, 'data' => $category->load('gender')]);
    }

    public function update(Request $request, ProductCategory $category)
    {
        $request->validate([
            'gender_id' => 'exists:genders,id',
            'name' => 'string|unique:product_categories,name,'.$category->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $category->update($request->all());
        return response()->json(['status' => true, 'data' => $category->load('gender')]);
    }

    public function destroy(ProductCategory $category)
    {
        $category->delete();
        return response()->json(['status' => true, 'message' => 'Category deleted successfully']);
    }
}
