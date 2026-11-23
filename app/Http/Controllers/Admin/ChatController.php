<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatLabel;
use App\Models\Conversation;
use Illuminate\Http\Request;

/** Super-admin oversight: read every chat, and manage global labels. */
class ChatController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->when($request->query('q'), fn ($qq, $s) => $qq
                ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
                ->orWhereHas('staff', fn ($u) => $u->where('name', 'like', "%{$s}%")))
            ->when($request->integer('label'), fn ($qq, $id) => $qq->whereHas('labels', fn ($l) => $l->whereKey($id)))
            ->with(['user', 'staff', 'latestMessage', 'labels'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.chats.index', [
            'conversations' => $conversations,
            'labels' => ChatLabel::query()->whereNull('owner_id')->orderBy('name')->get(),
        ]);
    }

    public function show(Conversation $conversation)
    {
        return view('admin.chats.show', [
            'conversation' => $conversation->load('user', 'staff', 'labels'),
            'messages' => $conversation->messages()->with('sender')->latest('id')->limit(300)->get()->reverse()->values(),
            'labels' => ChatLabel::query()->whereNull('owner_id')->orderBy('name')->get(),
        ]);
    }

    public function labelStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        ChatLabel::query()->create(['owner_id' => null, 'name' => $data['name'], 'color' => ($data['color'] ?? null) ?: '#8b5cf6']);

        return back()->with('success', 'Global label ban gaya — ye sab sub-admins ko milega.');
    }

    public function labelDestroy(ChatLabel $label)
    {
        abort_unless($label->owner_id === null, 403);   // only global labels here
        $label->delete();

        return back()->with('success', 'Global label delete ho gaya.');
    }

    public function syncLabels(Request $request, Conversation $conversation)
    {
        $globalIds = ChatLabel::query()->whereNull('owner_id')->pluck('id');
        $keep = $conversation->labels()->whereNotNull('owner_id')->pluck('chat_labels.id');   // keep the sub-admin's own labels
        $ids = collect($request->input('labels', []))->map(fn ($v) => (int) $v)->intersect($globalIds)->merge($keep)->unique()->all();
        $conversation->labels()->sync($ids);

        return back()->with('success', 'Labels update ho gaye.');
    }
}
