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
        Schema::table('revisions', function (Blueprint $table) {
            $table->boolean('last_validation_is_valid')->nullable()->after('status');
            $table->string('last_validation_summary')->nullable()->after('last_validation_is_valid');
            $table->json('last_validation_general_errors')->nullable()->after('last_validation_summary');
            $table->json('last_validation_action_errors')->nullable()->after('last_validation_general_errors');
            $table->timestamp('last_validated_at')->nullable()->after('last_validation_action_errors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revisions', function (Blueprint $table) {
            $table->dropColumn([
                'last_validation_is_valid',
                'last_validation_summary',
                'last_validation_general_errors',
                'last_validation_action_errors',
                'last_validated_at',
            ]);
        });
    }
};
