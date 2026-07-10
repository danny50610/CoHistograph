<?php

namespace App\Rules\GraphSchema;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LocaleMutualExclusion implements ValidationRule
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public function __construct(
        private string $model,
        private string $foreignKey,
        private int $parentId,
        private ?string $locale,
    ) {}

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $query = $this->model::query()->where($this->foreignKey, $this->parentId);

        if ($this->locale === null) {
            foreach (array_keys(config('cohistograph.app.graph.locales', [])) as $locale) {
                $exists = (clone $query)
                    ->whereNotNull('locale')
                    ->where('age_property_name', $value.'_'.$locale)
                    ->exists();

                if ($exists) {
                    $fail('已存在多語系版本的同名屬性，無法建立非多語系版本');

                    return;
                }
            }

            return;
        }

        $exists = (clone $query)
            ->whereNull('locale')
            ->where('age_property_name', $value)
            ->exists();

        if ($exists) {
            $fail('已存在非多語系版本的同名屬性，無法建立多語系版本');
        }
    }
}
