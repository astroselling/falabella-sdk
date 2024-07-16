<?php

use Astroselling\FalabellaSdk\FalabellaSdk;
use Astroselling\FalabellaSdk\Models\FalabellaFeed;

it('can be instanced', function () {
    $sdk = new FalabellaSdk('', '', 'MEX');
    $this->assertInstanceOf(FalabellaSdk::class, $sdk);
});

it('can create a feed', function () {
    $f = FalabellaFeed::factory()->create();
    $this->assertModelExists($f);
});
