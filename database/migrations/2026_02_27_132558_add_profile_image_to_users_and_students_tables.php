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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_image')->nullable()->after('password');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('profile_image')->nullable()->after('student_id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });
    }
};
