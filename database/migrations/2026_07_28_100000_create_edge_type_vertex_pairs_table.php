<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('edge_type_vertex_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edge_type_id')->constrained('edge_types')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('start_vertex_id')->constrained('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('end_vertex_id')->constrained('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['edge_type_id', 'start_vertex_id', 'end_vertex_id'], 'edge_type_vertex_pairs_unique');
            $table->index('start_vertex_id');
            $table->index('end_vertex_id');
        });

        $edgeTypes = DB::table('edge_types')->select(['id', 'start_vertex_id', 'end_vertex_id', 'created_at', 'updated_at'])->get();

        foreach ($edgeTypes as $edgeType) {
            DB::table('edge_type_vertex_pairs')->insert([
                'edge_type_id' => $edgeType->id,
                'start_vertex_id' => $edgeType->start_vertex_id,
                'end_vertex_id' => $edgeType->end_vertex_id,
                'created_at' => $edgeType->created_at,
                'updated_at' => $edgeType->updated_at,
            ]);
        }

        Schema::table('edge_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('start_vertex_id');
            $table->dropConstrainedForeignId('end_vertex_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edge_types', function (Blueprint $table) {
            $table->unsignedBigInteger('start_vertex_id')->nullable()->after('age_label_name');
            $table->unsignedBigInteger('end_vertex_id')->nullable()->after('start_vertex_id');
        });

        $pairs = DB::table('edge_type_vertex_pairs')
            ->select(['edge_type_id', 'start_vertex_id', 'end_vertex_id'])
            ->orderBy('id')
            ->get()
            ->groupBy('edge_type_id');

        foreach ($pairs as $edgeTypeId => $edgePairs) {
            $first = $edgePairs->first();

            DB::table('edge_types')
                ->where('id', $edgeTypeId)
                ->update([
                    'start_vertex_id' => $first->start_vertex_id,
                    'end_vertex_id' => $first->end_vertex_id,
                ]);
        }

        Schema::table('edge_types', function (Blueprint $table) {
            $table->foreign('start_vertex_id')->references('id')->on('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('end_vertex_id')->references('id')->on('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::dropIfExists('edge_type_vertex_pairs');
    }
};
