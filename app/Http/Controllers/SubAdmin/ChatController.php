<?php

namespace App\Http\Controllers\SubAdmin;

use App\Http\Controllers\Concerns\BuildsChatMessage;
use App\Http\Controllers\Controller;
use App\Models\ChatLabel;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;

/** The sub-admin's inbox — chats with their downline members. */
class ChatController extends Controller
{
    use BuildsChatMessage;

    public function __construct(private ChatService $chat) {}

    public function index(Request $request)
    {
        $me = $request->user();
        $conversations = Conversation::query()
            ->where('staff_id', $me->id)
            ->when($request->query('q'), fn ($qq, $s) => $qq->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->integer('label'), fn ($qq, $id) => $qq->whereHas('labels', fn ($l) => $l->whereKey($id)))
            ->with(['user', 'latestMessage', 'labels'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('subadmin.chats.index', [
            'conversations' => $conversations,
            'labels' => ChatLabel::query()->availableFor($me)->get(),
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $this->chat->markRead($conversation, $request->user());
        $messages = $conversation->messages()->latest('id')->limit(60)->get()->reverse()->values();

        return view('subadmin.chats.show', [
            'conversation' => $conversation->load('user', 'labels'),
            'messages' => $messages,
            'labels' => ChatLabel::query()->availableFor($request->user())->get(),
        ]);
    }

    public function labelStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        ChatLabel::query()->create([
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
            'color' => ($data['color'] ?? null) ?: '#8b5cf6',
        ]);

        return back()->with('success', 'Label ban gaya.');
    }

    public function labelDestroy(Request $request, ChatLabel $label)
    {
        abort_unless($label->owner_id === $request->user()->id, 403);   // sub-admin can delete only their own labels
        $label->delete();

        return back()->with('success', 'Label delete ho gaya.');
    }

    public function syncLabels(Request $request, Conversation $conversation)
    {
        $this->guard($request, $conversation);
        $allowed = ChatLabel::query()->availableFor($request->user())->pluck('id');
        $ids = collect($request->input('labels', []))->map(fn ($v) => (int) $v)->intersect($allowed)->all();
        $conversation->labels()->sync($ids);

        return back()->with('success', 'Labels update ho gaye.');
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
        $message = $this->chat->send($conversation, $request->user(), $this->messageData($request));

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
