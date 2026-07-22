<?php

namespace Tests\Unit\Support;

use App\Enums\PropertyType;
use App\Models\VertexProperty;
use App\Models\VertexType;
use App\Support\LocalizedPropertyGrouper;
use App\Support\PropertyValueCaster;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LocalizedPropertyGrouperDateCastTest extends TestCase
{
    use DatabaseTransactions;

    public function test_group_casts_date_and_timestamptz_values_from_storage(): void
    {
        $vertexType = VertexType::factory()->create();
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '發生日期',
            'age_property_name' => 'occurred_on',
            'age_property_type' => PropertyType::Date,
            'locale' => null,
        ]);
        VertexProperty::factory()->for($vertexType)->create([
            'name' => '記錄時間',
            'age_property_name' => 'recorded_at',
            'age_property_type' => PropertyType::Timestamptz,
            'locale' => null,
        ]);

        $grouper = new LocalizedPropertyGrouper(new PropertyValueCaster);
        $groups = $grouper->group(
            $vertexType->properties()->orderBy('id')->get(),
            [
                'occurred_on' => '2024-07-22',
                'recorded_at' => '2024-07-22T14:30:00+08:00',
            ],
        );

        $this->assertCount(2, $groups);
        $this->assertInstanceOf(CarbonImmutable::class, $groups[0]['members'][0]['value']);
        $this->assertSame('2024-07-22', $groups[0]['members'][0]['value']->toDateString());
        $this->assertInstanceOf(CarbonImmutable::class, $groups[1]['members'][0]['value']);
        $this->assertSame(480, $groups[1]['members'][0]['value']->offsetMinutes);
    }
}
