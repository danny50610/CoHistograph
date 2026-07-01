#!/usr/bin/env bash
# Reproducible end-to-end check that an Apache AGE "read-only" user really
# cannot write data - including via AGE's own functions.
#
# Usage:
#   ./run_all.sh                 # uses docker directly
#   SUDO=sudo ./run_all.sh       # if your docker needs sudo
#
# It (re)creates the container, loads the graph, creates the read-only role,
# and runs every write attempt. Every write MUST fail for the read-only role.
set -euo pipefail

DOCKER="${SUDO:-} docker"
CONTAINER=age-ro
IMAGE=apache/age:latest
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

psql_su()  { $DOCKER exec -i -e PGPASSWORD=postgres  "$CONTAINER" psql -U postgres  -d graphdb "$@"; }
psql_ro()  { $DOCKER exec -i -e PGPASSWORD=readonly  "$CONTAINER" psql -U readonly  -d graphdb "$@"; }

echo "==> (re)starting container"
$DOCKER rm -f "$CONTAINER" >/dev/null 2>&1 || true
$DOCKER run -d --name "$CONTAINER" \
  -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=graphdb \
  -p 5432:5432 "$IMAGE" >/dev/null

echo "==> waiting for postgres"
until $DOCKER exec "$CONTAINER" pg_isready -U postgres -d graphdb >/dev/null 2>&1; do sleep 1; done
sleep 2

echo "==> loading sample graph"
psql_su -v ON_ERROR_STOP=1 -f - < "$HERE/01_setup_graph.sql"

echo "==> creating read-only role"
psql_su -v ON_ERROR_STOP=1 -f - < "$HERE/02_create_readonly_user.sql"

echo "==> read-only role CAN read:"
psql_ro -c "SET search_path = ag_catalog, public;
            SELECT * FROM cypher('history', \$\$ MATCH (n:Person) RETURN n.name \$\$) AS (name agtype);"

echo "==> read-only role write attempts (every line MUST say 'permission denied'):"
psql_ro -f - < "$HERE/03_write_attempts.sql"

echo "==> data is unchanged:"
psql_su -c "SET search_path = ag_catalog, public;
            SELECT * FROM cypher('history', \$\$ MATCH (n:Person) RETURN n.name, n.born \$\$) AS (name agtype, born agtype);"

echo "==> DONE"
