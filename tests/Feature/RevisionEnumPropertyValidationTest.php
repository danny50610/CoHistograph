<?php

namespace Tests\Feature;

use App\Enums\RevisionActionType;
use App\Enums\RevisionStatus;
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
}
