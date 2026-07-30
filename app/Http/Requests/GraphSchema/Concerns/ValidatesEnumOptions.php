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
                'min_selections' => ['required', 'integer', 'min:'.EnumOptions::SELECTION_COUNT_MIN, 'max:'.EnumOptions::SELECTION_COUNT_MAX],
                'max_selections' => ['nullable', 'integer', 'min:'.EnumOptions::SELECTION_COUNT_MIN, 'max:'.EnumOptions::SELECTION_COUNT_MAX],
                'locale' => ['prohibited'],
            ];
        }

        return [
            'enum_options' => ['prohibited'],
            'min_selections' => ['prohibited'],
            'max_selections' => ['prohibited'],
        ];
    }

    protected function prepareEnumSelectionLimits(): void
    {
        if ($this->input('age_property_type') !== PropertyType::Enum->value) {
            return;
        }

        if (! $this->filled('min_selections')) {
            $this->merge(['min_selections' => EnumOptions::DEFAULT_MIN_SELECTIONS]);
        }

        if ($this->input('max_selections') === '') {
            $this->merge(['max_selections' => null]);
        }
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

            $min = $this->integerOrNull('min_selections') ?? EnumOptions::DEFAULT_MIN_SELECTIONS;
            $max = $this->integerOrNull('max_selections');

            foreach (EnumOptions::validateSchemaSelectionLimits($options, $min, $max) as $message) {
                if (str_contains($message, '啟用中的選項不足')) {
                    $validator->errors()->add('enum_options', $message);
                } elseif (str_contains($message, '不可小於') || str_contains($message, '最多選取數必須')) {
                    $validator->errors()->add('max_selections', $message);
                } elseif (str_contains($message, '最少選取數必須')) {
                    $validator->errors()->add('min_selections', $message);
                } else {
                    $validator->errors()->add('enum_options', $message);
                }
            }
        });
    }

    protected function integerOrNull(string $key): ?int
    {
        if (! $this->exists($key) || $this->input($key) === null || $this->input($key) === '') {
            return null;
        }

        return (int) $this->input($key);
    }

    /**
     * @return array{min_selections: int, max_selections: int|null}|array{min_selections: null, max_selections: null}
     */
    public function enumSelectionLimitsForStorage(): array
    {
        if ($this->input('age_property_type') !== PropertyType::Enum->value) {
            return [
                'min_selections' => null,
                'max_selections' => null,
            ];
        }

        return [
            'min_selections' => $this->integerOrNull('min_selections') ?? EnumOptions::DEFAULT_MIN_SELECTIONS,
            'max_selections' => $this->integerOrNull('max_selections'),
        ];
    }
}
