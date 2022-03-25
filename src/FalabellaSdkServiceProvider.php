<?php

namespace Astroselling\FalabellaSdk;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FalabellaSdkServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('falabella-sdk')
            ->hasConfigFile('falabellasdk')
            ->hasMigration('create_falabella_feeds_table');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['falabellasdk'];
    }
}
