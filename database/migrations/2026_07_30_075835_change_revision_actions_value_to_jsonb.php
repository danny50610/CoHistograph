<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE revision_actions ALTER COLUMN value TYPE jsonb USING to_jsonb(value)');

            return;
        }

        // SQLite / others: recreate as json (Laravel json maps appropriately).
        Schema::table('revision_actions', function (Blueprint $table) {
            $table->json('value_json')->nullable();
        });

        DB::table('revision_actions')->orderBy('id')->each(function (object $row): void {
            if ($row->value === null) {
                return;
            }

            DB::table('revision_actions')->where('id', $row->id)->update([
                'value_json' => json_encode($row->value, JSON_THROW_ON_ERROR),
            ]);
        });

        Schema::table('revision_actions', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('revision_actions', function (Blueprint $table) {
            $table->renameColumn('value_json', 'value');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE revision_actions ALTER COLUMN value TYPE text USING value::text');

            return;
        }

        Schema::table('revision_actions', function (Blueprint $table) {
            $table->text('value_text')->nullable();
        });

        DB::table('revision_actions')->orderBy('id')->each(function (object $row): void {
            if ($row->value === null) {
                return;
            }

            $decoded = json_decode((string) $row->value, true);
            $asText = is_string($decoded) ? $decoded : (string) $row->value;

            DB::table('revision_actions')->where('id', $row->id)->update([
                'value_text' => $asText,
            ]);
        });

        Schema::table('revision_actions', function (Blueprint $table) {
            $table->dropColumn('value');
        });

        Schema::table('revision_actions', function (Blueprint $table) {
            $table->renameColumn('value_text', 'value');
        });
    }
};
