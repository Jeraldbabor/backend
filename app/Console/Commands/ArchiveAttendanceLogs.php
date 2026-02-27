<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class ArchiveAttendanceLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:archive {--school= : The name of the school to exclusively archive logs for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive attendance logs older than the current school year into yearly tables.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting attendance logs archiving process...');

        $schoolOption = $this->option('school');
        $schoolId = null;
        $schoolPrefix = '';

        if ($schoolOption) {
            $school = DB::table('schools')->where('name', 'like', "%{$schoolOption}%")->first();
            if (!$school) {
                $this->error("School matching '{$schoolOption}' not found.");
                return;
            }
            $schoolId = $school->id;
            // Clean up name for table prefix (e.g., BUNHS -> bunhs_)
            $schoolPrefix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $school->name)) . '_';
            $this->info("Filtering archive ONLY for school: {$school->name} (ID: {$schoolId})");
        }

        // In the Philippines, the traditional School Year starts around June 1st.
        $currentDate = now();
        $currentSyStartYear = $currentDate->month >= 6 ? $currentDate->year : $currentDate->year - 1;
        $cutoffDate = Carbon::create($currentSyStartYear, 6, 1, 0, 0, 0);

        $this->info("Current School Year starts: {$currentSyStartYear}");
        $this->info("Cutoff Date for archiving: {$cutoffDate->toDateTimeString()}");

        // Find the oldest unarchived log before the cutoff.
        $query = DB::table('attendance_logs')->where('scanned_at', '<', $cutoffDate);
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }
        
        $oldestLog = $query->orderBy('scanned_at', 'asc')->first();

        if (!$oldestLog) {
            $this->info('No attendance logs require archiving at this time.');
            return;
        }

        $oldestDate = Carbon::parse($oldestLog->scanned_at);
        $archiveSyStartYear = $oldestDate->month >= 6 ? $oldestDate->year : $oldestDate->year - 1;
        
        // Loop through all past school years up to the current one
        while ($archiveSyStartYear < $currentSyStartYear) {
            $this->archiveSchoolYear($archiveSyStartYear, $schoolId, $schoolPrefix);
            $archiveSyStartYear++;
        }

        $this->info('Archiving process completed.');
    }

    /**
     * Archive logs for a specific school year bracket
     */
    private function archiveSchoolYear($startYear, $schoolId, $schoolPrefix)
    {
        $endYear = $startYear + 1;
        $tableName = "attendance_logs_{$schoolPrefix}{$startYear}";
        $startDate = Carbon::create($startYear, 6, 1, 0, 0, 0);
        $endDate = Carbon::create($endYear, 5, 31, 23, 59, 59);

        // CREATE TABLE DYNAMICALLY
        // If the table doesn't exist, we generate it directly.
        if (!Schema::hasTable($tableName)) {
            $this->info("Creating archive table: {$tableName}");
            Schema::create($tableName, function (Blueprint $table) {
                // Same structure as attendance_logs
                $table->id();
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('school_id');
                $table->string('rfid_code');
                $table->timestamp('scanned_at');
                $table->string('direction', 10)->default('in');
                $table->timestamps();
                
                // Keep indexes for audit fast querying
                // Do NOT enforce foreign constraints to keep it independent and safe
                $table->index(['school_id', 'scanned_at']);
                $table->index(['student_id', 'scanned_at']);
                $table->index('student_id');
                $table->index('created_at');
                $table->index('school_id');
            });
        }

        $this->info("Moving records for SY {$startYear}-{$endYear} to {$tableName}...");

        $totalArchived = 0;

        // Use chunkById to safely chunk and delete simultaneously. 
        // chunk() with offsets will skip rows if deleting.
        $chunkQuery = DB::table('attendance_logs')->whereBetween('scanned_at', [$startDate, $endDate]);
        if ($schoolId) {
            $chunkQuery->where('school_id', $schoolId);
        }
        
        $chunkQuery->chunkById(1000, function ($logs) use ($tableName, &$totalArchived) {
                // Convert object stdClass entries into arrays
                $records = $logs->map(function ($log) {
                    return (array) $log;
                })->toArray();

                // Group atomic insertions / unlinks inside a DB transaction to guarantee data persistence
                DB::transaction(function () use ($tableName, $records, $logs, &$totalArchived) {
                    // 1. Insert into archive table safely
                    DB::table($tableName)->insert($records);

                    // 2. Delete from original table safely
                    $ids = $logs->pluck('id')->toArray();
                    DB::table('attendance_logs')->whereIn('id', $ids)->delete();

                    $totalArchived += count($records);
                });
            });

        $this->info("Success! Archived {$totalArchived} records for SY {$startYear}-{$endYear}.");
    }
}
