<?php

namespace Dzava\GlobalSearch\Tests;

use Dzava\GlobalSearch\GlobalSearchServiceProvider;
use Illuminate\Support\Arr;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->withFactories(__DIR__ . '/database/factories');
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [GlobalSearchServiceProvider::class];
    }

    protected function assertArrayKeys($expected, $actual)
    {
        $expected = Arr::wrap($expected);
        $actual = Arr::wrap($actual);
        $actual = array_keys($actual);
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);

        return $this;
    }
}
