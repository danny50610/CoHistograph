<?php

namespace Tests\Feature;

use App\Enums\RevisionStatus;
use App\Models\Revision;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
