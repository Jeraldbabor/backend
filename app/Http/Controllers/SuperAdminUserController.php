<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuperAdminUserController extends Controller
{
    /**
     * Display a listing of the users across all schools.
     * GET /api/superadmin/users
     */
    public function index(Request $request)
    {
        $query = User::with('school');

        // Optional: filter by school_id
        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

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
     * POST /api/superadmin/users
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Exclude superadmin since another user requested it
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['superadmin', 'admin', 'parent', 'teacher', 'principal', 'student'])],
            'school_id' => 'nullable|exists:schools,id',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $profileImagePath = null;
        if ($request->hasFile('profile_image')) {
            $profileImagePath = $request->file('profile_image')->store('profiles', 'public');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Auto-hashed via model cast
            'role' => $validated['role'],
            'school_id' => $validated['school_id'] ?? null,
            'profile_image' => $profileImagePath,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Display the specified user.
     * GET /api/superadmin/users/{id}
     */
    public function show(User $user)
    {
        return response()->json([
            'user' => $user->load('school'),
        ]);
    }

    /**
     * Update the specified user in storage.
     * PUT /api/superadmin/users/{id}
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6', // Optional when updating
            'role' => ['sometimes', 'required', Rule::in(['superadmin', 'admin', 'parent', 'teacher', 'principal', 'student'])],
            'school_id' => 'sometimes|nullable|exists:schools,id',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // If password is provided, update it
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
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
        if (array_key_exists('school_id', $validated)) {
            $user->school_id = $validated['school_id'];
        }

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_image);
            }
            $user->profile_image = $request->file('profile_image')->store('profiles', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Remove the specified user from storage.
     * DELETE /api/superadmin/users/{id}
     */
    public function destroy(User $user)
    {
        // Prevent deleting the currently authenticated superadmin
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
