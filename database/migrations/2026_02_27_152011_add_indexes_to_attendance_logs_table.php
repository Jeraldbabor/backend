<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Add indexes to improve query performance, especially for archiving
            $table->index('student_id');
            $table->index('created_at');
            $table->index('school_id'); // Replaced gate_id with school_id to match current DB design
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['student_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['school_id']);
        });
    }
};
