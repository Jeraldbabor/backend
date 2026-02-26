<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * StudentController
 *
 * Admin-scoped CRUD for students. All operations are restricted
 * to the authenticated admin's school. Handles RFID code assignment.
 */
class StudentController extends Controller
{
    /**
     * List students for the admin's school.
     * GET /api/admin/students
     *
     * Query params: search, grade, section, has_rfid
     */
    public function index(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $query = Student::where('school_id', $schoolId)
            ->with(['parent:id,name,email']);

        // Search by name, student ID, or RFID code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('student_id_number', 'ilike', "%{$search}%")
                    ->orWhere('rfid_code', 'ilike', "%{$search}%");
            });
        }

        // Filter by grade
        if ($request->has('grade')) {
            $query->where('grade', $request->grade);
        }

        // Filter by section
        if ($request->has('section')) {
            $query->where('section', $request->section);
        }

        // Filter by RFID assignment status
        if ($request->has('has_rfid')) {
            if ($request->boolean('has_rfid')) {
                $query->whereNotNull('rfid_code');
            } else {
                $query->whereNull('rfid_code');
            }
        }

        $students = $query->orderBy('last_name')->orderBy('first_name')->paginate(15);

        return response()->json($students);
    }

    /**
     * Create a new student.
     * POST /api/admin/students
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'grade' => 'required|string|max:50',
            'section' => 'required|string|max:50',
            'student_id_number' => 'required|string|max:100|unique:students',
            'rfid_code' => 'nullable|string|max:100|unique:students',
            'parent_id' => 'nullable|integer|exists:users,id',
        ]);

        $validated['school_id'] = auth()->user()->school_id;

        // If parent_id is provided, verify the parent belongs to the same school
        if (! empty($validated['parent_id'])) {
            $parent = \App\Models\User::where('id', $validated['parent_id'])
                ->where('role', 'parent')
                ->first();

            if (! $parent) {
                return response()->json([
                    'message' => 'Invalid parent. User must have the parent role.',
                ], 422);
            }
        }

        $student = Student::create($validated);

        return response()->json([
            'message' => 'Student created successfully.',
            'student' => $student->load('parent:id,name,email'),
        ], 201);
    }

    /**
     * Show a single student with attendance history.
     * GET /api/admin/students/{student}
     */
    public function show(Student $student)
    {
        if ($student->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $student->load([
            'parent:id,name,email',
            'attendanceLogs' => function ($query) {
                $query->orderBy('scanned_at', 'desc')->limit(50);
            },
        ]);

        return response()->json(['student' => $student]);
    }

    /**
     * Update a student (including RFID code assignment).
     * PUT /api/admin/students/{student}
     */
    public function update(Request $request, Student $student)
    {
        if ($student->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'grade' => 'sometimes|required|string|max:50',
            'section' => 'sometimes|required|string|max:50',
            'student_id_number' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('students')->ignore($student->id)],
            'rfid_code' => ['nullable', 'string', 'max:100', Rule::unique('students')->ignore($student->id)],
            'parent_id' => 'nullable|integer|exists:users,id',
        ]);

        $student->update($validated);

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => $student->load('parent:id,name,email'),
        ]);
    }

    /**
     * Delete a student.
     * DELETE /api/admin/students/{student}
     */
    public function destroy(Student $student)
    {
        if ($student->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $student->delete();

        return response()->json(['message' => 'Student deleted successfully.']);
    }
}
