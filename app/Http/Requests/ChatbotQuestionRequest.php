<?php

namespace App\Http\Requests;

use App\Services\ChatbotService;
use Illuminate\Foundation\Http\FormRequest;

class ChatbotQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // visitors may ask without an account
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge(['question' => ChatbotService::normalize($this->input('question'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:'.config('bookease.chatbot.max_question_length', 500)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question.required' => 'Please type a question.',
            'question.min' => 'Please type a question.',
            'question.max' => 'Please keep your question under :max characters.',
        ];
    }
}
