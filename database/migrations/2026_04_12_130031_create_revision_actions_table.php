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
        Schema::create('revision_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('revisions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('action');
            $table->unsignedBigInteger('target_age_id')->nullable();
            $table->unsignedInteger('target_ref_order')->nullable();
            $table->string('vertex_type_label')->nullable();
            $table->string('edge_type_label')->nullable();
            $table->unsignedBigInteger('start_vertex_age_id')->nullable();
            $table->unsignedInteger('start_vertex_ref_order')->nullable();
            $table->unsignedBigInteger('end_vertex_age_id')->nullable();
            $table->unsignedInteger('end_vertex_ref_order')->nullable();
            $table->string('age_property_name')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revision_actions');
    }
};
