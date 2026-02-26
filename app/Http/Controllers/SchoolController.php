<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (auth()->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $schools = \App\Models\School::orderBy('name')->get();

        return response()->json($schools);
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
            'name' => 'required|string|max:255|unique:schools,name',
        ]);

        $school = \App\Models\School::create($validated);

        return response()->json([
            'message' => 'School created successfully',
            'school' => $school,
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

        $school = \App\Models\School::findOrFail($id);

        return response()->json([
            'school' => $school,
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

        $school = \App\Models\School::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:schools,name,'.$school->id,
        ]);

        $school->update($validated);

        return response()->json([
            'message' => 'School updated successfully',
            'school' => $school,
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

        $school = \App\Models\School::findOrFail($id);
        $school->delete();

        return response()->json([
            'message' => 'School deleted successfully',
        ]);
    }
}
