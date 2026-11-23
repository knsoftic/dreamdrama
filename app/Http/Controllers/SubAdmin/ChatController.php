<?php

namespace App\Http\Controllers\SubAdmin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;

/** The sub-admin's inbox — chats with their downline members. */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->where('staff_id', $request->user()->id)
            ->when($request->query('q'), fn ($qq, $s) => $qq->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->with(['user', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('subadmin.chats.index', ['conversations' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $this->chat->markRead($conversation, $request->user());
        $messages = $conversation->messages()->latest('id')->limit(60)->get()->reverse()->values();

        return view('subadmin.chats.show', [
            'conversation' => $conversation->load('user'),
            'messages' => $messages,
        ]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $new = $conversation->messages()->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->get();
        if ($new->isNotEmpty()) {
            $this->chat->markRead($conversation, $request->user());
        }

        return response()->json(['messages' => $new->map->toWire()]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $this->chat->send($conversation, $request->user(), ['type' => 'text', 'body' => $data['body']]);

        return response()->json(['message' => $message->toWire()]);
    }

    public function mute(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $conversation->update(['muted' => ! $conversation->muted]);

        return back()->with('success', $conversation->muted ? 'Chat mute ho gayi.' : 'Chat unmute ho gayi.');
    }

    public function block(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $conversation->update(['blocked' => ! $conversation->blocked]);

        return back()->with('success', $conversation->blocked ? 'User block — ab ye message nahi bhej sakta.' : 'User unblock ho gaya.');
    }

    private function guard(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->staff_id === $request->user()->id, 403);
    }
}
