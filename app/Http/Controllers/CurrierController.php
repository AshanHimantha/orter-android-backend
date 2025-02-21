<?php

namespace App\Http\Controllers;

use App\Models\curriers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CurrierController extends Controller
{
    /**
     * Display a listing of active curriers.
     */
    public function index()
    {
        try {
            $curriers = curriers::where('is_active', true)->get();

            return response()->json([
                'status' => true,
                'data' => $curriers
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching curriers:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Error fetching curriers'
            ], 500);
        }
    }

    /**
     * Store a new currier.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'charge' => 'required|numeric|min:0',
                'extra_per_kg' => 'required|numeric|min:0',
                'is_active' => 'boolean'
            ]);

            $currier = curriers::create($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Currier created successfully',
                'data' => $currier
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating currier:', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Error creating currier'
            ], 500);
        }
    }

    /**
     * Update currier details.
     */
    public function update(Request $request, $id)
    {
        try {
            $currier = curriers::findOrFail($id);

            $request->validate([
                'name' => 'string|max:255',
                'description' => 'nullable|string',
                'charge' => 'numeric|min:0',
                'extra_per_kg' => 'numeric|min:0',
                'is_active' => 'boolean'
            ]);

            $currier->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Currier updated successfully',
                'data' => $currier
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating currier:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error updating currier'
            ], 500);
        }
    }

    /**
     * Delete a currier.
     */
    public function destroy($id)
    {
        try {
            $currier = curriers::findOrFail($id);
            $currier->delete();

            return response()->json([
                'status' => true,
                'message' => 'Currier deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting currier:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error deleting currier'
            ], 500);
        }
    }

    /**
     * Toggle currier active status.
     */
    public function toggleActive($id)
    {
        try {
            $currier = curriers::findOrFail($id);
            $currier->is_active = !$currier->is_active;
            $currier->save();

            return response()->json([
                'status' => true,
                'message' => 'Currier status updated successfully',
                'data' => $currier
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling currier status:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Error updating currier status'
            ], 500);
        }
    }
}