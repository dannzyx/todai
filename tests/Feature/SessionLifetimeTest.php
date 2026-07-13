<?php

it('keeps users logged in for 30 days', function () {
    expect(config('session.lifetime'))->toBe(43200);
});
