<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UniversityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (auth()->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $universities = \App\Models\University::orderBy('name')->get();
        return response()->json($universities);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:universities,name',
        ]);

        $university = \App\Models\University::create($validated);

        return response()->json([
            'message' => 'University created successfully',
            'university' => $university,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (auth()->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $university = \App\Models\University::findOrFail($id);
        return response()->json([
            'university' => $university,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (auth()->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $university = \App\Models\University::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:universities,name,' . $university->id,
        ]);

        $university->update($validated);

        return response()->json([
            'message' => 'University updated successfully',
            'university' => $university,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (auth()->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $university = \App\Models\University::findOrFail($id);
        $university->delete();

        return response()->json([
            'message' => 'University deleted successfully',
        ]);
    }
}
