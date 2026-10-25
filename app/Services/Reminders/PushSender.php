<?php

namespace App\Services\Reminders;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends Web Push messages to every browser a user enabled notifications on.
 * Subscriptions the push service reports as expired are deleted.
 */
class PushSender
{
    public function configured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  array{title: string, body: string, url?: string}  $message
     * @return int number of browsers the message was delivered to
     */
    public function send(User $user, array $message): int
    {
        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty() || ! $this->configured()) {
            return 0;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]);

        $payload = json_encode($message + ['url' => '/kurs/A1'], JSON_UNESCAPED_UNICODE);
        foreach ($subscriptions as $s) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->public_key,
                'authToken' => $s->auth_token,
                'contentEncoding' => $s->content_encoding,
            ]), $payload, ['TTL' => 4 * 3600]);
        }

        $sent = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }

        return $sent;
    }
}
