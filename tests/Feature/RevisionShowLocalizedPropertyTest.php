<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Enums\RevisionStatus;
use App\Models\EdgeType;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RevisionShowLocalizedPropertyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_show_displays_localized_property_label_in_action_summary(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'person_label']);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $revision = Revision::query()->create([
            'title' => 'Localized property revision',
            'description' => '',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $revision->actions()->create([
            'order' => 0,
            'action' => 'create_vertex',
            'vertex_type_label' => 'person_label',
        ]);
        $revision->actions()->create([
            'order' => 1,
            'action' => 'create_vertex_property',
            'target_ref_order' => 0,
            'age_property_name' => 'name_zh_tw',
            'value' => '李白',
        ]);

        $this->actingAs($user)
            ->get(route('revisions.show', $revision))
            ->assertOk()
            ->assertSee('name_zh_tw（繁體中文）')
            ->assertSee('李白');
    }

    public function test_edit_includes_graph_locales_for_property_forms(): void
    {
        $user = User::factory()->create();
        $revision = Revision::query()->create([
            'title' => 'Draft revision',
            'description' => '',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('revisions.edit', $revision))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Revisions/Edit')
                ->where('graphLocales', config('cohistograph.app.graph.locales'))
            );
    }

<<<<<<< HEAD
    public function test_edit_includes_edge_types_with_snake_case_vertex_relations(): void
    {
        $user = User::factory()->create();
        $startVertex = VertexType::factory()->create(['name' => 'Artist']);
        $endVertex = VertexType::factory()->create(['name' => 'Track']);
        $edgeType = EdgeType::factory()->create([
            'name' => 'performs',
            'start_vertex_id' => $startVertex->id,
            'end_vertex_id' => $endVertex->id,
        ]);

        $revision = Revision::query()->create([
            'title' => 'Draft with edge types',
=======
    public function test_edit_exposes_property_types_for_value_inputs(): void
    {
        $user = User::factory()->create();
        $vertexType = VertexType::factory()->create(['age_label_name' => 'event_label']);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'occurred_on',
            'age_property_type' => PropertyType::Date,
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'anniversary',
            'age_property_type' => PropertyType::MonthDay,
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'recorded_at',
            'age_property_type' => PropertyType::Timestamptz,
        ]);

        $revision = Revision::query()->create([
            'title' => 'Draft with typed properties',
>>>>>>> 8315815 (feat: 修訂編輯依屬性型別切換 value 輸入元件)
            'description' => '',
            'status' => RevisionStatus::Draft,
            'user_id' => $user->id,
        ]);

<<<<<<< HEAD
        $response = $this->actingAs($user)
=======
        $this->actingAs($user)
>>>>>>> 8315815 (feat: 修訂編輯依屬性型別切換 value 輸入元件)
            ->get(route('revisions.edit', $revision))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Revisions/Edit')
<<<<<<< HEAD
                ->has('edgeTypes')
            );

        $matched = collect($response->inertiaProps('edgeTypes'))
            ->firstWhere('id', $edgeType->id);

        $this->assertNotNull($matched);
        $this->assertSame('Artist', $matched['start_vertex']['name'] ?? null);
        $this->assertSame('Track', $matched['end_vertex']['name'] ?? null);
        $this->assertArrayNotHasKey('startVertex', $matched);
        $this->assertArrayNotHasKey('endVertex', $matched);
=======
                ->where('vertexTypes', function (mixed $vertexTypes) use ($vertexType) {
                    /** @var Collection<int, array<string, mixed>> $types */
                    $types = collect($vertexTypes);
                    $target = $types->firstWhere('id', $vertexType->id);
                    $byName = collect($target['properties'] ?? [])->keyBy('age_property_name');

                    return ($byName['occurred_on']['age_property_type'] ?? null) === PropertyType::Date->value
                        && ($byName['anniversary']['age_property_type'] ?? null) === PropertyType::MonthDay->value
                        && ($byName['recorded_at']['age_property_type'] ?? null) === PropertyType::Timestamptz->value;
                })
            );
>>>>>>> 8315815 (feat: 修訂編輯依屬性型別切換 value 輸入元件)
    }
}
