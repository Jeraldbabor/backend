<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL automatically creates a check constraint for enum columns mapping to string values
        // when using Laravel's original migration.
        // Even though we changed the type to VARCHAR, the check constraint remains and prevents
        // inserting new roles like 'teacher' and 'principal'.
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add it back in case of rollback
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['superadmin'::character varying, 'admin'::character varying, 'parent'::character varying, 'student'::character varying]::text[]))");
    }
};
