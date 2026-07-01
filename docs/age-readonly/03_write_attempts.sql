-- 03_write_attempts.sql
-- Run as the read-only role. Every statement here is a WRITE and MUST fail.
-- We run each in its own transaction so one failure does not abort the rest.
-- Note: no `LOAD 'age'` here on purpose (non-superusers cannot LOAD; the C
-- functions auto-load their backing library on first call).

SET search_path = ag_catalog, "$user", public;

\echo '--- [1] Cypher CREATE node (INSERT into history."Person") ---'
BEGIN;
SELECT * FROM cypher('history', $$ CREATE (:Person {name:'Hacker'}) $$) AS (v agtype);
ROLLBACK;

\echo '--- [2] Cypher SET (UPDATE existing node) ---'
BEGIN;
SELECT * FROM cypher('history', $$ MATCH (n:Person {name:'Napoleon'}) SET n.born = 0 $$) AS (v agtype);
ROLLBACK;

\echo '--- [3] Cypher DELETE ---'
BEGIN;
SELECT * FROM cypher('history', $$ MATCH (n:Person {name:'Napoleon'}) DETACH DELETE n $$) AS (v agtype);
ROLLBACK;

\echo '--- [4] Cypher CREATE edge ---'
BEGIN;
SELECT * FROM cypher('history', $$ MATCH (a:Person),(b:Person) CREATE (a)-[:HACK]->(b) $$) AS (v agtype);
ROLLBACK;

\echo '--- [5] Plain SQL INSERT into label table ---'
BEGIN;
INSERT INTO history."Person" (properties) VALUES (agtype_build_map('name','sqlhacker'));
ROLLBACK;

\echo '--- [6] Plain SQL UPDATE label table ---'
BEGIN;
UPDATE history."Person" SET properties = agtype_build_map('name','x');
ROLLBACK;

\echo '--- [7] Plain SQL DELETE from label table ---'
BEGIN;
DELETE FROM history."Person";
ROLLBACK;

\echo '--- [8] AGE create_graph (needs CREATE on database) ---'
BEGIN;
SELECT create_graph('evil');
ROLLBACK;

\echo '--- [9] AGE create_vlabel on existing graph ---'
BEGIN;
SELECT create_vlabel('history', 'EvilLabel');
ROLLBACK;

\echo '--- [10] AGE drop_graph ---'
BEGIN;
SELECT drop_graph('history', true);
ROLLBACK;

\echo '--- [11] Tamper AGE metadata tables directly ---'
BEGIN;
INSERT INTO ag_catalog.ag_graph (graphid, name, namespace) VALUES (99999, 'fake', 'public');
ROLLBACK;
