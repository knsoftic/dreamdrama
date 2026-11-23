<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait BuildsChatMessage
{
    /** Validate the request and return the data for ChatService::send (text | image | voice). */
    protected function messageData(Request $request): array
    {
        $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:15360'],   // 15 MB
            'duration' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mime = (string) $file->getMimeType();
            $isImage = str_starts_with($mime, 'image/');
            $isAudio = str_starts_with($mime, 'audio/') || $mime === 'video/webm';   // MediaRecorder webm/opus

            abort_unless($isImage || $isAudio, 422, 'Sirf image ya voice message bheja ja sakta hai.');

            return [
                'type' => $isImage ? 'image' : 'voice',
                'body' => $request->filled('body') ? Str::limit((string) $request->input('body'), 4000, '') : null,
                'attachment_path' => $file->store('chat', 'public'),
                'meta' => $request->filled('duration') ? ['duration' => (int) $request->input('duration')] : null,
            ];
        }

        abort_if(! $request->filled('body'), 422, 'Message khali nahi ho sakta.');

        return ['type' => 'text', 'body' => (string) $request->input('body')];
    }
}
