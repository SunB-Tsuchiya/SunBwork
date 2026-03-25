<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    // The root path redirects unauthenticated guests to the login page.
    $response->assertStatus(302);
    $response->assertRedirect(route('login'));
});
