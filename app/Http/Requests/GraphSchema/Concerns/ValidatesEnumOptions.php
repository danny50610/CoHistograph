<?php

namespace App\Http\Requests\GraphSchema\Concerns;

use App\Enums\PropertyType;
use App\Support\EnumOptions;
use Illuminate\Validation\Validator;

trait ValidatesEnumOptions
{
    /**
     * @return array<string, mixed>
     */
    protected function enumOptionsRules(): array
    {
        $type = $this->input('age_property_type');

        if ($type === PropertyType::Enum->value) {
            return [
                'enum_options' => ['required', 'array', 'min:1'],
                'enum_options.*.value' => ['required', 'string', 'max:'.EnumOptions::VALUE_MAX_LENGTH, 'regex:'.EnumOptions::VALUE_PATTERN],
                'enum_options.*.label' => ['required', 'string', 'max:'.EnumOptions::LABEL_MAX_LENGTH],
                'enum_options.*.active' => ['sometimes', 'boolean'],
                'locale' => ['prohibited'],
            ];
        }

        return [
            'enum_options' => ['prohibited'],
        ];
    }

    protected function withEnumOptionsValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('age_property_type') !== PropertyType::Enum->value) {
                return;
            }

            if ($this->filled('locale')) {
                $validator->errors()->add('locale', 'ENUM 屬性不可設定語言版本。');
            }

            try {
                $options = EnumOptions::normalize(
                    is_array($this->input('enum_options')) ? $this->input('enum_options') : null,
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('enum_options', $e->getMessage());

                return;
            }

            $values = [];
            $labels = [];

            foreach ($options as $index => $option) {
                if ($option['value'] === '' || ! EnumOptions::isValidOptionValue($option['value'])) {
                    $validator->errors()->add("enum_options.{$index}.value", '選項 value 格式無效。');
                }

                if ($option['label'] === '') {
                    $validator->errors()->add("enum_options.{$index}.label", '選項 label 不可為空。');
                }

                if (in_array($option['value'], $values, true)) {
                    $validator->errors()->add("enum_options.{$index}.value", '選項 value 不可重複。');
                }

                if (in_array($option['label'], $labels, true)) {
                    $validator->errors()->add("enum_options.{$index}.label", '選項 label 不可重複。');
                }

                $values[] = $option['value'];
                $labels[] = $option['label'];
            }
        });
    }
}
