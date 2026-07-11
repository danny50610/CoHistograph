<?php

namespace App\Http\Requests\GraphSchema\Concerns;

trait ResolvesLocalizedAgePropertyName
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('locale') || $this->input('locale') === '') {
            $this->merge(['locale' => null]);
        }

        $locale = $this->input('locale');

        $this->merge([
            'resolved_age_property_name' => $locale
                ? $this->input('base_age_property_name').'_'.$locale
                : $this->input('age_property_name'),
        ]);
    }
}
