<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_properties', function (Blueprint $table) {
            $table->json('enum_options')->nullable()->after('locale');
        });

        Schema::table('edge_properties', function (Blueprint $table) {
            $table->json('enum_options')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('vertex_properties', function (Blueprint $table) {
            $table->dropColumn('enum_options');
        });

        Schema::table('edge_properties', function (Blueprint $table) {
            $table->dropColumn('enum_options');
        });
    }
};
