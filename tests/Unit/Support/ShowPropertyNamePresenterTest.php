<?php

namespace Tests\Unit\Support;

use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\ShowPropertyNamePresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShowPropertyNamePresenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_builds_semantic_options_for_localized_and_non_localized_properties(): void
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
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '出生年份',
            'age_property_name' => 'birth_year',
            'locale' => null,
        ]);

        $options = app(ShowPropertyNamePresenter::class)->options($vertexType->properties()->orderBy('id')->get());

        $this->assertCount(2, $options);
        $this->assertSame('name', $options[0]['value']);
        $this->assertStringContainsString('多語系', $options[0]['label']);
        $this->assertSame('birth_year', $options[1]['value']);
        $this->assertStringContainsString('出生年份', $options[1]['label']);
    }

    public function test_formats_display_label_for_localized_group(): void
    {
        $vertexType = VertexType::factory()->create([
            'show_property_name' => 'name',
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'age_property_name' => 'name_zh_tw',
            'locale' => 'zh_tw',
        ]);

        $label = app(ShowPropertyNamePresenter::class)->displayLabel($vertexType->fresh('properties'));

        $this->assertSame('name（多語系，顯示語言：zh_tw）', $label);
    }
}
