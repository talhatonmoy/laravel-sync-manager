<?php

namespace DeployCar\LaravelSyncManager\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notify(string $subject, array $payload): void
    {
        $email = config('sync.advanced.notifications.email');
        $webhook = config('sync.advanced.notifications.webhook');
        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($email) {
            rescue(static function () use ($email, $subject, $body): void {
                Mail::raw($body, static function ($message) use ($email, $subject): void {
                    $message->to($email)->subject($subject);
                });
            }, report: false);
        }

        if ($webhook) {
            rescue(static fn () => Http::acceptJson()->post($webhook, [
                'subject' => $subject,
                'payload' => $payload,
            ]), report: false);
        }

        Log::info($subject, $payload);
    }
}
