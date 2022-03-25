<?php

namespace Astroselling\FalabellaSdk\Tests;

use Astroselling\FalabellaSdk\FalabellaSdkServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Astroselling\\FalabellaSdk\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            FalabellaSdkServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        include_once __DIR__.'/../database/migrations/2022_22_03_000000create_falabella_feeds_table.php';
        (new \CreateFalabellaFeedsTable())->up();
    }
}
