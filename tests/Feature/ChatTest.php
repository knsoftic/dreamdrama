<?php

namespace Tests\Feature;

use App\Models\ChatLabel;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function subAdmin(): User
    {
        $u = User::query()->create(['name' => 'Boss', 'phone' => '0300'.random_int(1000000, 9999999), 'password' => 'secret123']);
        $u->forceFill(['role' => 'sub_admin', 'referral_code' => 'B'.random_int(1000, 9999)])->save();

        return $u;
    }

    private function member(User $boss): User
    {
        return User::query()->create([
            'name' => 'Member', 'phone' => '0301'.random_int(1000000, 9999999), 'password' => 'secret123',
            'referred_by' => $boss->id, 'sub_admin_id' => $boss->id,
        ]);
    }

    private function admin(): User
    {
        $u = User::query()->create(['name' => 'Boss Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    public function test_member_sends_a_message_to_their_sub_admin(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);

        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Mera withdrawal atka hai'])
            ->assertOk()->assertJsonPath('message.body', 'Mera withdrawal atka hai');

        $conv = Conversation::query()->first();
        $this->assertSame($member->id, $conv->user_id);
        $this->assertSame($boss->id, $conv->staff_id);
        $this->assertSame(1, $conv->staff_unread);
        $this->assertSame(0, $conv->user_unread);
    }

    public function test_sub_admin_sees_inbox_and_can_reply(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);
        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Hello']);
        $conv = Conversation::query()->first();

        $this->actingAs($boss)->get(route('team.chats.index'))->assertOk()->assertSee($member->name);

        // Opening the chat clears the staff's unread.
        $this->actingAs($boss)->get(route('team.chats.show', $conv))->assertOk();
        $this->assertSame(0, $conv->fresh()->staff_unread);

        // Replying raises the member's unread.
        $this->actingAs($boss)->postJson(route('team.chats.send', $conv), ['body' => 'Dekh raha hun'])->assertOk();
        $this->assertSame(1, $conv->fresh()->user_unread);
    }

    public function test_block_stops_the_member_from_sending(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);
        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Hi']);
        $conv = Conversation::query()->first();

        $this->actingAs($boss)->post(route('team.chats.block', $conv))->assertRedirect();
        $this->assertTrue($conv->fresh()->blocked);

        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Phir se'])->assertForbidden();
    }

    public function test_sub_admin_cannot_open_another_staffs_conversation(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);
        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Hi']);
        $conv = Conversation::query()->first();

        $other = $this->subAdmin();
        $this->actingAs($other)->get(route('team.chats.show', $conv))->assertForbidden();
    }

    public function test_polling_returns_only_newer_messages(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);
        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'one']);
        $conv = Conversation::query()->first();
        $firstId = $conv->messages()->first()->id;
        $this->actingAs($boss)->postJson(route('team.chats.send', $conv), ['body' => 'two']);

        $this->actingAs($member)->getJson(route('chat.messages').'?after='.$firstId)
            ->assertOk()->assertJsonPath('messages.0.body', 'two')->assertJsonCount(1, 'messages');
    }

    public function test_member_can_send_an_image(): void
    {
        Storage::fake('public');
        $member = $this->member($this->subAdmin());

        $res = $this->actingAs($member)->post(route('chat.send'), [
            'attachment' => UploadedFile::fake()->image('pic.jpg'),
        ])->assertOk();

        $this->assertSame('image', $res->json('message.type'));
        $path = Conversation::query()->first()->messages()->first()->attachment_path;
        Storage::disk('public')->assertExists($path);
    }

    public function test_member_can_send_a_voice_note(): void
    {
        Storage::fake('public');
        $member = $this->member($this->subAdmin());

        $res = $this->actingAs($member)->post(route('chat.send'), [
            'attachment' => UploadedFile::fake()->create('voice.webm', 80, 'audio/webm'),
            'duration' => 7,
        ])->assertOk();

        $this->assertSame('voice', $res->json('message.type'));
        $this->assertSame(7, Conversation::query()->first()->messages()->first()->meta['duration']);
    }

    public function test_sub_admin_creates_assigns_and_filters_by_label(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);
        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Hi']);
        $conv = Conversation::query()->first();

        $this->actingAs($boss)->post(route('team.chat-labels.store'), ['name' => 'VIP', 'color' => '#ff0000'])->assertRedirect();
        $label = ChatLabel::query()->first();
        $this->assertSame($boss->id, $label->owner_id);

        $this->actingAs($boss)->post(route('team.chats.labels', $conv), ['labels' => [$label->id]])->assertRedirect();
        $this->assertTrue($conv->fresh()->labels->contains($label->id));

        $this->actingAs($boss)->get(route('team.chats.index', ['label' => $label->id]))->assertOk()->assertSee($member->name);
        $this->actingAs($boss)->get(route('team.chats.index', ['label' => 99999]))->assertOk()->assertDontSee($member->name);
    }

    public function test_sub_admin_cannot_delete_a_global_or_others_label(): void
    {
        $boss = $this->subAdmin();
        $otherLabel = ChatLabel::query()->create(['owner_id' => $this->subAdmin()->id, 'name' => 'X']);
        $global = ChatLabel::query()->create(['owner_id' => null, 'name' => 'G']);

        $this->actingAs($boss)->delete(route('team.chat-labels.destroy', $otherLabel))->assertForbidden();
        $this->actingAs($boss)->delete(route('team.chat-labels.destroy', $global))->assertForbidden();
    }

    public function test_super_admin_global_label_and_oversight(): void
    {
        $boss = $this->subAdmin();
        $member = $this->member($boss);
        $this->actingAs($member)->postJson(route('chat.send'), ['body' => 'Hi']);
        $conv = Conversation::query()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.chat-labels.store'), ['name' => 'Priority'])->assertRedirect();
        $global = ChatLabel::query()->whereNull('owner_id')->first();
        $this->assertNotNull($global);
        $this->assertTrue(ChatLabel::query()->availableFor($boss)->get()->contains($global->id));   // global reaches sub-admins

        // Oversight: admin reads every chat.
        $this->actingAs($admin)->get(route('admin.chats.index'))->assertOk()->assertSee($member->name);
        $this->actingAs($admin)->get(route('admin.chats.show', $conv))->assertOk()->assertSee('Hi');

        $this->actingAs($admin)->post(route('admin.chats.labels', $conv), ['labels' => [$global->id]])->assertRedirect();
        $this->assertTrue($conv->fresh()->labels->contains($global->id));
    }
}
