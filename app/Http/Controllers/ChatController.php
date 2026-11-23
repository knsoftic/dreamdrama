<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\Request;

/** The member's chat with their sub-admin (support). */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function show(Request $request)
    {
        $conv = $this->chat->conversationFor($request->user());
        if (! $conv) {
            return view('chat.none');
        }

        $this->chat->markRead($conv, $request->user());
        $messages = $conv->messages()->latest('id')->limit(60)->get()->reverse()->values();

        return view('chat.show', [
            'conversation' => $conv->load('staff'),
            'messages' => $messages,
        ]);
    }

    public function messages(Request $request)
    {
        $conv = $this->chat->conversationFor($request->user());
        if (! $conv) {
            return response()->json(['messages' => []]);
        }

        $new = $conv->messages()->where('id', '>', (int) $request->query('after', 0))->orderBy('id')->get();
        if ($new->isNotEmpty()) {
            $this->chat->markRead($conv, $request->user());
        }

        return response()->json(['messages' => $new->map->toWire()]);
    }

    public function send(Request $request)
    {
        $conv = $this->chat->conversationFor($request->user());
        abort_if(! $conv, 404);
        abort_if($conv->blocked, 403, 'Aap is chat me message nahi bhej sakte.');

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message = $this->chat->send($conv, $request->user(), ['type' => 'text', 'body' => $data['body']]);

        return response()->json(['message' => $message->toWire()]);
    }
}
