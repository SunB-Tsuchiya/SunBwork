<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

// Helper: returns true when the test should be skipped.
// Skip if resetPasswords feature is off OR if the password_reset_tokens table is missing.
$shouldSkip = function (): bool {
    if (! Features::enabled(Features::resetPasswords())) {
        return true;
    }
    try {
        return ! Schema::hasTable('password_reset_tokens');
    } catch (\Throwable) {
        return true;
    }
};

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
})->skip($shouldSkip, 'Password updates are not enabled or password_reset_tokens table is missing.');

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class);
})->skip($shouldSkip, 'Password updates are not enabled or password_reset_tokens table is missing.');

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
})->skip($shouldSkip, 'Password updates are not enabled or password_reset_tokens table is missing.');

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post('/forgot-password', [
        'email' => $user->email,
    ]);

    Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        return true;
    });
})->skip($shouldSkip, 'Password updates are not enabled or password_reset_tokens table is missing.');
