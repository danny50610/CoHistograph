<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Undo apache-age-driver's HTTP search_path mutation on the default connection.
 *
 * Applied only to OAuth routes (Passport + MCP registration). That package runs
 * `SET SESSION search_path = ag_catalog, public` after connect, so current_schema()
 * becomes ag_catalog and Schema::getColumnListing() returns []. Passport then
 * assumes the legacy oauth_clients columns and INSERT fails.
 */
class RestoreDefaultPgsqlSearchPath
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            $searchPath = $connection->getConfig('search_path') ?? 'public';

            if (is_array($searchPath)) {
                $searchPath = implode(', ', $searchPath);
            }

            $connection->statement("set search_path to {$searchPath}");
        }

        return $next($request);
    }
}
