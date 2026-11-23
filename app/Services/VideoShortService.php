<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Cuts an uploaded (copyright-free) source video into vertical 9:16 "shorts" with ffmpeg.
 * No downloading / no external links — it only processes a file the admin uploaded.
 */
class VideoShortService
{
    public function bin(): string
    {
        return (string) config('media.ffmpeg', 'ffmpeg');
    }

    /** Is ffmpeg usable on this machine? */
    public function available(): bool
    {
        try {
            $p = new Process([$this->bin(), '-version']);
            $p->setTimeout(20);
            $p->run();

            return $p->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Source duration in seconds (parsed from ffmpeg output; no ffprobe required). */
    public function duration(string $path): float
    {
        try {
            $p = new Process([$this->bin(), '-hide_banner', '-i', $path]);
            $p->setTimeout(120);
            $p->run();
            if (preg_match('/Duration:\s+(\d+):(\d+):(\d+(?:\.\d+)?)/', $p->getErrorOutput(), $m)) {
                return (int) $m[1] * 3600 + (int) $m[2] * 60 + (float) $m[3];
            }
        } catch (\Throwable) {
        }

        return 0.0;
    }

    /**
     * Clip windows [[start, length], ...] from the batch options.
     * Consecutive clips of `clip_length` seconds, skipping an intro/outro, up to `max_shorts`.
     */
    public function windows(float $duration, array $opt): array
    {
        $len = max(5, (int) ($opt['clip_length'] ?? 40));
        $max = max(1, (int) ($opt['max_shorts'] ?? 10));
        $head = max(0.0, (float) ($opt['skip_intro'] ?? 0));
        $tail = max(0.0, (float) ($opt['skip_outro'] ?? 0));
        $gap = max(0.0, (float) ($opt['gap'] ?? 0));

        if ($duration - $head - $tail < 5) {
            $head = 0.0;
            $tail = 0.0;
        }

        $out = [];
        $t = $head;
        $end = $duration - $tail;
        while ($t + 3 <= $end && count($out) < $max) {
            $l = min($len, $end - $t);
            if ($l < 3) {
                break;
            }
            $out[] = [round($t, 2), round($l, 2)];
            $t += $l + $gap;
        }

        return $out;
    }

    /** Cut one 9:16 short. reframe: 'blur' (fit + blurred background) | 'crop' (centre-crop). */
    public function cut(string $src, float $start, float $len, string $out, string $reframe = 'blur'): bool
    {
        $w = (int) config('media.short_width', 1080);
        $h = (int) config('media.short_height', 1920);

        $args = [$this->bin(), '-hide_banner', '-y', '-ss', (string) $start, '-i', $src, '-t', (string) $len];

        if ($reframe === 'crop') {
            $args = array_merge($args, [
                '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},setsar=1",
            ]);
        } else {
            $args = array_merge($args, [
                '-filter_complex',
                "[0:v]scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},boxblur=42:1[bg];"
                ."[0:v]scale={$w}:{$h}:force_original_aspect_ratio=decrease[fg];"
                .'[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1[v]',
                '-map', '[v]', '-map', '0:a?',
            ]);
        }

        $args = array_merge($args, [
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $out,
        ]);

        return $this->runOk($args, 1800) && is_file($out) && filesize($out) > 0;
    }

    /** One 9:16 thumbnail frame. */
    public function thumb(string $src, float $at, string $out): bool
    {
        $w = (int) config('media.short_width', 1080);
        $h = (int) config('media.short_height', 1920);

        $args = [
            $this->bin(), '-hide_banner', '-y', '-ss', (string) $at, '-i', $src, '-frames:v', '1',
            '-vf', "scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h}", '-q:v', '3', $out,
        ];

        return $this->runOk($args, 120) && is_file($out) && filesize($out) > 0;
    }

    private function runOk(array $cmd, int $timeout): bool
    {
        try {
            $p = new Process($cmd);
            $p->setTimeout($timeout);
            $p->run();

            return $p->getExitCode() === 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
