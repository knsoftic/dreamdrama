<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// A chat thread — only its two participants (the member and their staff) may listen.
Broadcast::channel('conversation.{id}', function ($user, $id) {
    $conversation = \App\Models\Conversation::find($id);

    return $conversation && $conversation->hasParticipant($user);
});
