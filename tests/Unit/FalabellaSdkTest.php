<?php

use Astroselling\FalabellaSdk\FalabellaSdk;

it('can be instanced', function () {
    $sdk = new FalabellaSdk('', '');
    $this->assertInstanceOf(FalabellaSdk::class, $sdk);
});
