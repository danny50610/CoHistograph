<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * B1: revision_actions.value text → jsonb.
     * New baseline wraps every text value as a JSON string, then best-effort promotes
     * INTEGER / FLOAT / BOOLEAN to native JSON scalars when the property schema agrees.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE revision_actions ALTER COLUMN value TYPE jsonb USING to_jsonb(value)');

        $this->promoteVertexPropertyScalars();
        $this->promoteEdgePropertyScalars();
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE revision_actions
            ALTER COLUMN value TYPE text
            USING CASE
                WHEN value IS NULL THEN NULL
                WHEN jsonb_typeof(value) = 'string' THEN value #>> '{}'
                WHEN jsonb_typeof(value) IN ('number', 'boolean') THEN value #>> '{}'
                ELSE value::text
            END
        SQL);
    }

    private function promoteVertexPropertyScalars(): void
    {
        $actions = "('create_vertex_property', 'update_vertex_property')";

        DB::statement(<<<SQL
            UPDATE revision_actions AS ra
            SET value = to_jsonb((ra.value #>> '{}')::bigint)
            WHERE ra.value IS NOT NULL
              AND jsonb_typeof(ra.value) = 'string'
              AND ra.action IN {$actions}
              AND (ra.value #>> '{}') ~ '^-?\\d+$'
              AND {$this->uniquePropertyTypeSql('vertex_properties', 'INTEGER')}
        SQL);

        DB::statement(<<<SQL
            UPDATE revision_actions AS ra
            SET value = to_jsonb((ra.value #>> '{}')::double precision)
            WHERE ra.value IS NOT NULL
              AND jsonb_typeof(ra.value) = 'string'
              AND ra.action IN {$actions}
              AND (ra.value #>> '{}') ~ '^-?(?:\\d+|\\d*\\.\\d+)$'
              AND {$this->uniquePropertyTypeSql('vertex_properties', 'FLOAT')}
        SQL);

        DB::statement(<<<SQL
            UPDATE revision_actions AS ra
            SET value = to_jsonb(lower(ra.value #>> '{}') = 'true')
            WHERE ra.value IS NOT NULL
              AND jsonb_typeof(ra.value) = 'string'
              AND ra.action IN {$actions}
              AND lower(ra.value #>> '{}') IN ('true', 'false')
              AND {$this->uniquePropertyTypeSql('vertex_properties', 'BOOLEAN')}
        SQL);
    }

    private function promoteEdgePropertyScalars(): void
    {
        $actions = "('create_edge_property', 'update_edge_property')";

        DB::statement(<<<SQL
            UPDATE revision_actions AS ra
            SET value = to_jsonb((ra.value #>> '{}')::bigint)
            WHERE ra.value IS NOT NULL
              AND jsonb_typeof(ra.value) = 'string'
              AND ra.action IN {$actions}
              AND (ra.value #>> '{}') ~ '^-?\\d+$'
              AND {$this->uniquePropertyTypeSql('edge_properties', 'INTEGER')}
        SQL);

        DB::statement(<<<SQL
            UPDATE revision_actions AS ra
            SET value = to_jsonb((ra.value #>> '{}')::double precision)
            WHERE ra.value IS NOT NULL
              AND jsonb_typeof(ra.value) = 'string'
              AND ra.action IN {$actions}
              AND (ra.value #>> '{}') ~ '^-?(?:\\d+|\\d*\\.\\d+)$'
              AND {$this->uniquePropertyTypeSql('edge_properties', 'FLOAT')}
        SQL);

        DB::statement(<<<SQL
            UPDATE revision_actions AS ra
            SET value = to_jsonb(lower(ra.value #>> '{}') = 'true')
            WHERE ra.value IS NOT NULL
              AND jsonb_typeof(ra.value) = 'string'
              AND ra.action IN {$actions}
              AND lower(ra.value #>> '{}') IN ('true', 'false')
              AND {$this->uniquePropertyTypeSql('edge_properties', 'BOOLEAN')}
        SQL);
    }

    /**
     * Only promote when every schema row sharing this age_property_name agrees on $type.
     */
    private function uniquePropertyTypeSql(string $table, string $type): string
    {
        return <<<SQL
            (
                SELECT COUNT(DISTINCT p.age_property_type)
                FROM {$table} AS p
                WHERE p.age_property_name = ra.age_property_name
            ) = 1
            AND EXISTS (
                SELECT 1
                FROM {$table} AS p
                WHERE p.age_property_name = ra.age_property_name
                  AND p.age_property_type = '{$type}'
            )
        SQL;
    }
};
