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
        Schema::table('vertex_types', function (Blueprint $table) {
            $table->text('usage_guidelines')->nullable()->after('description');
        });

        Schema::table('edge_types', function (Blueprint $table) {
            $table->text('usage_guidelines')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vertex_types', function (Blueprint $table) {
            $table->dropColumn('usage_guidelines');
        });

        Schema::table('edge_types', function (Blueprint $table) {
            $table->dropColumn('usage_guidelines');
        });
    }
};
