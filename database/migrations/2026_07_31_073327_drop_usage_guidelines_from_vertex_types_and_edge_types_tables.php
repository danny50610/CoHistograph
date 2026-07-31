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
            if (Schema::hasColumn('vertex_types', 'usage_guidelines')) {
                $table->dropColumn('usage_guidelines');
            }
        });

        Schema::table('edge_types', function (Blueprint $table) {
            if (Schema::hasColumn('edge_types', 'usage_guidelines')) {
                $table->dropColumn('usage_guidelines');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vertex_types', function (Blueprint $table) {
            if (! Schema::hasColumn('vertex_types', 'usage_guidelines')) {
                $table->text('usage_guidelines')->nullable()->after('description');
            }
        });

        Schema::table('edge_types', function (Blueprint $table) {
            if (! Schema::hasColumn('edge_types', 'usage_guidelines')) {
                $table->text('usage_guidelines')->nullable()->after('description');
            }
        });
    }
};
