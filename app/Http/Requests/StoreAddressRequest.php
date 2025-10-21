<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:120'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'street' => ['required', 'string', 'max:190'],
            'building' => ['nullable', 'string', 'max:190'],
            'house' => ['nullable', 'string', 'max:190'],
            'apartment' => ['nullable', 'string', 'max:190'],
            'floor' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'block_id' => __('Block / Area'),
        ];
    }
}
