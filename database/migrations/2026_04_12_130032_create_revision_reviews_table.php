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
        Schema::create('revision_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('revisions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('action');
            $table->text('comment')->nullable();
            $table->jsonb('actions_snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revision_reviews');
    }
};
