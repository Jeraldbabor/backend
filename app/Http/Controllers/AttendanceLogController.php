<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;

/**
 * AttendanceLogController
 *
 * Read-only controller for viewing attendance logs.
 * Admin-scoped: only shows logs for the admin's school.
 */
class AttendanceLogController extends Controller
{
    /**
     * List attendance logs with filters.
     * GET /api/admin/attendance-logs
     *
     * Query params: date_from, date_to, student_id, grade, section, direction
     */
    public function index(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $query = AttendanceLog::where('school_id', $schoolId)
            ->with(['student:id,first_name,last_name,grade,section,student_id_number']);

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('scanned_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('scanned_at', '<=', $request->date_to);
        }

        // Filter by specific student
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by grade/section (via student relation)
        if ($request->has('grade') || $request->has('section')) {
            $query->whereHas('student', function ($q) use ($request) {
                if ($request->has('grade')) {
                    $q->where('grade', $request->grade);
                }
                if ($request->has('section')) {
                    $q->where('section', $request->section);
                }
            });
        }

        // Filter by direction
        if ($request->has('direction')) {
            $query->where('direction', $request->direction);
        }

        // Search by student name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        $logs = $query->orderBy('scanned_at', 'desc')->paginate(20);

        return response()->json($logs);
    }

    /**
     * Show a single attendance log.
     * GET /api/admin/attendance-logs/{attendanceLog}
     */
    public function show(AttendanceLog $attendanceLog)
    {
        if ($attendanceLog->school_id !== auth()->user()->school_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'attendance_log' => $attendanceLog->load('student'),
        ]);
    }

    /**
     * Export attendance logs as CSV.
     * GET /api/admin/attendance-logs/export
     */
    public function export(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $query = AttendanceLog::where('school_id', $schoolId)
            ->with('student:id,first_name,last_name,grade,section,student_id_number');

        if ($request->has('date_from')) {
            $query->whereDate('scanned_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('scanned_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('scanned_at', 'desc')->get();

        $csv = "Student ID,Name,Grade,Section,Direction,Scanned At\n";
        foreach ($logs as $log) {
            $student = $log->student;
            $csv .= implode(',', [
                $student?->student_id_number ?? 'N/A',
                '"'.($student?->full_name ?? 'Unknown').'"',
                $student?->grade ?? 'N/A',
                $student?->section ?? 'N/A',
                strtoupper($log->direction),
                $log->scanned_at->format('Y-m-d H:i:s'),
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_logs.csv"',
        ]);
    }
}
