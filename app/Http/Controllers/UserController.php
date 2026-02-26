<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $query = User::with('school');

        // Scope to admin's school
        $query->where('school_id', auth()->user()->school_id)
            ->where('role', '!=', 'superadmin');

        // Optional: filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Optional: search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Sort by newest first and paginate
        $users = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($users);
    }

    /**
     * Store a newly created user in storage.
     * POST /api/admin/users
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['superadmin', 'admin', 'parent', 'teacher', 'principal', 'student'])],
            'school_id' => 'nullable|exists:schools,id',
        ]);

        if ($validated['role'] === 'superadmin') {
            return response()->json(['message' => 'Unauthorized to assign superadmin role.'], 403);
        }
        $validated['school_id'] = auth()->user()->school_id;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Auto-hashed via model cast
            'role' => $validated['role'],
            'school_id' => $validated['school_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Display the specified user.
     * GET /api/admin/users/{id}
     */
    public function show(User $user)
    {
        if ($user->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized access to user from another school.'], 403);
        }

        return response()->json([
            'user' => $user->load('school'),
        ]);
    }

    /**
     * Update the specified user in storage.
     * PUT /api/admin/users/{id}
     */
    public function update(Request $request, User $user)
    {
        if ($user->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized access to update user from another school.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6', // Optional when updating
            'role' => ['sometimes', 'required', Rule::in(['superadmin', 'admin', 'parent', 'teacher', 'principal', 'student'])],
            'school_id' => 'sometimes|nullable|exists:schools,id',
        ]);

        if (isset($validated['role']) && $validated['role'] === 'superadmin') {
            return response()->json(['message' => 'Unauthorized to assign superadmin role.'], 403);
        }

        // If password is provided, update it
        if (! empty($validated['password'])) {
            $user->password = $validated['password']; // Auto-hashed via model cast
        }

        // Update other fields if present
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['role'])) {
            $user->role = $validated['role'];
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Remove the specified user from storage.
     * DELETE /api/admin/users/{id}
     */
    public function destroy(User $user)
    {
        if ($user->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized to delete user from another school.'], 403);
        }

        // Prevent deleting the currently authenticated admin
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'You cannot delete yourself from this endpoint. Use the account settings instead.',
            ], 403);
        }

        // Revoke their API tokens and delete the user
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
