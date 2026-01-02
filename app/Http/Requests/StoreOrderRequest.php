<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol' => 'required|string|exists:markets,symbol',
            'type' => 'required|in:buy,sell',
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
        ];
    }

    public function messages(): array
    {
        return [
            'symbol.exists' => 'Market not found or inactive',
            'type.in' => 'Order type must be either buy or sell',
            'price.min' => 'Price must be greater than 0',
            'amount.min' => 'Amount must be greater than 0',
        ];
    }
}

