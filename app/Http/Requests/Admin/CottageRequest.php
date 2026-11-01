<?php

namespace App\Http\Requests\Admin;

use App\Enums\CottageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CottageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Accept "1,500.00" or "₱1500" from the form; keep it a string.
            'price_per_night' => preg_replace('/[^\d.]/', '', (string) $this->input('price_per_night')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CottageType::class)],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            // DECIMAL(10,2): up to 8 whole digits, up to 2 decimals, > 0
            'price_per_night' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=400,min_height=300'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_per_night.regex' => 'Enter the nightly rate in pesos, e.g. 2500 or 2500.50.',
            'price_per_night.not_regex' => 'The nightly rate must be more than ₱0.00.',
            'image.dimensions' => 'The photo should be at least 400 × 300 pixels.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'price_per_night' => 'nightly rate',
        ];
    }
}
