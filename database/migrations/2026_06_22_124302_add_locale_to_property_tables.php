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
        Schema::table('vertex_properties', function (Blueprint $table) {
            $table->string('locale')->nullable()->after('age_property_type');
        });

        Schema::table('edge_properties', function (Blueprint $table) {
            $table->string('locale')->nullable()->after('age_property_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vertex_properties', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('edge_properties', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
