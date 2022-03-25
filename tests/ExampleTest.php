<?php

use Astroselling\FalabellaSdk\Models\FalabellaFeed;

it('can test', function () {
    expect(true)->toBeTrue();
});

it('can create a feed', function () {
    $f = FalabellaFeed::factory()->create();
    $this->assertModelExists($f);
});
