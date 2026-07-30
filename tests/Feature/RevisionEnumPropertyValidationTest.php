<?php

namespace Tests\Feature;

use App\Enums\RevisionActionType;
use App\Enums\RevisionStatus;
use App\Models\EdgeProperty;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\RevisionAction;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Services\Revision\RevisionValidationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RevisionEnumPropertyValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_vertex_property_accepts_active_enum_values(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum()->create([
            'age_property_name' => 'genres',
        ]);

        $revision = Revision::create([
            'title' => 'ENUM create',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => ['jazz', 'rock'],
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->fresh('actions'));

        $this->assertTrue($result->isValid(), json_encode($result->actionMessages()));
    }

    public function test_create_vertex_property_rejects_inactive_enum_value(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => false],
        ])->create([
            'age_property_name' => 'genres',
        ]);

        $revision = Revision::create([
            'title' => 'ENUM inactive',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => ['jazz'],
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->fresh('actions'));

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->actionMessages()[1] ?? []);
    }

    public function test_create_vertex_property_rejects_empty_enum_array(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum()->create([
            'age_property_name' => 'genres',
        ]);

        $revision = new Revision([
            'title' => 'ENUM empty',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->setRelation('actions', collect([
            new RevisionAction([
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $vertexType->age_label_name,
            ]),
            new RevisionAction([
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => [],
            ]),
        ]));

        $result = app(RevisionValidationService::class)->validate($revision);

        $this->assertFalse($result->isValid());
    }

    public function test_create_vertex_property_rejects_below_min_selections(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ['value' => 'pop', 'label' => '流行', 'active' => true],
        ])->create([
            'age_property_name' => 'genres',
            'min_selections' => 2,
            'max_selections' => null,
        ]);

        $revision = Revision::create([
            'title' => 'ENUM below min',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => ['rock'],
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->fresh('actions'));

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->actionMessages()[1] ?? []);
    }

    public function test_create_vertex_property_rejects_above_max_selections(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        $property = VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ['value' => 'pop', 'label' => '流行', 'active' => true],
        ])->create([
            'age_property_name' => 'genres',
            'min_selections' => 1,
            'max_selections' => 2,
        ]);

        $revision = Revision::create([
            'title' => 'ENUM above max',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => $property->age_property_name,
                'value' => ['rock', 'jazz', 'pop'],
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->fresh('actions'));

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->actionMessages()[1] ?? []);
    }

    public function test_create_edge_property_accepts_active_enum_values(): void
    {
        $user = User::factory()->create();
        $start = VertexType::factory()->create(['age_label_name' => 'artist_enum_'.uniqid()]);
        $end = VertexType::factory()->create(['age_label_name' => 'track_enum_'.uniqid()]);
        $edgeType = EdgeType::factory()->create([
            'age_label_name' => 'performs_enum_'.uniqid(),
            'start_vertex_id' => $start->id,
            'end_vertex_id' => $end->id,
        ]);
        $property = EdgeProperty::factory()->for($edgeType)->enum()->create([
            'age_property_name' => 'roles',
        ]);

        $revision = Revision::create([
            'title' => 'ENUM edge create',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $start->age_label_name,
            ],
            [
                'order' => 1,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $end->age_label_name,
            ],
            [
                'order' => 2,
                'action' => RevisionActionType::CreateEdge,
                'edge_type_label' => $edgeType->age_label_name,
                'start_vertex_ref_order' => 0,
                'end_vertex_ref_order' => 1,
            ],
            [
                'order' => 3,
                'action' => RevisionActionType::CreateEdgeProperty,
                'target_ref_order' => 2,
                'age_property_name' => $property->age_property_name,
                'value' => ['jazz', 'rock'],
            ],
        ]);

        $result = app(RevisionValidationService::class)->validate($revision->fresh('actions'));

        $this->assertTrue($result->isValid(), json_encode($result->actionMessages()));
    }

    public function test_update_persists_enum_values_in_definition_order(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
            ['value' => 'pop', 'label' => '流行', 'active' => true],
        ])->create([
            'age_property_name' => 'genres',
        ]);

        $revision = Revision::create([
            'title' => 'ENUM normalize',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('revisions.update', $revision), [
                'title' => 'ENUM normalize',
                'description' => '',
                'actions' => [
                    [
                        'order' => 0,
                        'action' => RevisionActionType::CreateVertex->value,
                        'vertex_type_label' => $vertexType->age_label_name,
                    ],
                    [
                        'order' => 1,
                        'action' => RevisionActionType::CreateVertexProperty->value,
                        'target_ref_order' => 0,
                        'age_property_name' => 'genres',
                        'value' => ['pop', 'rock'],
                    ],
                ],
            ])
            ->assertRedirect(route('revisions.show', $revision));

        $action = $revision->fresh('actions')->actions->firstWhere('order', 1);
        $this->assertSame(['rock', 'pop'], $action->value);
    }

    public function test_show_displays_enum_diff_for_create_ref_property(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_enum_'.uniqid()]);
        VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => true],
        ])->create([
            'age_property_name' => 'genres',
        ]);

        $revision = Revision::create([
            'title' => 'ENUM show diff',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->createMany([
            [
                'order' => 0,
                'action' => RevisionActionType::CreateVertex,
                'vertex_type_label' => $vertexType->age_label_name,
            ],
            [
                'order' => 1,
                'action' => RevisionActionType::CreateVertexProperty,
                'target_ref_order' => 0,
                'age_property_name' => 'genres',
                'value' => ['jazz', 'rock'],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('revisions.show', $revision))
            ->assertOk()
            ->assertSee('現有：')
            ->assertSee('（無）')
            ->assertSee('此目標於本修訂新建，圖上尚無值')
            ->assertSee('新增：')
            ->assertSee('搖滾、爵士');
    }
}
