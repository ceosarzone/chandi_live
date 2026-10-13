<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->dealer?->status === 'approved';
    }

    public function rules(): array
    {
        return ['title' => 'required|string|max:150', 'product_category_id' => ['bail', 'required', 'integer', Rule::exists('product_categories', 'id')->where('active', true)], 'description' => 'required|string|min:20|max:5000', 'weight' => 'required|numeric|decimal:0,8|min:0.00000001|max:1000000', 'weight_unit' => 'required|in:g,tola,kg,oz', 'purity' => 'required|numeric|decimal:0,5|min:0.00001|max:100', 'quantity' => 'required|integer|min:0|max:1000000', 'availability' => 'required|in:in_stock,out_of_stock,on_request', 'price' => 'nullable|numeric|decimal:0,2|min:0|max:100000000000', 'currency' => 'required|in:PKR,USD', 'condition' => 'required|in:new,used', 'images' => 'nullable|array|max:6', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=3000,max_height=3000'];
    }
}
