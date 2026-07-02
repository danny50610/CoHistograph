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
        DB::table('vertex_properties')
            ->where('age_property_type', 'NUMERIC')
            ->update(['age_property_type' => 'FLOAT']);

        DB::table('edge_properties')
            ->where('age_property_type', 'NUMERIC')
            ->update(['age_property_type' => 'FLOAT']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot distinguish rows migrated from NUMERIC vs originally FLOAT.
    }
};
