<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@deutschtrainer.local'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'rochdi.karouali1234@gmail.com'],
            [
                'name' => 'Rochdi',
                'password' => '1234',
                'is_admin' => true,
                'locale' => 'en',
                'email_verified_at' => now(),
            ]
        );

        $this->call(LektionOneSeeder::class);
        $this->call(A1CourseSeeder::class);
    }
}
