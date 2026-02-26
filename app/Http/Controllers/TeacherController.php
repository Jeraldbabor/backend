<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * TeacherController
 *
 * Admin-scoped CRUD for teacher grade/section assignments.
 * A user with role=teacher can have multiple assignments (multiple sections).
 */
class TeacherController extends Controller
{
    /**
     * List teacher assignments for the admin's school.
     * GET /api/admin/teachers
     */
    public function index(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $query = Teacher::where('school_id', $schoolId)
            ->with('user:id,name,email');

        if ($request->has('grade')) {
            $query->where('grade', $request->grade);
        }

        if ($request->has('section')) {
            $query->where('section', $request->section);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $teachers = $query->orderBy('grade')->orderBy('section')->paginate(15);

        return response()->json($teachers);
    }

    /**
     * Assign a teacher to a grade/section.
     * POST /api/admin/teachers
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'grade' => 'required|string|max:50',
            'section' => 'required|string|max:50',
        ]);

        // Verify the user has the teacher role
        $user = User::find($validated['user_id']);
        if (! $user || $user->role !== 'teacher') {
            return response()->json([
                'message' => 'User must have the teacher role.',
            ], 422);
        }

        $validated['school_id'] = auth()->user()->school_id;

        // Check for duplicate assignment
        $exists = Teacher::where('user_id', $validated['user_id'])
            ->where('grade', $validated['grade'])
            ->where('section', $validated['section'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This teacher is already assigned to this grade and section.',
            ], 422);
        }

        $teacher = Teacher::create($validated);

        return response()->json([
            'message' => 'Teacher assigned successfully.',
            'teacher' => $teacher->load('user:id,name,email'),
        ], 201);
    }

    /**
     * Show a single teacher assignment.
     * GET /api/admin/teachers/{teacher}
     */
    public function show(Teacher $teacher)
    {
        if ($teacher->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'teacher' => $teacher->load('user:id,name,email'),
        ]);
    }

    /**
     * Update a teacher assignment.
     * PUT /api/admin/teachers/{teacher}
     */
    public function update(Request $request, Teacher $teacher)
    {
        if ($teacher->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'grade' => 'sometimes|required|string|max:50',
            'section' => 'sometimes|required|string|max:50',
        ]);

        $teacher->update($validated);

        return response()->json([
            'message' => 'Teacher assignment updated successfully.',
            'teacher' => $teacher->load('user:id,name,email'),
        ]);
    }

    /**
     * Remove a teacher assignment.
     * DELETE /api/admin/teachers/{teacher}
     */
    public function destroy(Teacher $teacher)
    {
        if ($teacher->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $teacher->delete();

        return response()->json(['message' => 'Teacher assignment removed successfully.']);
    }
}
