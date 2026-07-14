<?php

namespace App\Rules\GraphSchema;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ImmutableAgeLabelNameWhenGraphDataExists implements ValidationRule
{
    /**
     * @param  Closure(): bool  $hasGraphData
     */
    public function __construct(
        private string $currentAgeLabelName,
        private Closure $hasGraphData,
    ) {}

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === $this->currentAgeLabelName) {
            return;
        }

        if (($this->hasGraphData)()) {
            $fail('圖資料庫中已有此類型的資料，無法變更 Label 名稱');
        }
    }
}
