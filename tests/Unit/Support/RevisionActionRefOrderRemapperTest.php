<?php

namespace Tests\Unit\Support;

use App\Support\RevisionActionRefOrderRemapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RevisionActionRefOrderRemapperTest extends TestCase
{
    private RevisionActionRefOrderRemapper $remapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->remapper = new RevisionActionRefOrderRemapper;
    }

    public function test_mapping_for_delete_shifts_later_orders_and_nulls_deleted(): void
    {
        $this->assertSame([
            0 => 0,
            1 => null,
            2 => 1,
            3 => 2,
        ], $this->remapper->mappingForDelete(4, 1));
    }

    public function test_mapping_for_insert_shifts_orders_at_or_after_insert_point(): void
    {
        $this->assertSame([
            0 => 0,
            1 => 2,
            2 => 3,
        ], $this->remapper->mappingForInsert(3, 1));

        $this->assertSame([
            0 => 1,
            1 => 2,
        ], $this->remapper->mappingForInsert(2, 0));

        $this->assertSame([
            0 => 0,
            1 => 1,
        ], $this->remapper->mappingForInsert(2, 2));
    }

    #[DataProvider('moveMappingProvider')]
    public function test_mapping_for_move(int $count, int $from, int $to, array $expected): void
    {
        $this->assertSame($expected, $this->remapper->mappingForMove($count, $from, $to));
    }

    /**
     * @return array<string, array{int, int, int, array<int, int>}>
     */
    public static function moveMappingProvider(): array
    {
        return [
            'move later' => [4, 1, 3, [0 => 0, 1 => 3, 2 => 1, 3 => 2]],
            'move earlier' => [4, 3, 1, [0 => 0, 1 => 2, 2 => 3, 3 => 1]],
            'adjacent swap down' => [3, 0, 1, [0 => 1, 1 => 0, 2 => 2]],
            'no-op' => [3, 1, 1, [0 => 0, 1 => 1, 2 => 2]],
        ];
    }

    public function test_remap_action_updates_all_ref_fields(): void
    {
        $mapping = $this->remapper->mappingForDelete(3, 0);

        $remapped = $this->remapper->remapAction([
            'target_ref_order' => 1,
            'start_vertex_ref_order' => 2,
            'end_vertex_ref_order' => 0,
        ], $mapping);

        $this->assertSame(0, $remapped['target_ref_order']);
        $this->assertSame(1, $remapped['start_vertex_ref_order']);
        $this->assertNull($remapped['end_vertex_ref_order']);
    }

    public function test_remap_action_clears_refs_to_deleted_order(): void
    {
        $mapping = $this->remapper->mappingForDelete(3, 1);

        $remapped = $this->remapper->remapAction([
            'target_ref_order' => 1,
            'start_vertex_ref_order' => null,
            'end_vertex_ref_order' => '',
        ], $mapping);

        $this->assertNull($remapped['target_ref_order']);
        $this->assertNull($remapped['start_vertex_ref_order']);
        $this->assertSame('', $remapped['end_vertex_ref_order']);
    }

    public function test_remap_action_keeps_zero_ref_after_move(): void
    {
        $mapping = $this->remapper->mappingForMove(3, 1, 0);

        $remapped = $this->remapper->remapAction([
            'target_ref_order' => 1,
        ], $mapping);

        $this->assertSame(0, $remapped['target_ref_order']);
    }
}
