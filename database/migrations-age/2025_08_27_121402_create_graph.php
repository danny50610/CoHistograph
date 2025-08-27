<?php

use App\Facades\ApacheAge;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ApacheAge::createGraph(config('cohistograph.app.graph.name'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ApacheAge::dropGraph(config('cohistograph.app.graph.name'), true);
    }
};
