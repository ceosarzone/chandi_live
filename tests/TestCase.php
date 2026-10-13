<?php

namespace Tests;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected $seed = true;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app->make('config')->get('database.default') !== 'mariadb' || $app->make('config')->get('database.connections.mariadb.database') !== 'chandi_live_test') {
            throw new \RuntimeException('Tests require the dedicated chandi_live_test MariaDB database.');
        }

        $app->make('config')->set('chandi.marketplace_enabled', true);

        return $app;
    }
}
