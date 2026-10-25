<?php

namespace App\Services\Reminders;

use App\Models\User;
use App\Notifications\StudyReminderMail;
use App\Services\StatsService;
use Carbon\CarbonInterface;

/**
 * Daily study nudge: at the learner's chosen local hour, if today's goal isn't met yet,
 * send a push message (or an e-mail when no browser is subscribed). At most once per local day.
 */
class StudyReminder
{
    public function __construct(
        private readonly StatsService $stats,
        private readonly PushSender $push,
    ) {}

    public function isDue(User $user, ?CarbonInterface $now = null): bool
    {
        if (! $user->reminders_enabled) {
            return false;
        }
        $local = ($now ?? now())->copy()->setTimezone($user->timezoneName());

        return $local->hour === $user->reminder_hour
            && $user->last_reminded_on?->toDateString() !== $local->toDateString()
            && ! $this->stats->daily($user)['goal_met'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function message(User $user): array
    {
        $locale = $user->locale ?: config('app.locale');
        $daily = $this->stats->daily($user);
        $headline = $this->stats->headline($user);
        $left = max(0, $daily['goal'] - $daily['today']);

        $body = $headline['streak'] > 0
            ? __('messages.reminder_streak', ['streak' => $headline['streak'], 'left' => $left], $locale)
            : __('messages.reminder_goal', ['left' => $left], $locale);
        if ($headline['due'] > 0) {
            $body .= ' '.__('messages.reminder_due', ['due' => $headline['due']], $locale);
        }

        return ['title' => __('messages.reminder_title', [], $locale), 'body' => $body, 'url' => '/kurs/A1'];
    }

    /** @return string 'push' | 'mail' */
    public function send(User $user, ?CarbonInterface $now = null): string
    {
        $message = $this->message($user);
        $channel = $this->push->send($user, $message) > 0 ? 'push' : 'mail';
        if ($channel === 'mail') {
            $user->notify(new StudyReminderMail($message));
        }

        $user->forceFill(['last_reminded_on' => ($now ?? now())->copy()->setTimezone($user->timezoneName())->toDateString()])->save();

        return $channel;
    }
}
