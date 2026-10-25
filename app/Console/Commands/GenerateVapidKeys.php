<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID key pair for Web Push reminders (add the output to .env)';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\RuntimeException $e) {
            $this->error('OpenSSL could not create the key: '.$e->getMessage());
            $this->line('On Windows, point OPENSSL_CONF at the openssl.cnf that ships with PHP, e.g. C:\\php\\extras\\ssl\\openssl.cnf');

            return self::FAILURE;
        }

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
