<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const DAILY_GOALS = [10, 20, 30, 50];

    protected $fillable = ['name', 'email', 'password', 'is_admin', 'locale', 'daily_goal', 'reminders_enabled', 'reminder_hour', 'timezone', 'last_reminded_on'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'daily_goal' => 'integer',
            'reminders_enabled' => 'boolean',
            'reminder_hour' => 'integer',
            'last_reminded_on' => 'date',
        ];
    }

    /** The learner's own timezone (set from the browser), falling back to the app's. */
    public function timezoneName(): string
    {
        return $this->timezone ?: config('app.timezone');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function exerciseSessions(): HasMany
    {
        return $this->hasMany(ExerciseSession::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function wordProgress(): HasMany
    {
        return $this->hasMany(UserWordProgress::class);
    }

    public function importPreviews(): HasMany
    {
        return $this->hasMany(ImportPreview::class);
    }
}
