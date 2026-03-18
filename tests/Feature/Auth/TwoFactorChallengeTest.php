<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    /** @var \Tests\TestCase $this */
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
});