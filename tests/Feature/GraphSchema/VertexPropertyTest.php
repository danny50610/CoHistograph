<?php

namespace Tests\Feature\GraphSchema;

use App\Enums\PropertyType;
use App\Models\User;
use App\Models\VertexProperty;
use App\Models\VertexType;
use Danny50610\LaravelApacheAgeDriver\Query\Builder as AgeQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VertexPropertyTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->addRole('admin');
    }

    public function test_store_success()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Full Name',
                'description' => 'The full name of the person',
                'age_property_name' => 'full_name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = VertexProperty::where('vertex_type_id', $vertexType->id)
            ->where('name', 'Full Name')
            ->first();
        $this->assertNotNull($property);
        $this->assertEquals('full_name', $property->age_property_name);
        $this->assertEquals(PropertyType::String, $property->age_property_type);
    }

    public function test_store_enum_property_success(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Genres',
                'description' => '',
                'age_property_name' => 'genres',
                'age_property_type' => PropertyType::Enum->value,
                'enum_options' => [
                    ['value' => 'rock', 'label' => '搖滾', 'active' => '1'],
                    ['value' => 'jazz', 'label' => '爵士', 'active' => '0'],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = VertexProperty::where('age_property_name', 'genres')->firstOrFail();
        $this->assertEquals(PropertyType::Enum, $property->age_property_type);
        $this->assertNull($property->locale);
        $this->assertSame(
            [
                ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ['value' => 'jazz', 'label' => '爵士', 'active' => false],
            ],
            $property->enum_options,
        );
        $this->assertSame(1, $property->min_selections);
        $this->assertNull($property->max_selections);
    }

    public function test_store_enum_with_selection_limits(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Genres',
                'description' => '',
                'age_property_name' => 'genres',
                'age_property_type' => PropertyType::Enum->value,
                'min_selections' => 2,
                'max_selections' => 3,
                'enum_options' => [
                    ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                    ['value' => 'jazz', 'label' => '爵士', 'active' => true],
                    ['value' => 'pop', 'label' => '流行', 'active' => true],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = VertexProperty::where('age_property_name', 'genres')->firstOrFail();
        $this->assertSame(2, $property->min_selections);
        $this->assertSame(3, $property->max_selections);
    }

    public function test_store_enum_rejects_when_active_options_below_min(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Genres',
                'description' => '',
                'age_property_name' => 'genres',
                'age_property_type' => PropertyType::Enum->value,
                'min_selections' => 2,
                'enum_options' => [
                    ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                    ['value' => 'jazz', 'label' => '爵士', 'active' => false],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['enum_options']);
    }

    public function test_store_enum_rejects_max_less_than_min(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Genres',
                'description' => '',
                'age_property_name' => 'genres',
                'age_property_type' => PropertyType::Enum->value,
                'min_selections' => 3,
                'max_selections' => 2,
                'enum_options' => [
                    ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                    ['value' => 'jazz', 'label' => '爵士', 'active' => true],
                    ['value' => 'pop', 'label' => '流行', 'active' => true],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['max_selections']);
    }

    public function test_store_enum_rejects_locale(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Genres',
                'description' => '',
                'locale' => 'zh_tw',
                'base_age_property_name' => 'genres',
                'age_property_type' => PropertyType::Enum->value,
                'enum_options' => [
                    ['value' => 'rock', 'label' => '搖滾', 'active' => true],
                ],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['locale']);
    }

    public function test_update_enum_options_success(): void
    {
        $this->mock(\App\Support\AgePropertyDataChecker::class, function ($mock): void {
            $mock->shouldReceive('vertexPropertyHasData')->andReturn(false);
            $mock->shouldReceive('vertexPropertyEnumValueInUse')->andReturn(false);
            $mock->shouldReceive('usedVertexEnumValues')->andReturn([]);
        });

        $vertexType = VertexType::factory()->create();
        $property = VertexProperty::factory()->for($vertexType)->enum()->create([
            'name' => 'Genres',
            'age_property_name' => 'genres',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$property->id}", [
                'name' => 'Genres',
                'description' => '',
                'age_property_name' => 'genres',
                'age_property_type' => PropertyType::Enum->value,
                'enum_options' => [
                    ['value' => 'rock', 'label' => '搖滾樂', 'active' => true],
                    ['value' => 'jazz', 'label' => '爵士', 'active' => true],
                    ['value' => 'pop', 'label' => '流行', 'active' => true],
                ],
                'min_selections' => 1,
                'max_selections' => 2,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property->refresh();
        $this->assertCount(3, $property->enum_options);
        $this->assertSame('搖滾樂', $property->enum_options[0]['label']);
        $this->assertSame(1, $property->min_selections);
        $this->assertSame(2, $property->max_selections);
    }

    public function test_store_date_and_timestamptz_types_success()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Occurred On',
                'description' => '',
                'age_property_name' => 'occurred_on',
                'age_property_type' => PropertyType::Date->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Anniversary',
                'description' => '',
                'age_property_name' => 'anniversary',
                'age_property_type' => PropertyType::MonthDay->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Recorded At',
                'description' => '',
                'age_property_name' => 'recorded_at',
                'age_property_type' => PropertyType::Timestamptz->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertEquals(
            PropertyType::Date,
            VertexProperty::where('age_property_name', 'occurred_on')->firstOrFail()->age_property_type,
        );
        $this->assertEquals(
            PropertyType::MonthDay,
            VertexProperty::where('age_property_name', 'anniversary')->firstOrFail()->age_property_type,
        );
        $this->assertEquals(
            PropertyType::Timestamptz,
            VertexProperty::where('age_property_name', 'recorded_at')->firstOrFail()->age_property_type,
        );
    }

    public function test_store_fail_when_name_not_unique_within_vertex_type()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create(['name' => 'Full Name']);

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Full Name',
                'description' => '',
                'age_property_name' => 'another_prop',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertCount(1, VertexProperty::where('vertex_type_id', $vertexType->id)->get());
    }

    public function test_store_fail_when_age_property_name_not_unique_within_vertex_type()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create(['age_property_name' => 'full_name']);

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Another Name',
                'description' => '',
                'age_property_name' => 'full_name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['resolved_age_property_name']);

        $this->assertCount(1, VertexProperty::where('vertex_type_id', $vertexType->id)->get());
    }

    public function test_store_success_with_localized_property()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => '姓名',
                'description' => '',
                'locale' => 'zh_tw',
                'base_age_property_name' => 'name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = VertexProperty::where('vertex_type_id', $vertexType->id)
            ->where('age_property_name', 'name_zh_tw')
            ->first();
        $this->assertNotNull($property);
        $this->assertEquals('zh_tw', $property->locale);
        $this->assertEquals('name_zh_tw', $property->age_property_name);
    }

    public function test_store_normalizes_empty_locale_to_null()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Birth Year',
                'description' => '',
                'locale' => '',
                'age_property_name' => 'birth_year',
                'age_property_type' => PropertyType::Integer->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $property = VertexProperty::where('vertex_type_id', $vertexType->id)
            ->where('age_property_name', 'birth_year')
            ->first();
        $this->assertNotNull($property);
        $this->assertNull($property->locale);
    }

    public function test_store_fail_when_localized_conflicts_with_existing_non_localized_property()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name',
            'locale' => null,
        ]);

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => '姓名',
                'description' => '',
                'locale' => 'zh_tw',
                'base_age_property_name' => 'name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['base_age_property_name']);

        $this->assertCount(1, VertexProperty::where('vertex_type_id', $vertexType->id)->get());
    }

    public function test_store_fail_when_non_localized_conflicts_with_existing_localized_property()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Name',
                'description' => '',
                'age_property_name' => 'name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);

        $this->assertCount(1, VertexProperty::where('vertex_type_id', $vertexType->id)->get());
    }

    public function test_store_fail_when_base_age_property_name_exceeds_max_length()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => '姓名',
                'description' => '',
                'locale' => 'zh_tw',
                'base_age_property_name' => str_repeat('a', 59),
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['base_age_property_name']);
    }

    public function test_store_fail_when_age_property_name_invalid()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Full Name',
                'description' => '',
                'age_property_name' => 'Invalid-Name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);
    }

    public function test_store_fail_when_age_property_type_invalid()
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Full Name',
                'description' => '',
                'age_property_name' => 'full_name',
                'age_property_type' => 'not_a_type',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_type']);
    }

    public function test_update_success()
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create();

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
                'age_property_type' => PropertyType::Integer->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $updatedProperty = VertexProperty::find($vertexProperty->id);
        $this->assertNotNull($updatedProperty);
        $this->assertEquals('Updated Name', $updatedProperty->name);
        $this->assertEquals(PropertyType::Integer, $updatedProperty->age_property_type);
    }

    public function test_store_fail_when_age_property_name_is_cypher_reserved_word(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->post("/graph-schema/vertex-type/{$vertexType->id}/vertex-property", [
                'name' => 'Reserved',
                'description' => '',
                'age_property_name' => 'in',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);
    }

    public function test_update_does_not_change_age_property_name_or_locale()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Other Property',
            'age_property_name' => 'taken_prop',
        ]);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Localized Name',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => 'Updated Localized Name',
                'description' => 'Updated description',
                'base_age_property_name' => 'name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $vertexProperty->refresh();
        $this->assertEquals('Updated Localized Name', $vertexProperty->name);
        $this->assertEquals('name_zh_tw', $vertexProperty->age_property_name);
        $this->assertEquals('zh_tw', $vertexProperty->locale);
    }

    public function test_update_can_rename_age_property_name_when_no_graph_data(): void
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Full Name',
            'age_property_name' => 'full_name',
            'locale' => null,
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => 'Full Name',
                'description' => '',
                'age_property_name' => 'display_name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame('display_name', $vertexProperty->fresh()->age_property_name);
    }

    public function test_update_can_rename_localized_base_age_property_name_when_no_graph_data(): void
    {
        $vertexType = VertexType::factory()->create([
            'show_property_name' => 'name',
        ]);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => '姓名',
                'description' => '',
                'base_age_property_name' => 'title',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $vertexProperty->refresh();
        $this->assertSame('title_zh_tw', $vertexProperty->age_property_name);
        $this->assertSame('zh_tw', $vertexProperty->locale);
        $this->assertSame('title', $vertexType->fresh()->show_property_name);
    }

    public function test_update_rejects_locale_field(): void
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Localized Name',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => $vertexProperty->name,
                'description' => '',
                'base_age_property_name' => 'name',
                'locale' => 'en_us',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['locale']);

        $this->assertSame('zh_tw', $vertexProperty->fresh()->locale);
    }

    public function test_update_rejects_age_property_name_when_graph_data_exists(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'lock_prop_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Full Name',
            'age_property_name' => 'full_name',
            'locale' => null,
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType, $vertexProperty) {
                return $builder->createNode(null, $vertexType->age_label_name, [
                    $vertexProperty->age_property_name => 'in_use',
                ])->setAs(['v']);
            })->get();

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => $vertexProperty->name,
                'description' => '',
                'age_property_name' => 'renamed_name',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['age_property_name']);

        $this->assertSame('full_name', $vertexProperty->fresh()->age_property_name);
    }

    public function test_update_fail_when_name_not_unique_within_vertex_type()
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create(['name' => 'Taken Name']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create(['name' => 'Original Name']);

        $this->actingAs($this->user)
            ->put("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}", [
                'name' => 'Taken Name',
                'description' => '',
                'age_property_type' => PropertyType::String->value,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertEquals('Original Name', $vertexProperty->fresh()->name);
    }

    public function test_destroy_success()
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create();

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}")
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($vertexProperty);
    }

    public function test_destroy_fail_when_property_used_in_graph_data()
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'destroy_vertex_prop_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create();

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType, $vertexProperty) {
                return $builder->createNode(null, $vertexType->age_label_name, [
                    $vertexProperty->age_property_name => 'in_use',
                ])->setAs(['v']);
            })->get();

        $this->actingAs($this->user)
            ->delete("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}")
            ->assertStatus(302)
            ->assertSessionHas('warning');

        $this->assertModelExists($vertexProperty);
    }

    public function test_create_form_shows_locale_selector(): void
    {
        $vertexType = VertexType::factory()->create();

        $this->actingAs($this->user)
            ->get("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/create")
            ->assertOk()
            ->assertSee('語言版本')
            ->assertSee('非多語系')
            ->assertSee('繁體中文（zh_tw）')
            ->assertSee('id="age_property_type"', false)
            ->assertSee('form-select', false)
            ->assertSee('>INTEGER<', false)
            ->assertSee('>STRING<', false)
            ->assertDontSee('type="text" name="age_property_type"', false);
    }

    public function test_edit_form_allows_editing_property_name_when_no_graph_data(): void
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $response = $this->actingAs($this->user)
            ->get("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}/edit");

        $response->assertOk()
            ->assertSee('語言版本')
            ->assertSee('繁體中文')
            ->assertSee('(zh_tw)')
            ->assertSee('id="base_age_property_name"', false)
            ->assertDontSee('id="locale"', false)
            ->assertDontSee('圖資料庫中已有此屬性的資料，無法變更 Property 名稱');
    }

    public function test_edit_form_shows_readonly_property_name_when_graph_data_exists(): void
    {
        $vertexType = VertexType::factory()->create(['age_label_name' => 'readonly_prop_vt']);
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        DB::connection(config('cohistograph.app.graph.connection-name'))
            ->apacheAgeCypher(config('cohistograph.app.graph.name'), function (AgeQueryBuilder $builder) use ($vertexType, $vertexProperty) {
                return $builder->createNode(null, $vertexType->age_label_name, [
                    $vertexProperty->age_property_name => 'in_use',
                ])->setAs(['v']);
            })->get();

        $response = $this->actingAs($this->user)
            ->get("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}/edit");

        $response->assertOk()
            ->assertSee('語言版本')
            ->assertSee('繁體中文')
            ->assertSee('name_zh_tw')
            ->assertSee('圖資料庫中已有此屬性的資料，無法變更 Property 名稱')
            ->assertDontSee('id="locale"', false)
            ->assertDontSee('id="base_age_property_name"', false);
    }

    public function test_show_displays_locale_for_localized_property(): void
    {
        $vertexType = VertexType::factory()->create();
        $vertexProperty = VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $this->actingAs($this->user)
            ->get("/graph-schema/vertex-type/{$vertexType->id}/vertex-property/{$vertexProperty->id}")
            ->assertOk()
            ->assertSee('語言版本')
            ->assertSee('繁體中文')
            ->assertSee('(zh_tw)')
            ->assertSee('name_zh_tw');
    }

    public function test_vertex_type_show_lists_enum_option_labels(): void
    {
        $this->mock(\App\Support\AgePropertyDataChecker::class, function ($mock): void {
            $mock->shouldReceive('vertexPropertyHasData')->andReturn(false);
            $mock->shouldReceive('usedVertexEnumValues')->andReturn([]);
        });

        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->enum([
            ['value' => 'rock', 'label' => '搖滾', 'active' => true],
            ['value' => 'jazz', 'label' => '爵士', 'active' => false],
        ])->create([
            'name' => 'Genres',
            'age_property_name' => 'genres',
        ]);

        $this->actingAs($this->user)
            ->get(route('graph-schema.vertex-type.show', $vertexType))
            ->assertOk()
            ->assertSee('ENUM')
            ->assertSee('搖滾')
            ->assertSee('爵士（已停用）');
    }
}
