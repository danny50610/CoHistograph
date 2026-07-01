# AGENTS.md

CoHistograph — a collaborative platform for building, managing, and exploring
historical event knowledge graphs (Laravel 12 / PHP 8.2 skeleton).

## Cursor Cloud specific instructions

### Scope note
The Apache AGE read-only investigation under `docs/age-readonly/` is the work
that has been set up and verified in this environment. The Laravel PHP stack
(PHP 8.2, Composer) is **not** installed here — that was intentionally out of
scope. If you need the Laravel app, install PHP 8.2+ and Composer, then run
`composer install` and `npm install` (Node 22 + npm are already present).

### Docker + PostgreSQL + Apache AGE
Docker is a system dependency and is installed once per VM (not by the update
script). If `docker` is missing on a fresh pod, install Docker CE and, for
docker-in-docker here, configure `fuse-overlayfs` storage-driver with
`containerd-snapshotter: false` in `/etc/docker/daemon.json`, set
`iptables-legacy`, then start the daemon with `sudo dockerd` (run it in a tmux
session — there is no systemd here).

- Image: `apache/age:latest` (ships PostgreSQL 18 + AGE).
- Start the graph DB + run the full read-only proof in one shot:
  `SUDO=sudo docs/age-readonly/run_all.sh` (recreates the `age-ro` container
  each run). See `docs/age-readonly/README.md` for details and findings.

### Non-obvious AGE gotchas (learned during setup)
- `docker exec` needs `-i` to pipe SQL via stdin/heredoc; without it psql gets
  no input and silently exits 0.
- `LOAD 'age';` is **superuser-only** ("access to library age is not allowed").
  Read-only roles do NOT need it — AGE's C functions auto-load on first call.
  If you need the parser hook, preload `age` via `session_preload_libraries`
  instead of granting LOAD.
- A read-only AGE role needs `USAGE`+`SELECT` on `ag_catalog` AND on each
  per-graph schema (a graph named `history` => schema `history`). New graphs =>
  new schema => new `GRANT SELECT`.
- Read-only is enforced purely by standard privileges: all `ag_catalog`
  functions are `SECURITY INVOKER`, so `cypher()` writes run as the caller and
  fail without table `INSERT/UPDATE/DELETE`. Never grant such a role
  `pg_read_server_files`, `CREATE` on the database, or table DML.
