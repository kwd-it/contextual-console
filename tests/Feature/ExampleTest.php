<?php

test('the root path redirects to the dashboard route', function () {
    $this->get('/')
        ->assertRedirect(route('dashboard.index'));
});
