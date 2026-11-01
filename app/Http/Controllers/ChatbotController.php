<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotQuestionRequest;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __invoke(ChatbotQuestionRequest $request, ChatbotService $chatbot): JsonResponse
    {
        // A random chat id groups a visitor's questions. The Laravel session id
        // itself is never logged, since it would let anyone reading logs hijack a session.
        $chatId = $request->session()->get('chatbot_id');

        if (! is_string($chatId) || ! Str::isUuid($chatId)) {
            $chatId = (string) Str::uuid();
            $request->session()->put('chatbot_id', $chatId);
        }

        $reply = $chatbot->ask($request->validated('question'), $request->user(), $chatId);

        return response()->json([
            'answer' => $reply->answer,
            'fallback' => $reply->fallback,
        ]);
    }
}
