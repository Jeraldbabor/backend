<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * MobileAuthController
 *
 * Handles authentication for mobile app users (parent, teacher, principal, student).
 * Uses Laravel Sanctum tokens — similar to AdminAuthController but for mobile roles.
 */
class MobileAuthController extends Controller
{
    /** Roles allowed to log in via the mobile app */
    private const MOBILE_ROLES = ['parent', 'teacher', 'principal', 'student'];

    /**
     * Mobile Login
     *
     * POST /api/mobile/login
     * Body: { email, password }
     * Returns: { user, token }
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! in_array($user->role, self::MOBILE_ROLES)) {
            throw ValidationException::withMessages([
                'email' => ['Your account does not have mobile app access.'],
            ]);
        }

        $token = $user->createToken('mobile-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load('school'),
            'token' => $token,
        ]);
    }

    /**
     * Mobile Logout
     *
     * POST /api/mobile/logout
     * Requires: Bearer token
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get Current User
     *
     * GET /api/mobile/me
     * Requires: Bearer token
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('school');

        // For parents, also load their linked students
        $data = ['user' => $user];

        if ($user->role === 'parent') {
            $data['students'] = $user->students()->with('school')->get();
        }

        return response()->json($data);
    }

    /**
     * Get Dashboard Stats
     *
     * GET /api/mobile/dashboard
     * Returns role-specific dashboard data
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $data = ['role' => $user->role];

        if ($user->role === 'parent') {
            $studentIds = $user->students()->pluck('id');
            $today = now()->startOfDay();

            $data['students'] = $user->students()
                ->with(['attendanceLogs' => function ($q) use ($today) {
                    $q->where('scanned_at', '>=', $today)->orderBy('scanned_at', 'desc');
                }])
                ->get();

            $data['total_students'] = $studentIds->count();

        } elseif ($user->role === 'teacher') {
            $assignments = $user->teacherAssignments()->get();
            $today = now()->startOfDay();

            $data['assignments'] = $assignments;
            $data['students_today'] = [];

            foreach ($assignments as $assignment) {
                $students = \App\Models\Student::where('school_id', $assignment->school_id)
                    ->where('grade', $assignment->grade)
                    ->where('section', $assignment->section)
                    ->with(['attendanceLogs' => function ($q) use ($today) {
                        $q->where('scanned_at', '>=', $today)->orderBy('scanned_at', 'desc');
                    }])
                    ->get();

                $data['students_today'][] = [
                    'grade' => $assignment->grade,
                    'section' => $assignment->section,
                    'students' => $students,
                    'present' => $students->filter(fn ($s) => $s->attendanceLogs->isNotEmpty())->count(),
                    'total' => $students->count(),
                ];
            }

        } elseif ($user->role === 'principal') {
            $today = now()->startOfDay();
            $schoolId = $user->school_id;

            $totalStudents = \App\Models\Student::where('school_id', $schoolId)->count();
            $presentToday = \App\Models\AttendanceLog::where('school_id', $schoolId)
                ->where('scanned_at', '>=', $today)
                ->where('direction', 'in')
                ->distinct('student_id')
                ->count('student_id');

            $data['total_students'] = $totalStudents;
            $data['present_today'] = $presentToday;
            $data['absent_today'] = $totalStudents - $presentToday;
            $data['attendance_rate'] = $totalStudents > 0
                ? round(($presentToday / $totalStudents) * 100, 1)
                : 0;

        } elseif ($user->role === 'student') {
            // Students can see their own attendance via parent linkage
            $student = \App\Models\Student::where('parent_id', $user->id)->first();
            if ($student) {
                $data['student'] = $student;
                $data['recent_scans'] = $student->attendanceLogs()
                    ->orderBy('scanned_at', 'desc')
                    ->limit(20)
                    ->get();
            }
        }

        return response()->json($data);
    }

    /**
     * Store Expo Push Token
     *
     * POST /api/mobile/push-token
     * Body: { token, device_name? }
     */
    public function storePushToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = $request->user();

        // Use updateOrCreate so if the token already exists for this user, it just updates device_name
        // If it was somehow tied to another user, we re-link it to this user (phones change hands)
        \Illuminate\Support\Facades\DB::table('device_tokens')
            ->where('expo_push_token', $request->token)
            ->where('user_id', '!=', $user->id)
            ->delete();

        \Illuminate\Support\Facades\DB::table('device_tokens')->updateOrInsert(
            ['user_id' => $user->id, 'expo_push_token' => $request->token],
            ['device_name' => $request->device_name, 'updated_at' => now()]
        );

        return response()->json(['message' => 'Push token registered successfully.']);
    }
}
