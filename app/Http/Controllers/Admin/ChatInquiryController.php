<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatInquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only audit log of chatbot exchanges (owner only). */
class ChatInquiryController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'fallback' ? 'fallback' : 'all';
        $search = trim((string) $request->query('q', ''));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $inquiries = ChatInquiry::query()
            ->with('user:id,name,email')
            ->when($filter === 'fallback', fn ($q) => $q->where('was_fallback', true))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('question', 'like', $like)
                ->orWhere('response', 'like', $like)))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $since = now()->subDays(7);
        $week = ChatInquiry::where('created_at', '>=', $since);

        return view('admin.chatbot.index', [
            'inquiries' => $inquiries,
            'filter' => $filter,
            'search' => $search,
            'stats' => [
                'today' => ChatInquiry::where('created_at', '>=', today())->count(),
                'week' => (clone $week)->count(),
                'week_fallback' => (clone $week)->where('was_fallback', true)->count(),
                'week_visitors' => (clone $week)->distinct()->count('session_id'),
            ],
        ]);
    }

    public function show(ChatInquiry $inquiry): View
    {
        $inquiry->load('user:id,name,email');

        $conversation = $inquiry->session_id
            ? ChatInquiry::where('session_id', $inquiry->session_id)
                ->whereBetween('created_at', [$inquiry->created_at->copy()->subHours(2), $inquiry->created_at->copy()->addHours(2)])
                ->orderBy('id')
                ->get(['id', 'question', 'response', 'was_fallback', 'created_at'])
            : collect([$inquiry]);

        return view('admin.chatbot.show', compact('inquiry', 'conversation'));
    }
}
