<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Reminders\PushSender;
use App\Services\Reminders\StudyReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daily goal, reminder time and this device's Web Push registration. */
class LearningSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daily_goal' => ['required', 'integer', Rule::in(User::DAILY_GOALS)],
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_hour' => ['required', 'integer', 'between:0,23'],
            'timezone' => ['nullable', 'timezone:all'],
        ]);
        $request->user()->update($data);

        return back()->with('success', __('messages.settings_saved'));
    }

    /** Store the browser's push endpoint (one row per device; re-subscribing updates the keys). */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            ['public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'], 'content_encoding' => $data['content_encoding'] ?? 'aes128gcm'],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true]);
    }

    /** Send the reminder right now, so the learner can see what it looks like. */
    public function test(Request $request, PushSender $push, StudyReminder $reminder): JsonResponse
    {
        $sent = $push->send($request->user(), $reminder->message($request->user()));

        return response()->json(
            ['sent' => $sent, 'message' => __($sent ? 'messages.push_test_sent' : 'messages.push_test_failed')],
            $sent ? 200 : 422,
        );
    }
}
