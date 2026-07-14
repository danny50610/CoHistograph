<?php

namespace Tests\Unit\Rules\GraphSchema;

use App\Rules\GraphSchema\ImmutableAgeLabelNameWhenGraphDataExists;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ImmutableAgeLabelNameWhenGraphDataExistsTest extends TestCase
{
    public function test_passes_when_value_matches_current_label(): void
    {
        $validator = Validator::make(
            ['age_label_name' => 'person'],
            ['age_label_name' => [new ImmutableAgeLabelNameWhenGraphDataExists('person', fn (): bool => true)]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_passes_when_label_changes_and_no_graph_data(): void
    {
        $validator = Validator::make(
            ['age_label_name' => 'individual'],
            ['age_label_name' => [new ImmutableAgeLabelNameWhenGraphDataExists('person', fn (): bool => false)]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_fails_when_label_changes_and_graph_data_exists(): void
    {
        $validator = Validator::make(
            ['age_label_name' => 'individual'],
            ['age_label_name' => [new ImmutableAgeLabelNameWhenGraphDataExists('person', fn (): bool => true)]],
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(
            ['圖資料庫中已有此類型的資料，無法變更 Label 名稱'],
            $validator->errors()->get('age_label_name'),
        );
    }

    public function test_does_not_query_graph_when_label_unchanged(): void
    {
        $queried = false;

        $validator = Validator::make(
            ['age_label_name' => 'person'],
            ['age_label_name' => [new ImmutableAgeLabelNameWhenGraphDataExists('person', function () use (&$queried): bool {
                $queried = true;

                return true;
            })]],
        );

        $this->assertTrue($validator->passes());
        $this->assertFalse($queried);
    }
}
