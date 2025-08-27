<?php

namespace App\Rules\GraphSchema;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AgePropertyName implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        if (strlen($value) > 64) {
            $fail('The :attribute must not exceed 64 characters.');
            return;
        }

        if (preg_match('/^[a-z0-9_][a-z0-9_\-]*$/', $value) === false) {
            $fail(':attribute 只能小寫英文、數字、"_" 開頭，後續只能包含小寫英文、數字、"_"、"-"');
        }
    }
}
