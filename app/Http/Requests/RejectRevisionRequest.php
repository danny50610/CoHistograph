<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('revision.review') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required' => '退回理由為必填',
            'comment.min' => '退回理由為必填',
        ];
    }
}
