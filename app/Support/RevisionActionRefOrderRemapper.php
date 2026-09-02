<?php

namespace App\Support;

/**
 * Remaps *_ref_order fields when revision actions are inserted, moved, or deleted.
 *
 * @phpstan-type OrderMapping array<int, int|null>
 */
class RevisionActionRefOrderRemapper
{
    /**
     * @var list<string>
     */
    public const REF_FIELDS = [
        'target_ref_order',
        'start_vertex_ref_order',
        'end_vertex_ref_order',
    ];

    /**
     * @return OrderMapping
     */
    public function mappingForDelete(int $count, int $deleted): array
    {
        $mapping = [];

        for ($i = 0; $i < $count; $i++) {
            if ($i === $deleted) {
                $mapping[$i] = null;
            } elseif ($i > $deleted) {
                $mapping[$i] = $i - 1;
            } else {
                $mapping[$i] = $i;
            }
        }

        return $mapping;
    }

    /**
     * @return OrderMapping
     */
    public function mappingForInsert(int $countBeforeInsert, int $insertAt): array
    {
        $insertAt = max(0, min($insertAt, $countBeforeInsert));
        $mapping = [];

        for ($i = 0; $i < $countBeforeInsert; $i++) {
            $mapping[$i] = $i >= $insertAt ? $i + 1 : $i;
        }

        return $mapping;
    }

    /**
     * @return OrderMapping
     */
    public function mappingForMove(int $count, int $from, int $to): array
    {
        $mapping = [];

        if ($count <= 0) {
            return $mapping;
        }

        $from = max(0, min($from, $count - 1));
        $to = max(0, min($to, $count - 1));
        $oldOrder = range(0, $count - 1);
        $moved = array_splice($oldOrder, $from, 1);
        array_splice($oldOrder, $to, 0, $moved);

        foreach ($oldOrder as $newIndex => $oldIndex) {
            $mapping[$oldIndex] = $newIndex;
        }

        ksort($mapping);

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $action
     * @param  OrderMapping  $oldToNew
     * @return array<string, mixed>
     */
    public function remapAction(array $action, array $oldToNew): array
    {
        foreach (self::REF_FIELDS as $field) {
            if (! array_key_exists($field, $action) || $action[$field] === null || $action[$field] === '') {
                continue;
            }

            $action[$field] = $oldToNew[(int) $action[$field]] ?? null;
        }

        return $action;
    }
}
