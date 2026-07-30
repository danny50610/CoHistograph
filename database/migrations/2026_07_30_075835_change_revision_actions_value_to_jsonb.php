<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE revision_actions ALTER COLUMN value TYPE jsonb USING to_jsonb(value)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE revision_actions ALTER COLUMN value TYPE text USING value::text');
    }
};
