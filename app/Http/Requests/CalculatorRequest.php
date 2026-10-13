<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['weight' => 'required|numeric|decimal:0,8|min:0.00000001|max:1000000', 'unit' => 'required|in:g,tola,kg,oz', 'purity' => 'required|numeric|decimal:0,5|min:0.00001|max:100', 'quantity' => 'required|integer|min:1|max:10000', 'benchmark' => 'required|numeric|decimal:0,8|min:0.00000001|max:100000000', 'premium' => 'required|numeric|min:-100|max:1000', 'charges' => 'required|numeric|min:0|max:1000000000', 'tax' => 'required|numeric|min:0|max:100'];
    }
}
