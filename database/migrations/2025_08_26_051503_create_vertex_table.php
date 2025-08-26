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
        Schema::create('vertex_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->default('');
            $table->string('age_label_name')->unique();
            $table->timestamps();
        });

        Schema::create('vertex_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertex_type_id')->constrained('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->default('');
            $table->string('age_property_name');
            $table->string('age_property_type');
            $table->timestamps();
        });

        Schema::create('edge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->default('');
            $table->string('age_label_name')->unique();
            $table->foreignId('start_vertex_id')->constrained('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('end_vertex_id')->constrained('vertex_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['start_vertex_id', 'end_vertex_id']);
        });

        Schema::create('edge_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edge_type_id')->constrained('edge_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('description')->default('');
            $table->string('age_property_name');
            $table->string('age_property_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edge_properties');
        Schema::dropIfExists('edge_types');
        Schema::dropIfExists('vertex_properties');
        Schema::dropIfExists('vertex_types');
    }
};
