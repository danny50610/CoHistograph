# PostgreSQL + Apache AGE: a truly read-only user

This folder documents how to create a **read-only** database user for a
PostgreSQL database that uses the [Apache AGE](https://age.apache.org/) graph
extension, and **proves** that the user cannot write data — not even through
AGE's own Cypher / graph functions.

Relevant to CoHistograph because a knowledge-graph platform typically wants to
expose a safe "query only" role (e.g. for an analytics/query API) that can run
Cypher `MATCH`/`RETURN` but can never mutate the graph.

## TL;DR findings

- A read-only user is achieved with **standard PostgreSQL privileges** — there
  is no AGE-specific "read only" switch.
- The key insight: **all 348 functions in `ag_catalog` are `SECURITY INVOKER`
  (zero `SECURITY DEFINER`).** So `cypher()`, `create_graph()`,
  `create_vlabel()`, etc. run with the *caller's* privileges. A Cypher
  `CREATE`/`SET`/`DELETE` is ultimately an `INSERT`/`UPDATE`/`DELETE` on the
  label tables; if the role lacks that table privilege, the write fails.
- Therefore Cypher is **not** a breakthrough: every write path (SQL or Cypher)
  is gated by ordinary table/schema/database privileges.

## Files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Start PostgreSQL 18 + Apache AGE (`apache/age:latest`). |
| `01_setup_graph.sql` | Create the `age` extension + sample `history` graph. |
| `02_create_readonly_user.sql` | Create the `readonly` login role (read grants only). |
| `03_write_attempts.sql` | 11 write attacks that must all be denied. |
| `04_read_tests.sql` | 5 positive read tests that must all succeed. |
| `run_all.sh` | One-shot: (re)create container, load data, create role, run all attacks. |

## How to run

```bash
# Option A: docker compose
docker compose -f docs/age-readonly/docker-compose.yml up -d
# then run the .sql files in order (see run_all.sh for exact commands)

# Option B: one shot (recreates the container each time)
SUDO=sudo docs/age-readonly/run_all.sh
```

## The read-only grant set

```sql
CREATE ROLE readonly WITH LOGIN PASSWORD 'readonly';
GRANT CONNECT ON DATABASE graphdb TO readonly;

-- Needed to call cypher() and read graph metadata.
GRANT USAGE  ON SCHEMA ag_catalog TO readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA ag_catalog TO readonly;

-- The graph's vertex/edge tables live in a schema named after the graph.
GRANT USAGE  ON SCHEMA history TO readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA history TO readonly;
ALTER DEFAULT PRIVILEGES IN SCHEMA history GRANT SELECT ON TABLES TO readonly;
```

Deliberately **NOT** granted: `INSERT`/`UPDATE`/`DELETE` on any table, `CREATE`
on the database (blocks `create_graph`), `CREATE` on the graph schema (blocks
`create_vlabel`/`create_elabel`), sequence `USAGE`, and membership in
`pg_read_server_files` (blocks `load_labels_from_file`).

## What was verified

The `readonly` role **can** read:

```
SELECT * FROM cypher('history', $$ MATCH (n:Person) RETURN n.name $$) AS (name agtype);
-- "Napoleon", "Wellington"
```

Note: it does **not** need `LOAD 'age'` (which is superuser-only). AGE's C
functions auto-load their backing library on first call, so plain Cypher
queries work for the read-only role.

### Positive read tests (all succeed — `04_read_tests.sql`)

| # | Read query (as `readonly`) | Result |
|---|----------------------------|--------|
| R1 | Cypher `MATCH … WHERE n.born < 1800 RETURN …` | Napoleon 1769, Wellington 1769 |
| R2 | Cypher `RETURN count(n)` | `2` |
| R3 | Cypher edge traversal `(a)-[r:FOUGHT]->(b)` | Napoleon → Waterloo → Wellington |
| R4 | Cypher `ORDER BY n.name DESC LIMIT 1` | Wellington |
| R5 | `SELECT … FROM ag_catalog.ag_graph` + `SELECT count(*) FROM history."Person"` | `history`; `2` |

Every write below was **denied**:

| # | Attack (as `readonly`) | Result |
|---|------------------------|--------|
| 1 | Cypher `CREATE (:Person …)` | `permission denied for table Person` |
| 2 | Cypher `SET n.born = 0` | `permission denied for table Person` |
| 3 | Cypher `DETACH DELETE n` | `permission denied for table Person` |
| 4 | Cypher `CREATE (a)-[:HACK]->(b)` | `permission denied for schema history` |
| 5 | Plain SQL `INSERT INTO history."Person"` | `permission denied for table Person` |
| 6 | Plain SQL `UPDATE history."Person"` | `permission denied for table Person` |
| 7 | Plain SQL `DELETE FROM history."Person"` | `permission denied for table Person` |
| 8 | `create_graph('evil')` | `permission denied for database graphdb` |
| 9 | `create_vlabel('history','EvilLabel')` | `permission denied for schema history` |
| 10 | `drop_graph('history', true)` | `must be owner of sequence _label_id_seq` |
| 11 | `INSERT INTO ag_catalog.ag_graph …` | `permission denied for table ag_graph` |
| 12 | `load_labels_from_file(...,'/etc/passwd')` | `permission denied to LOAD from a file` (needs `pg_read_server_files`) |
| 13 | `create_elabel('history','EVIL')` | `permission denied for schema history` |

**Negative control:** an otherwise-identical `writer` role *with* `INSERT` on
the label tables ran the exact same Cypher `CREATE` and it **succeeded**
(then was cleaned up). This proves the read-only failures are real denials, not
Cypher silently doing nothing.

After all attacks, the graph is byte-for-byte unchanged (2 `Person` nodes,
1 `FOUGHT` edge).

## Gotchas / hardening notes

- **`LOAD 'age'` is superuser-only.** Don't put it in read-only client code.
  If you rely on the parser hook (some Cypher forms), add `age` to
  `session_preload_libraries`/`shared_preload_libraries` in
  `postgresql.conf` instead of granting `LOAD`.
- **Grant on `ag_catalog` is required** — without `USAGE`+`SELECT` there, the
  role can't even call `cypher()`.
- **Per-graph schemas:** every graph gets its own schema (here `history`). New
  graphs need their own `GRANT SELECT`. Script this if graphs are created
  dynamically.
- **Never** grant the read-only role `pg_read_server_files`, `CREATE` on the
  database, or any table `INSERT/UPDATE/DELETE`; each re-opens a write path
  through an AGE helper function.
