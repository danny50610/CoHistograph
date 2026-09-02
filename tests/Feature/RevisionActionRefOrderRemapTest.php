<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Services\RevisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionActionRefOrderRemapTest extends TestCase
{
    use DatabaseTransactions;

    private string $graphConnection;

    private string $graphName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphConnection = (string) config('cohistograph.app.graph.connection-name');
        $this->graphName = (string) config('cohistograph.app.graph.name');

        $connection = DB::connection($this->graphConnection);
        if (! $connection->apacheAgeHasGraph($this->graphName)) {
            $connection->apacheAgeCreateGraph($this->graphName);
        }
    }

    public function test_delete_preceding_action_remaps_later_ref_orders(): void
    {
        [$revision, $actions] = $this->createRevisionWithTwoVerticesAndProperty();
        $service = app(RevisionService::class);

        $service->deleteAction($revision, $actions[0]);

        $remaining = $revision->fresh()->actions()->orderBy('order')->get();

        $this->assertCount(2, $remaining);
        $this->assertSame('create_vertex', $remaining[0]->action->value);
        $this->assertSame('create_vertex_property', $remaining[1]->action->value);
        $this->assertSame(0, $remaining[1]->target_ref_order);
        $this->assertTrue((bool) $revision->fresh()->last_validation_is_valid);
    }

    public function test_delete_referenced_action_clears_ref_orders(): void
    {
        [$revision, $actions] = $this->createRevisionWithTwoVerticesAndProperty();
        $service = app(RevisionService::class);

        $service->deleteAction($revision, $actions[1]);

        $remaining = $revision->fresh()->actions()->orderBy('order')->get();

        $this->assertCount(2, $remaining);
        $this->assertSame('create_vertex', $remaining[0]->action->value);
        $this->assertSame('create_vertex_property', $remaining[1]->action->value);
        $this->assertNull($remaining[1]->target_ref_order);
        $this->assertFalse((bool) $revision->fresh()->last_validation_is_valid);
    }

    public function test_move_action_remaps_ref_orders_onto_the_same_targets(): void
    {
        [$revision, $actions] = $this->createRevisionWithTwoVerticesAndProperty();
        $service = app(RevisionService::class);

        $service->moveAction($revision, $actions[0], 2);

        $reordered = $revision->fresh()->actions()->orderBy('order')->get();

        $this->assertSame('create_vertex', $reordered[0]->action->value);
        $this->assertSame('create_vertex_property', $reordered[1]->action->value);
        $this->assertSame('create_vertex', $reordered[2]->action->value);
        $this->assertSame(0, $reordered[1]->target_ref_order);
        $this->assertTrue((bool) $revision->fresh()->last_validation_is_valid);
    }

    public function test_move_create_after_dependent_action_keeps_ref_but_fails_validation(): void
    {
        [$revision, $actions] = $this->createRevisionWithTwoVerticesAndProperty();
        $service = app(RevisionService::class);

        $service->moveAction($revision, $actions[1], 2);

        $reordered = $revision->fresh()->actions()->orderBy('order')->get();

        $this->assertSame('create_vertex', $reordered[0]->action->value);
        $this->assertSame('create_vertex_property', $reordered[1]->action->value);
        $this->assertSame('create_vertex', $reordered[2]->action->value);
        $this->assertSame(2, $reordered[1]->target_ref_order);
        $this->assertFalse((bool) $revision->fresh()->last_validation_is_valid);
    }

    public function test_insert_action_remaps_existing_and_incoming_ref_orders(): void
    {
        [$revision, , $vertexType] = $this->createRevisionWithTwoVerticesAndProperty();
        $service = app(RevisionService::class);

        $service->addAction($revision, 0, [
            'action' => 'create_vertex',
            'vertex_type_label' => $vertexType->age_label_name,
        ]);

        $actions = $revision->fresh()->actions()->orderBy('order')->get();

        $this->assertCount(4, $actions);
        $this->assertSame('create_vertex', $actions[0]->action->value);
        $this->assertSame('create_vertex', $actions[1]->action->value);
        $this->assertSame('create_vertex', $actions[2]->action->value);
        $this->assertSame('create_vertex_property', $actions[3]->action->value);
        $this->assertSame(2, $actions[3]->target_ref_order);
        $this->assertTrue((bool) $revision->fresh()->last_validation_is_valid);
    }

    public function test_insert_action_incoming_refs_use_pre_insert_numbering(): void
    {
        [$revision] = $this->createRevisionWithTwoVerticesAndProperty();
        $propertyName = $revision->actions()->orderBy('order')->get()[2]->age_property_name;
        $service = app(RevisionService::class);

        $inserted = $service->addAction($revision, 0, [
            'action' => 'create_vertex_property',
            'target_ref_order' => 1,
            'age_property_name' => $propertyName,
            'value' => 'also named',
        ]);

        $this->assertSame(2, $inserted->target_ref_order);

        $actions = $revision->fresh()->actions()->orderBy('order')->get();
        $this->assertSame(2, $actions[3]->target_ref_order);
    }

    /**
     * @return array{0: Revision, 1: \Illuminate\Support\Collection<int, \App\Models\RevisionAction>, 2: VertexType}
     */
    private function createRevisionWithTwoVerticesAndProperty(): array
    {
        $user = User::factory()->createOne();
        $vertexType = VertexType::factory()->createOne([
            'age_label_name' => 'label_'.fake()->unique()->lexify('??????'),
        ]);
        $property = VertexProperty::factory()->createOne([
            'vertex_type_id' => $vertexType->id,
            'age_property_name' => 'p_'.fake()->unique()->lexify('????????'),
            'age_property_type' => PropertyType::String,
        ]);

        $revision = Revision::query()->create([
            'title' => 'ref remap',
            'description' => null,
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 1,
                'action' => 'create_vertex',
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 2,
                'action' => 'create_vertex_property',
                'target_ref_order' => 1,
                'age_property_name' => $property->age_property_name,
                'value' => 'named',
            ],
        ]);

        return [$revision->fresh(), $revision->actions()->orderBy('order')->get(), $vertexType];
    }
}
