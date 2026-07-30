<?php

use App\Enums\PropertyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vertex_properties', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_selections')->nullable()->after('enum_options');
            $table->unsignedTinyInteger('max_selections')->nullable()->after('min_selections');
        });

        Schema::table('edge_properties', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_selections')->nullable()->after('enum_options');
            $table->unsignedTinyInteger('max_selections')->nullable()->after('min_selections');
        });

        DB::table('vertex_properties')
            ->where('age_property_type', PropertyType::Enum->value)
            ->whereNull('min_selections')
            ->update(['min_selections' => 1]);

        DB::table('edge_properties')
            ->where('age_property_type', PropertyType::Enum->value)
            ->whereNull('min_selections')
            ->update(['min_selections' => 1]);
    }

    public function down(): void
    {
        Schema::table('vertex_properties', function (Blueprint $table) {
            $table->dropColumn(['min_selections', 'max_selections']);
        });

        Schema::table('edge_properties', function (Blueprint $table) {
            $table->dropColumn(['min_selections', 'max_selections']);
        });
    }
};
