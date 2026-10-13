<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() ?? false;
    }

    public function rules(): array
    {
        return ['threshold' => 'required|numeric|gt:0|max:100000000', 'direction' => 'required|in:above,below', 'currency' => 'required|in:PKR,USD', 'unit' => 'required|in:g,tola,oz,kg', 'frequency_hours' => 'required|integer|min:1|max:168', 'expires_at' => 'nullable|date|after:now', 'email_enabled' => 'accepted'];
    }
}
