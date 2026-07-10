<?php

namespace Tests\Unit\Support;

use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\LocalizedPropertyGrouper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocalizedPropertyGrouperTest extends TestCase
{
    use DatabaseTransactions;

    private LocalizedPropertyGrouper $grouper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grouper = new LocalizedPropertyGrouper;
    }

    public function test_groups_localized_properties_by_base_name(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Name',
            'age_property_name' => 'name_en_us',
            'locale' => 'en_us',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '名前',
            'age_property_name' => 'name_ja_jp',
            'locale' => 'ja_jp',
        ]);

        $groups = $this->grouper->group($vertexType->properties()->orderBy('id')->get());

        $this->assertCount(1, $groups);
        $this->assertTrue($groups[0]['is_localized']);
        $this->assertSame('Name', $groups[0]['title']);
        $this->assertCount(3, $groups[0]['members']);
        $this->assertSame(['繁體中文', '日本語', 'English'], array_column($groups[0]['members'], 'locale_label'));
    }

    public function test_non_localized_property_forms_own_group(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '出生年份',
            'age_property_name' => 'birth_year',
            'locale' => null,
        ]);

        $groups = $this->grouper->group($vertexType->properties);

        $this->assertCount(1, $groups);
        $this->assertFalse($groups[0]['is_localized']);
        $this->assertSame('出生年份', $groups[0]['title']);
        $this->assertCount(1, $groups[0]['members']);
        $this->assertSame('birth_year', $groups[0]['members'][0]['property']->age_property_name);
    }

    public function test_dirty_data_keeps_non_localized_and_localized_groups_separate(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name',
            'locale' => null,
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Name',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $groups = $this->grouper->group($vertexType->properties()->orderBy('id')->get());

        $this->assertCount(2, $groups);
        $this->assertFalse($groups[0]['is_localized']);
        $this->assertSame('name', $groups[0]['members'][0]['property']->age_property_name);
        $this->assertTrue($groups[1]['is_localized']);
        $this->assertSame('name_zh_tw', $groups[1]['members'][0]['property']->age_property_name);
    }

    public function test_group_title_uses_first_property_by_id(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Name',
            'age_property_name' => 'name_en_us',
            'locale' => 'en_us',
        ]);

        $groups = $this->grouper->group($vertexType->properties()->orderBy('id')->get());

        $this->assertSame('姓名', $groups[0]['title']);
    }

    public function test_includes_property_values_when_provided(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $groups = $this->grouper->group(
            $vertexType->properties,
            ['name_zh_tw' => '李白'],
        );

        $this->assertSame('李白', $groups[0]['members'][0]['value']);
    }

    public function test_partial_locale_definitions_only_include_defined_members(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Name',
            'age_property_name' => 'name_en_us',
            'locale' => 'en_us',
        ]);

        $groups = $this->grouper->group($vertexType->properties);

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['members']);
        $this->assertSame(
            ['name_zh_tw', 'name_en_us'],
            collect($groups[0]['members'])->map(fn (array $member) => $member['property']->age_property_name)->all(),
        );
    }

    public function test_localized_property_without_locale_suffix_still_groups_by_full_name(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'display_name',
            'locale' => 'zh_tw',
        ]);

        $groups = $this->grouper->group($vertexType->properties);

        $this->assertCount(1, $groups);
        $this->assertTrue($groups[0]['is_localized']);
        $this->assertSame('display_name', $groups[0]['members'][0]['property']->age_property_name);
        $this->assertSame('繁體中文', $groups[0]['members'][0]['locale_label']);
    }

    public function test_unknown_locale_falls_back_to_raw_code_and_sorts_last(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => 'Name',
            'age_property_name' => 'name_fr_fr',
            'locale' => 'fr_fr',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '姓名',
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $groups = $this->grouper->group($vertexType->properties()->orderBy('id')->get());

        $this->assertSame(['繁體中文', 'fr_fr'], array_column($groups[0]['members'], 'locale_label'));
    }
}
