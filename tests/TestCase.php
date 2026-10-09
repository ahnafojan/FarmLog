<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing') || $app['config']->get('database.default') !== 'mysql'
            || ! str_ends_with($app['config']->get('database.connections.mysql.database'), '_testing')) {
            throw new RuntimeException('Tests require a dedicated MySQL database ending in _testing.');
        }

        return $app;
    }
}
