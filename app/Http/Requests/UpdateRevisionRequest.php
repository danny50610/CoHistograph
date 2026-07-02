<?php

namespace App\Http\Requests;

use App\Enums\RevisionActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'actions' => ['present', 'array'],
            'actions.*.action' => ['required', new Enum(RevisionActionType::class)],
            'actions.*.target_age_id' => ['nullable', 'integer'],
            'actions.*.target_ref_order' => ['nullable', 'integer'],
            'actions.*.vertex_type_label' => ['nullable', 'string', 'max:64'],
            'actions.*.edge_type_label' => ['nullable', 'string', 'max:64'],
            'actions.*.start_vertex_age_id' => ['nullable', 'integer'],
            'actions.*.start_vertex_ref_order' => ['nullable', 'integer'],
            'actions.*.end_vertex_age_id' => ['nullable', 'integer'],
            'actions.*.end_vertex_ref_order' => ['nullable', 'integer'],
            'actions.*.age_property_name' => ['nullable', 'string', 'max:64'],
            'actions.*.value' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => '請填寫標題',
            'title.max' => '標題最多 255 字元',
            'actions.*.action.required' => '每個操作必須指定 action 類型',
        ];
    }
}
