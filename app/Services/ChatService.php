<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

class ChatService
{
    /** The staff (the member's sub-admin, else the super-admin) who handles this member. */
    public function staffFor(User $member): ?User
    {
        if ($member->sub_admin_id) {
            $staff = User::query()->whereKey($member->sub_admin_id)->where('role', 'sub_admin')->first();
            if ($staff) {
                return $staff;
            }
        }

        return User::query()->where('role', 'admin')->orderBy('id')->first();
    }

    /** The member's single conversation with their staff (created on first open). */
    public function conversationFor(User $member): ?Conversation
    {
        if ($member->isStaff()) {
            return null;   // staff use their inbox, not this
        }

        $staff = $this->staffFor($member);
        if (! $staff || $staff->id === $member->id) {
            return null;
        }

        return Conversation::query()->firstOrCreate(['user_id' => $member->id, 'staff_id' => $staff->id]);
    }

    public function send(Conversation $conv, User $sender, array $data): Message
    {
        $message = $conv->messages()->create([
            'sender_id' => $sender->id,
            'type' => $data['type'] ?? 'text',
            'body' => $data['body'] ?? null,
            'attachment_path' => $data['attachment_path'] ?? null,
            'meta' => $data['meta'] ?? null,
        ]);

        $fromStaff = $sender->id === $conv->staff_id;
        $conv->forceFill([
            'last_message_at' => $message->created_at,
            'user_unread' => (int) $conv->user_unread + ($fromStaff ? 1 : 0),
            'staff_unread' => (int) $conv->staff_unread + ($fromStaff ? 0 : 1),
        ])->save();

        try {
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            // A down broadcaster (Reverb off) must never break sending a message.
        }

        $url = $fromStaff ? route('chat') : route('team.chats.show', $conv);
        $this->notify($conv, $sender, $message, $url);

        return $message;
    }

    public function markRead(Conversation $conv, User $reader): void
    {
        $isStaff = $reader->id === $conv->staff_id;
        $conv->forceFill($isStaff ? ['staff_unread' => 0] : ['user_unread' => 0])->save();
        $conv->messages()->where('sender_id', '!=', $reader->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    private function notify(Conversation $conv, User $sender, Message $message, string $url): void
    {
        $recipient = $sender->id === $conv->staff_id ? $conv->user : $conv->staff;
        if (! $recipient) {
            return;
        }
        // Staff muted this chat -> no push to the staff side.
        if ($conv->muted && $recipient->id === $conv->staff_id) {
            return;
        }

        $tokens = DeviceToken::query()->where('user_id', $recipient->id)->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        $preview = match ($message->type) {
            'image' => '📷 Photo',
            'voice' => '🎤 Voice message',
            default => Str::limit((string) $message->body, 60),
        };

        try {
            $fcm = app(FcmService::class);
            if ($fcm->isConfigured()) {
                $fcm->send($tokens, $sender->name, $preview, $url);
            }
        } catch (\Throwable $e) {
            // A push failure must never block sending a message.
        }
    }
}
