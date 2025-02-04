<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Collection::all();
        return response()->json(['status' => true, 'data' => $collections]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:collections',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $collection = Collection::create($request->all());
        return response()->json(['status' => true, 'data' => $collection], 201);
    }

    public function show(Collection $collection)
    {
        return response()->json(['status' => true, 'data' => $collection]);
    }

    public function update(Request $request, Collection $collection)
    {
        $request->validate([
            'name' => 'string|unique:collections,name,'.$collection->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $collection->update($request->all());
        return response()->json(['status' => true, 'data' => $collection]);
    }

    public function destroy(Collection $collection)
    {
        $collection->delete();
        return response()->json(['status' => true, 'message' => 'Collection deleted successfully']);
    }
}
