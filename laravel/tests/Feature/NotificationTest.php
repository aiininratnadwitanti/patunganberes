<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_notification_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $notification = $user->appNotifications()->create([
            'title' => 'Halo',
            'message' => 'Pesan',
            'type' => 'bill_created',
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_all_notifications_can_be_marked_read_and_cleared(): void
    {
        $user = User::factory()->create();

        $user->appNotifications()->createMany([
            ['title' => 'A', 'message' => 'x', 'type' => 'bill_created'],
            ['title' => 'B', 'message' => 'y', 'type' => 'payment_reported'],
        ]);

        $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $user->appNotifications()->where('is_read', false)->count());

        $this->actingAs($user)->delete(route('notifications.clear'))->assertRedirect();
        $this->assertSame(0, $user->appNotifications()->count());
    }

    public function test_a_user_cannot_open_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $notification = $owner->appNotifications()->create([
            'title' => 'Privat',
            'message' => 'x',
            'type' => 'bill_created',
        ]);

        $this->actingAs($other)
            ->get(route('notifications.open', $notification))
            ->assertForbidden();
    }
}
