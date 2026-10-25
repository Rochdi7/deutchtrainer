<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Reminders\StudyReminder;
use Illuminate\Console\Command;

/** Runs hourly (routes/console.php): reminds learners whose local reminder hour is now. */
class SendStudyReminders extends Command
{
    protected $signature = 'reminders:send {--user= : Only this user id} {--force : Ignore hour, goal and once-a-day checks}';

    protected $description = 'Send the daily study reminder (web push, e-mail fallback)';

    public function handle(StudyReminder $reminder): int
    {
        $query = User::query()->when($this->option('user'), fn ($q, $id) => $q->whereKey($id));
        if (! $this->option('force')) {
            $query->where('reminders_enabled', true);
        }

        $sent = 0;
        foreach ($query->cursor() as $user) {
            if (! $this->option('force') && ! $reminder->isDue($user)) {
                continue;
            }
            $channel = $reminder->send($user);
            $this->line("{$user->email}: {$channel}");
            $sent++;
        }
        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
