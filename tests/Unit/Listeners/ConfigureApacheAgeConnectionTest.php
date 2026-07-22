<?php

namespace Tests\Unit\Listeners;

use App\Listeners\ConfigureApacheAgeConnection;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use RuntimeException;
use Tests\TestCase;

class ConfigureApacheAgeConnectionTest extends TestCase
{
    public function test_loads_age_and_sets_search_path_for_graph_connection(): void
    {
        config(['cohistograph.app.graph.connection-name' => 'pgsql-age']);

        $connection = new FakeApacheAgeConnection('pgsql-age');
        $event = new ConnectionEstablished($connection);

        (new ConfigureApacheAgeConnection)->handle($event);

        $this->assertSame([
            "LOAD 'age'",
            'SET search_path = ag_catalog, "$user", public',
        ], $connection->statements);
    }

    public function test_ignores_non_graph_connections(): void
    {
        config(['cohistograph.app.graph.connection-name' => 'pgsql-age']);

        $connection = new FakeApacheAgeConnection('pgsql');
        $event = new ConnectionEstablished($connection);

        (new ConfigureApacheAgeConnection)->handle($event);

        $this->assertSame([], $connection->statements);
    }

    public function test_continues_when_load_age_fails(): void
    {
        config(['cohistograph.app.graph.connection-name' => 'pgsql-age']);

        $connection = new FakeApacheAgeConnection('pgsql-age', failLoadAge: true);
        $event = new ConnectionEstablished($connection);

        (new ConfigureApacheAgeConnection)->handle($event);

        $this->assertSame([
            "LOAD 'age'",
            'SET search_path = ag_catalog, "$user", public',
        ], $connection->statements);
    }
}

class FakeApacheAgeConnection extends Connection
{
    /** @var list<string> */
    public array $statements = [];

    public function __construct(
        private string $fakeName,
        private bool $failLoadAge = false,
    ) {
        // Skip parent constructor; this fake only supports listener calls.
    }

    public function getName(): string
    {
        return $this->fakeName;
    }

    public function getDriverName(): string
    {
        return 'pgsql';
    }

    public function statement($query, $bindings = []): bool
    {
        $this->statements[] = (string) $query;

        if ($this->failLoadAge && $query === "LOAD 'age'") {
            throw new RuntimeException('already loaded');
        }

        return true;
    }
}
