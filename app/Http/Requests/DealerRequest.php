<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['business_name' => 'required|string|max:150', 'representative' => 'required|string|max:150', 'city_id' => ['bail', 'required', 'integer', Rule::exists('cities', 'id')->where('active', true)], 'phone' => ['required', new PhoneNumber], 'whatsapp' => ['nullable', new PhoneNumber], 'address' => 'required|string|max:255', 'maps_url' => 'nullable|url:http,https|max:255', 'category' => 'required|string|max:100', 'description' => 'required|string|min:30|max:5000', 'working_hours' => 'nullable|string|max:255', 'website' => 'nullable|url:http,https|max:255', 'social_urls' => 'nullable|array|max:5', 'social_urls.*' => 'nullable|url:http,https|max:255', 'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072|dimensions:max_width=3000,max_height=3000', 'photos' => 'nullable|array|max:6', 'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=3000,max_height=3000', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', 'document_label' => 'required_with:document|nullable|string|max:100', 'intent' => 'required|in:draft,submit'];
    }

    public function prepareForValidation(): void
    {
        foreach (['phone', 'whatsapp'] as $key) {
            if (! is_string($this->input($key)) && $this->input($key) !== null) {
                continue;
            }
            $v = preg_replace('/[\s()-]/', '', (string) $this->input($key));
            if (str_starts_with($v, '03')) {
                $v = '+92'.substr($v, 1);
            }
            $this->merge([$key => $v ?: null]);
        }
    }
}
