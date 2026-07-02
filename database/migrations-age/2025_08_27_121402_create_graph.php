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
        DB::apacheAgeCreateGraph(config('cohistograph.app.graph.name'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::apacheAgeDropGraph(config('cohistograph.app.graph.name'), true);
    }
};
