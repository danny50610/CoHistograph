<?php

namespace App\Rules\GraphSchema;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueResolvedAgePropertyName implements ValidationRule
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    public function __construct(
        private string $model,
        private string $foreignKey,
        private int $parentId,
        private ?int $ignoreId = null,
    ) {}

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $exists = $this->model::query()
            ->where($this->foreignKey, $this->parentId)
            ->where('age_property_name', $value)
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail("{$value} 已被使用");
        }
    }
}
