<?php

test('returns a successful response', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('home'));

    $response->assertOk();
});