<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('daily_goal')->default(20)->after('locale'); // XP (correct answers) per day
            $table->boolean('reminders_enabled')->default(false)->after('daily_goal');
            $table->unsignedTinyInteger('reminder_hour')->default(18)->after('reminders_enabled'); // local hour 0–23
            $table->string('timezone', 64)->nullable()->after('reminder_hour');
            $table->date('last_reminded_on')->nullable()->after('timezone'); // local date of the last reminder sent
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_goal', 'reminders_enabled', 'reminder_hour', 'timezone', 'last_reminded_on']);
        });
    }
};
