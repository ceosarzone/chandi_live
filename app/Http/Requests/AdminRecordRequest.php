<?php

namespace App\Http\Requests;

use App\Services\AdminResources;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        [$model,$permission,$columns,$rules] = AdminResources::get($this->route('resource'));

        return $rules !== [] && $this->user()?->canManage($permission);
    }

    public function rules(): array
    {
        [$model,$permission,$columns,$rules] = AdminResources::get($this->route('resource'));
        foreach ($rules as $field => $rule) {
            if (str_ends_with($field, '_id') && is_string($rule)) {
                $rules[$field] = 'bail|integer|'.$rule;
            }
        }
        if (isset($rules['slug'])) {
            $rules['slug'] = [...explode('|', $rules['slug']), Rule::unique((new $model)->getTable(), 'slug')->ignore($this->route('id'))];
        }

        return $rules;
    }
}
