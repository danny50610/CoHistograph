<?php

namespace App\Listeners;

use Illuminate\Database\Events\ConnectionEstablished;
use Throwable;

class ConfigureApacheAgeConnection
{
    /**
     * Ensure each graph connection session can run cypher() on the first query.
     *
     * Without LOAD 'age', Apache AGE raises:
     * "unhandled cypher(cstring) function call" on the first cypher() call of a session
     * when age is not in shared_preload_libraries.
     */
    public function handle(ConnectionEstablished $event): void
    {
        $graphConnection = (string) config('cohistograph.app.graph.connection-name');

        if ($event->connectionName !== $graphConnection) {
            return;
        }

        if ($event->connection->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $event->connection->statement("LOAD 'age'");
        } catch (Throwable) {
            // Already loaded via shared_preload_libraries, or LOAD is not permitted.
        }

        $event->connection->statement('SET search_path = ag_catalog, "$user", public');
    }
}
