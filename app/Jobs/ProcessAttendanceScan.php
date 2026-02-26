<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;

/**
 * ProcessAttendanceScan Job
 *
 * Dispatched after a successful RFID scan at the school gate.
 * Creates notification records for:
 * 1. The student's parent (if linked)
 * 2. All teachers assigned to the student's grade/section at the same school
 */
class ProcessAttendanceScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected AttendanceLog $attendanceLog
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = $this->attendanceLog->load(['student', 'school']);
        $student = $log->student;
        $school = $log->school;

        if (! $student || ! $school) {
            return;
        }

        $direction = $log->direction === 'in' ? 'arrived at' : 'left';
        $time = $log->scanned_at->format('g:i A');
        $date = $log->scanned_at->format('M d, Y');
        $notificationType = $log->direction === 'in' ? 'attendance_in' : 'attendance_out';

        $notificationData = [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'grade' => $student->grade,
            'section' => $student->section,
            'school_name' => $school->name,
            'direction' => $log->direction,
            'scanned_at' => $log->scanned_at->toISOString(),
            'attendance_log_id' => $log->id,
        ];

        $userIdsToNotify = [];

        // --- 1. Notify the parent ---
        if ($student->parent_id) {
            $userIdsToNotify[] = $student->parent_id;

            Notification::create([
                'user_id' => $student->parent_id,
                'title' => $log->direction === 'in'
                    ? '✅ Student Arrived at School'
                    : '🏠 Student Left School',
                'body' => "Your child {$student->full_name} has {$direction} {$school->name} at {$time} on {$date}.",
                'type' => $notificationType,
                'data' => $notificationData,
            ]);
        }

        // --- 2. Notify teachers of the same grade & section ---
        $teachers = Teacher::where('school_id', $student->school_id)
            ->where('grade', $student->grade)
            ->where('section', $student->section)
            ->with('user')
            ->get();

        foreach ($teachers as $teacher) {
            if (! $teacher->user) {
                continue;
            }

            $userIdsToNotify[] = $teacher->user_id;

            Notification::create([
                'user_id' => $teacher->user_id,
                'title' => $log->direction === 'in'
                    ? '📋 Student Arrived'
                    : '📋 Student Departed',
                'body' => "Student {$student->full_name} (Grade {$student->grade} - Section {$student->section}) has {$direction} {$school->name} at {$time}.",
                'type' => $notificationType,
                'data' => $notificationData,
            ]);
        }

        // --- 3. Send ACTUAL Push Notifications via Expo ---
        if (!empty($userIdsToNotify)) {
            // Find all Expo tokens belonging to these users
            $tokens = DB::table('device_tokens')
                ->whereIn('user_id', $userIdsToNotify)
                ->pluck('expo_push_token')
                ->toArray();

            if (!empty($tokens)) {
                try {
                    $title = $log->direction === 'in' ? 'Student Arrived' : 'Student Departed';
                    $body = "{$student->full_name} has {$direction} {$school->name} at {$time}.";

                    $messages = [
                        new ExpoMessage([
                            'title' => $title,
                            'body' => $body,
                            'to' => $tokens,
                            'sound' => 'default',
                            'data' => $notificationData,
                        ]),
                    ];

                    (new Expo)->send($messages);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Expo Push Notification Failed: ' . $e->getMessage());
                }
            }
        }
    }
}
