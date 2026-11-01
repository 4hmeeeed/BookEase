<?php

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Remarks attached to a staff decision. Required when rejecting or
 * cancelling so the guest (and the audit log) knows why.
 */
class BookingDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('booking')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $needsReason = in_array($this->route()?->getActionMethod(), ['reject', 'cancel'], true);

        return [
            'remarks' => [$needsReason ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remarks.required' => 'Please give the guest a short reason.',
        ];
    }
}
