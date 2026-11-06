<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits(): array
    {
        // Forzar SQLite en memoria ANTES de que RefreshDatabase o cualquier trait ejecute migraciones
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
        ]);

        DB::purge('mysql');
        DB::setDefaultConnection('sqlite');

        if (DB::getDefaultConnection() !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Seguridad de Tests: Las pruebas solo pueden ejecutarse sobre SQLite en memoria (:memory:) para proteger la base de datos MySQL.');
        }

        return parent::setUpTraits();
    }
}
