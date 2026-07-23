<?php

test('the root redirects to the dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
