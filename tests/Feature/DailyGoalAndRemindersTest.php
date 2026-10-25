<?php

namespace Tests\Feature;

use App\Models\ExerciseAttempt;
use App\Models\ExerciseSession;
use App\Models\User;
use App\Notifications\StudyReminderMail;
use App\Services\Reminders\PushSender;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DailyGoalAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function earnXp(User $user, int $n, $at = null): void
    {
        $session = ExerciseSession::create(['user_id' => $user->id, 'mode' => 'practice', 'label' => 'x', 'items' => [], 'total' => $n, 'correct' => $n, 'started_at' => $at ?? now()]);
        for ($i = 0; $i < $n; $i++) {
            ExerciseAttempt::create(['user_id' => $user->id, 'exercise_session_id' => $session->id, 'item_id' => "i$i", 'type' => 'mc', 'given' => 'a', 'is_correct' => true, 'answered_at' => $at ?? now()]);
        }
    }

    public function test_daily_goal_counts_only_todays_correct_answers(): void
    {
        $user = User::factory()->create(['daily_goal' => 10]);
        $this->earnXp($user, 4, now()->subDay());
        $this->earnXp($user, 6);

        $daily = app(StatsService::class)->daily($user);
        $this->assertSame(['today' => 6, 'goal' => 10, 'goal_met' => false], $daily);

        $this->earnXp($user, 4);
        $this->assertTrue(app(StatsService::class)->daily($user)['goal_met']);
    }

    public function test_headline_stats_are_shared_with_every_page(): void
    {
        $user = User::factory()->create(['daily_goal' => 30]);
        $this->earnXp($user, 2);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('stats.today', 2)->where('stats.goal', 30)->has('push.vapid_public_key'));
    }

    public function test_learning_settings_are_validated_and_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('settings.learning'), ['daily_goal' => 15, 'reminders_enabled' => true, 'reminder_hour' => 9])
            ->assertSessionHasErrors('daily_goal');

        $this->actingAs($user)->patch(route('settings.learning'), ['daily_goal' => 30, 'reminders_enabled' => true, 'reminder_hour' => 9, 'timezone' => 'Africa/Casablanca'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame([30, true, 9, 'Africa/Casablanca'], [$user->daily_goal, $user->reminders_enabled, $user->reminder_hour, $user->timezone]);
    }

    public function test_push_subscriptions_are_stored_per_endpoint(): void
    {
        $user = User::factory()->create();
        $payload = ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'key1', 'auth' => 'auth1']];

        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('push.subscribe'), ['keys' => ['p256dh' => 'key2', 'auth' => 'auth2']] + $payload)->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count());
        $this->assertSame('key2', $user->pushSubscriptions()->first()->public_key);

        $this->actingAs($user)->deleteJson(route('push.unsubscribe'), ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_reminder_is_sent_once_at_the_local_hour_only_while_the_goal_is_open(): void
    {
        Notification::fake();
        $push = $this->mock(PushSender::class);
        $push->shouldReceive('send')->andReturn(0); // no browser subscribed → e-mail fallback

        // 18:00 in Casablanca (UTC+1 in late October 2026)
        $this->travelTo(now()->setTimezone('Africa/Casablanca')->setTime(18, 10)->utc());
        $due = User::factory()->create(['reminders_enabled' => true, 'reminder_hour' => 18, 'timezone' => 'Africa/Casablanca', 'daily_goal' => 10]);
        $otherHour = User::factory()->create(['reminders_enabled' => true, 'reminder_hour' => 8, 'timezone' => 'Africa/Casablanca']);
        $disabled = User::factory()->create(['reminders_enabled' => false, 'reminder_hour' => 18, 'timezone' => 'Africa/Casablanca']);
        $goalMet = User::factory()->create(['reminders_enabled' => true, 'reminder_hour' => 18, 'timezone' => 'Africa/Casablanca', 'daily_goal' => 10]);
        $this->earnXp($goalMet, 10);

        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertSentTo($due, StudyReminderMail::class);
        Notification::assertNotSentTo([$otherHour, $disabled, $goalMet], StudyReminderMail::class);

        // A second run in the same hour must not remind again.
        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertSentToTimes($due, StudyReminderMail::class, 1);
    }

    public function test_reminder_prefers_push_when_a_browser_is_subscribed(): void
    {
        Notification::fake();
        $user = User::factory()->create(['locale' => 'en', 'daily_goal' => 20]);
        $push = $this->mock(PushSender::class);
        $push->shouldReceive('send')->once()->withArgs(fn ($u, $m) => $u->is($user) && str_contains($m['body'], '20 XP'))->andReturn(1);

        $this->artisan('reminders:send', ['--user' => $user->id, '--force' => true])->expectsOutputToContain('push')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertNotNull($user->fresh()->last_reminded_on);
    }
}
