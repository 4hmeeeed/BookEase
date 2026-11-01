<?php

namespace App\Http\Requests;

use App\Exceptions\InvalidStayException;
use App\Models\Booking;
use App\Models\Cottage;
use App\Services\AvailabilityService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            // "0917 123 4567" / "+63 917-123-4567" → "09171234567"
            $digits = preg_replace('/\D/', '', (string) $this->input('phone'));
            $this->merge(['phone' => str_starts_with($digits, '63') ? '0'.substr($digits, 2) : $digits]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Cottage $cottage */
        $cottage = $this->route('cottage');

        return [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d'],
            'guests_count' => ['required', 'integer', 'min:1', 'max:'.$cottage->capacity],
            // Philippine mobile number: 11 digits starting with 09.
            'phone' => ['required', 'regex:/^09\d{9}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'agree' => ['accepted'],
        ];
    }

    /**
     * Stay rules (past dates, max nights, …) from the single source of truth.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['check_in', 'check_out'])) {
                    return;
                }

                try {
                    app(AvailabilityService::class)->validateStay($this->input('check_in'), $this->input('check_out'));
                } catch (InvalidStayException $e) {
                    $validator->errors()->add('check_in', $e->getMessage());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guests_count.max' => 'This unit fits up to :max guests.',
            'phone.regex' => 'Enter a mobile number like 0917 123 4567.',
            'agree.accepted' => 'Please confirm that your booking details are correct.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guests_count' => 'number of guests',
            'phone' => 'mobile number',
        ];
    }
}
