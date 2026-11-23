<?php

namespace Tests\Feature;

use App\Jobs\ProcessShortBatch;
use App\Models\ShortBatch;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoShortService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShortsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    public function test_clip_windows_are_computed_from_options(): void
    {
        $svc = new VideoShortService;

        $w = $svc->windows(120, ['clip_length' => 30, 'max_shorts' => 10]);
        $this->assertSame([[0.0, 30.0], [30.0, 30.0], [60.0, 30.0], [90.0, 30.0]], $w);

        // max_shorts caps the count
        $this->assertCount(2, $svc->windows(120, ['clip_length' => 30, 'max_shorts' => 2]));

        // intro/outro are skipped
        $w2 = $svc->windows(100, ['clip_length' => 40, 'max_shorts' => 10, 'skip_intro' => 10, 'skip_outro' => 10]);
        $this->assertSame(10.0, $w2[0][0]);
        $this->assertLessThanOrEqual(90.0, $w2[count($w2) - 1][0] + $w2[count($w2) - 1][1]);

        // too short -> no clips
        $this->assertSame([], $svc->windows(2, ['clip_length' => 30, 'max_shorts' => 5]));
    }

    public function test_only_admin_can_open_the_tool(): void
    {
        $user = User::query()->create(['name' => 'U', 'phone' => '03111111111', 'password' => 'secret123']);
        $admin = $this->admin();

        $this->actingAs($user)->get(route('admin.shorts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.shorts.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.shorts.create'))->assertOk();
    }

    public function test_store_saves_source_and_queues_the_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->create('clip.mp4', 2048, 'video/mp4');

        $this->actingAs($this->admin())->post(route('admin.shorts.store'), [
            'source_title' => 'My clips',
            'video' => $file,
            'clip_length' => 30,
            'max_shorts' => 5,
            'skip_intro' => 2,
            'skip_outro' => 2,
            'reframe' => 'blur',
            'category' => 'Comedy',
        ])->assertRedirect();

        $batch = ShortBatch::query()->first();
        $this->assertNotNull($batch);
        $this->assertSame('queued', $batch->status);
        $this->assertSame(30, $batch->options['clip_length']);
        $this->assertSame('blur', $batch->options['reframe']);
        Storage::disk('local')->assertExists($batch->source_path);
        Queue::assertPushed(ProcessShortBatch::class);
    }

    public function test_bulk_publish_activates_selected_shorts(): void
    {
        $admin = $this->admin();
        $batch = ShortBatch::query()->create(['source_title' => 'B', 'status' => 'done', 'created_by' => $admin->id]);
        $a = $this->draftShort($batch);
        $b = $this->draftShort($batch);

        // publish only the first
        $this->actingAs($admin)->post(route('admin.shorts.publish', $batch), ['ids' => [$a->id]])->assertRedirect();
        $this->assertTrue($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);

        // publish all the rest
        $this->actingAs($admin)->post(route('admin.shorts.publish', $batch), ['all' => 1])->assertRedirect();
        $this->assertTrue($b->fresh()->is_active);
    }

    public function test_destroy_removes_batch_and_its_shorts(): void
    {
        $admin = $this->admin();
        $batch = ShortBatch::query()->create(['source_title' => 'B', 'status' => 'done', 'created_by' => $admin->id]);
        $short = $this->draftShort($batch);

        $this->actingAs($admin)->delete(route('admin.shorts.destroy', $batch))->assertRedirect(route('admin.shorts.index'));
        $this->assertDatabaseMissing('short_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('videos', ['id' => $short->id]);
    }

    private function draftShort(ShortBatch $batch): Video
    {
        return Video::query()->create([
            'title' => 'Short', 'file_path' => 'videos/x'.uniqid().'.mp4', 'duration_seconds' => 20,
            'is_active' => false, 'short_batch_id' => $batch->id,
        ]);
    }
}
