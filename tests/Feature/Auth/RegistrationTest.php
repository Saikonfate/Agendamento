<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'matricula' => '12345678',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});