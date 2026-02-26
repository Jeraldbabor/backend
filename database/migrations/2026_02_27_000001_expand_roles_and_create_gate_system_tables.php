<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Expands user roles and creates core gate system tables:
     * - students: enrolled students with RFID codes
     * - teachers: teacher grade/section assignments
     * - attendance_logs: RFID scan records
     * - notifications: push notification records for mobile app
     */
    public function up(): void
    {
        // --- 1. Expand user roles to include teacher and principal ---
        // PostgreSQL: drop the old enum constraint and change column to varchar
        // This is needed because PG enums are strict types
        DB::statement('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(50)');
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'student'");

        // --- 2. Create students table ---
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('grade');
            $table->string('section');
            $table->string('rfid_code')->unique()->nullable();
            $table->string('student_id_number')->unique();
            $table->timestamps();

            $table->index(['school_id', 'grade', 'section']);
            $table->index('rfid_code');
        });

        // --- 3. Create teachers table (grade/section assignments) ---
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('grade');
            $table->string('section');
            $table->timestamps();

            $table->unique(['user_id', 'grade', 'section']);
            $table->index(['school_id', 'grade', 'section']);
        });

        // --- 4. Create attendance_logs table ---
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('rfid_code');
            $table->timestamp('scanned_at');
            $table->string('direction', 10)->default('in'); // 'in' or 'out'
            $table->timestamps();

            $table->index(['school_id', 'scanned_at']);
            $table->index(['student_id', 'scanned_at']);
        });

        // --- 5. Create notifications table ---
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('students');

        // Note: reverting VARCHAR back to enum is complex in PostgreSQL
        // Manual intervention may be needed if rolling back
    }
};
