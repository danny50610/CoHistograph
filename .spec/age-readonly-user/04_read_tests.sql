-- 04_read_tests.sql
-- Positive read tests. Run as the read-only role; every query MUST succeed
-- and return data (no writes involved). No `LOAD 'age'` needed.

SET search_path = ag_catalog, "$user", public;

\echo '--- [R1] Cypher WHERE filter (born < 1800) ---'
SELECT * FROM cypher('history', $$
    MATCH (n:Person) WHERE n.born < 1800 RETURN n.name, n.born
$$) AS (name agtype, born agtype);

\echo '--- [R2] Cypher aggregation (count nodes) ---'
SELECT * FROM cypher('history', $$
    MATCH (n:Person) RETURN count(n)
$$) AS (person_count agtype);

\echo '--- [R3] Cypher edge traversal (who fought whom) ---'
SELECT * FROM cypher('history', $$
    MATCH (a:Person)-[r:FOUGHT]->(b:Person)
    RETURN a.name, r.at, b.name
$$) AS (attacker agtype, place agtype, defender agtype);

\echo '--- [R4] Cypher ORDER BY + LIMIT ---'
SELECT * FROM cypher('history', $$
    MATCH (n:Person) RETURN n.name ORDER BY n.name DESC LIMIT 1
$$) AS (name agtype);

\echo '--- [R5] Read graph metadata from ag_catalog + plain SQL SELECT on label table ---'
SELECT name AS graph_name FROM ag_catalog.ag_graph;
SELECT count(*) AS person_rows FROM history."Person";
