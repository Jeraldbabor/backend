<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAttendanceScan;
use App\Models\AttendanceLog;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * KioskController
 *
 * Handles RFID scan requests from the gate kiosk device.
 * Authenticated via X-Kiosk-Api-Key header (KioskApiKey middleware).
 *
 * Flow:
 * 1. Receive RFID code from kiosk
 * 2. Look up student
 * 3. Throttle duplicate scans (5 min cooldown)
 * 4. Determine direction (in/out)
 * 5. Create attendance log
 * 6. Dispatch notification job
 * 7. Return student info to kiosk display
 */
class KioskController extends Controller
{
    /**
     * Process an RFID scan from the gate kiosk.
     *
     * POST /api/kiosk/scan
     * Headers: X-Kiosk-Api-Key
     * Body: { rfid_code, school_id }
     */
    public function scan(Request $request)
    {
        $request->validate([
            'rfid_code' => 'required|string|max:100',
            'school_id' => 'required|integer|exists:schools,id',
        ]);

        // --- 1. Find student by RFID code ---
        $student = Student::where('rfid_code', $request->rfid_code)
            ->where('school_id', $request->school_id)
            ->first();

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Unrecognized RFID card. Please contact the admin.',
            ], 404);
        }

        // --- 1.5 Find Adviser ---
        $adviser = Teacher::where('school_id', $student->school_id)
            ->where('grade', $student->grade)
            ->where('section', $student->section)
            ->with('user')
            ->first();
            
        $adviserName = $adviser && $adviser->user ? $adviser->user->name : 'No Adviser Assigned';

        // --- 2. Throttle: prevent duplicate scans within 5 minutes ---
        $recentScan = AttendanceLog::where('student_id', $student->id)
            ->where('scanned_at', '>=', Carbon::now()->subMinutes(5))
            ->latest('scanned_at')
            ->first();

        if ($recentScan) {
            return response()->json([
                'success' => false,
                'message' => 'Already scanned recently. Please wait 5 minutes.',
                'student' => [
                    'name' => $student->full_name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'profile_image_url' => $student->profile_image_url,
                    'adviser_name' => $adviserName,
                ],
                'last_scan' => $recentScan->scanned_at->toISOString(),
            ], 429);
        }

        // --- 3. Determine direction ---
        // If the student already has an "in" scan today without a matching "out", this is "out"
        $todayInScan = AttendanceLog::where('student_id', $student->id)
            ->where('direction', 'in')
            ->whereDate('scanned_at', Carbon::today())
            ->latest('scanned_at')
            ->first();

        $todayOutScan = $todayInScan
            ? AttendanceLog::where('student_id', $student->id)
                ->where('direction', 'out')
                ->where('scanned_at', '>', $todayInScan->scanned_at)
                ->exists()
            : false;

        $direction = ($todayInScan && ! $todayOutScan) ? 'out' : 'in';

        // --- 4. Create attendance log ---
        $now = Carbon::now();
        $attendanceLog = AttendanceLog::create([
            'student_id' => $student->id,
            'school_id' => $student->school_id,
            'rfid_code' => $request->rfid_code,
            'scanned_at' => $now,
            'direction' => $direction,
        ]);

        // --- 5. Dispatch notification job (async) ---
        ProcessAttendanceScan::dispatch($attendanceLog);

        // --- 6. Return result to kiosk display ---
        return response()->json([
            'success' => true,
            'message' => $direction === 'in'
                ? "Welcome, {$student->full_name}!"
                : "Goodbye, {$student->full_name}! Stay safe.",
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'grade' => $student->grade,
                'section' => $student->section,
                'profile_image_url' => $student->profile_image_url,
                'adviser_name' => $adviserName,
            ],
            'scan' => [
                'direction' => $direction,
                'scanned_at' => $now->toISOString(),
            ],
        ]);
    }
}
