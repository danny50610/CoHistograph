<?php

namespace App\Http\Requests;

use App\SystemConfig\HomepageConfig;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHomepageConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('system-config.manage') === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return HomepageConfig::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tagline.required' => '標題下方文字為必填',
            'tagline.max' => '標題下方文字不可超過 :max 字',
        ];
    }
}
