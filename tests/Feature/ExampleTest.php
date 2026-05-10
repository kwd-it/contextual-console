<?php

test('the root path redirects to the sources index route', function () {
    $this->get('/')
        ->assertRedirect(route('sources.index'));
});
