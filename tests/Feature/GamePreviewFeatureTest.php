<?php

it('returns a successful response for game preview', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});