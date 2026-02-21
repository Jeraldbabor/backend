<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * AuthController
 *
 * Handles admin authentication via Laravel Sanctum tokens.
 * - login:  Validates credentials, checks admin role, issues a token.
 * - logout: Revokes the current access token.
 * - me:     Returns the authenticated user's data.
 */
class AuthController extends Controller
{
    /**
     * Admin Login
     *
     * POST /api/admin/login
     * Body: { email, password }
     * Returns: { user, token }
     */
    public function login(Request $request)
    {
        // Step 1: Validate incoming request data
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Step 2: Find the user by email
        $user = User::where('email', $request->email)->first();

        // Step 3: Check if user exists and password matches
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Step 4: Ensure the user has admin or superadmin role
        if (! $user->isAdmin() && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'email' => ['Access denied. Admin privileges required.'],
            ]);
        }

        // Step 5: Create a Sanctum token for the admin
        $token = $user->createToken('admin-token')->plainTextToken;

        // Step 6: Return the user data and token
        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Admin Logout
     *
     * POST /api/admin/logout
     * Requires: Bearer token (Sanctum)
     * Returns: { message }
     */
    public function logout(Request $request)
    {
        // Revoke the current access token used for this request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get Authenticated Admin
     *
     * GET /api/admin/me
     * Requires: Bearer token (Sanctum)
     * Returns: { user }
     */
    public function me(Request $request)
    {
        // Return the currently authenticated user's data with university
        $user = $request->user()->load('university');
        
        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update Password
     *
     * PUT /api/admin/password
     * Body: { current_password, password, password_confirmation }
     * Returns: { message }
     */
    public function updatePassword(Request $request)
    {
        // Step 1: Validate the request data
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        // Step 2: Verify the current password is correct
        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Step 3: Update the password (auto-hashed via model cast)
        $user->update([
            'password' => $request->password,
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Delete Account
     *
     * DELETE /api/admin/account
     * Body: { password }
     * Returns: { message }
     */
    public function deleteAccount(Request $request)
    {
        // Step 1: Validate password confirmation
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        // Step 2: Verify the password is correct
        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        // Step 3: Revoke all tokens for this user
        $user->tokens()->delete();

        // Step 4: Delete the user account
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}
