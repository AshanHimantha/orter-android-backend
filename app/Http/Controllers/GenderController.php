<?php

namespace App\Http\Controllers;

use App\Models\Gender;
use Illuminate\Http\Request;

class GenderController extends Controller
{
    public function index()
    {
        $genders = Gender::all();
        return response()->json(['status' => true, 'data' => $genders]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:genders',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $gender = Gender::create($request->all());
        return response()->json(['status' => true, 'data' => $gender], 201);
    }

    public function show(Gender $gender)
    {
        return response()->json(['status' => true, 'data' => $gender]);
    }

    public function update(Request $request, Gender $gender)
    {
        $request->validate([
            'name' => 'string|unique:genders,name,'.$gender->id,
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $gender->update($request->all());
        return response()->json(['status' => true, 'data' => $gender]);
    }

    public function destroy(Gender $gender)
    {
        $gender->delete();
        return response()->json(['status' => true, 'message' => 'Gender deleted successfully']);
    }
}
