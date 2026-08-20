-- 02_create_readonly_user.sql
-- Run as a superuser (postgres). Creates a login role that should only be able
-- to READ the AGE graph. The role is granted:
--   * CONNECT on the database
--   * USAGE + SELECT on ag_catalog (needed to call cypher()/read metadata)
--   * USAGE + SELECT on the graph schema (history)
-- It is deliberately NOT granted INSERT/UPDATE/DELETE anywhere, and NOT granted
-- CREATE on the database (so it cannot create new graphs/schemas).

LOAD 'age';
SET search_path = ag_catalog, "$user", public;

DROP ROLE IF EXISTS readonly;
CREATE ROLE readonly WITH LOGIN PASSWORD 'readonly';

GRANT CONNECT ON DATABASE graphdb TO readonly;

-- ag_catalog: needed so the role can call cypher() and read graph metadata.
GRANT USAGE ON SCHEMA ag_catalog TO readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA ag_catalog TO readonly;

-- The graph schema holds the actual vertex/edge tables.
GRANT USAGE ON SCHEMA history TO readonly;
GRANT SELECT ON ALL TABLES IN SCHEMA history TO readonly;

-- Make sure future label tables created in this graph are also readable
-- (has no effect on write privileges).
ALTER DEFAULT PRIVILEGES IN SCHEMA history GRANT SELECT ON TABLES TO readonly;
