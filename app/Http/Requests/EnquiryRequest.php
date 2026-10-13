<?php

namespace App\Http\Requests;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class EnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['buyer_name' => 'required|string|max:120', 'email' => 'required|email|max:255', 'phone' => ['nullable', new PhoneNumber], 'message' => 'required|string|min:10|max:2000', 'consent' => 'accepted', 'website' => 'nullable|max:0', 'dealer_profile_id' => 'nullable|integer', 'product_id' => 'nullable|integer'];
    }
}
