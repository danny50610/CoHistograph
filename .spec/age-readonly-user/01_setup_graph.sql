-- 01_setup_graph.sql
-- Run as a superuser (postgres). Creates the AGE extension, a sample graph
-- ("history") with a couple of nodes and one edge, used by the read-only tests.
--
--   docker exec -i -e PGPASSWORD=postgres age-ro \
--     psql -U postgres -d graphdb -v ON_ERROR_STOP=1 -f - < 01_setup_graph.sql

CREATE EXTENSION IF NOT EXISTS age;
LOAD 'age';
SET search_path = ag_catalog, "$user", public;

SELECT create_graph('history');

SELECT * FROM cypher('history', $$ CREATE (:Person {name:'Napoleon', born:1769}) $$) AS (v agtype);
SELECT * FROM cypher('history', $$ CREATE (:Person {name:'Wellington', born:1769}) $$) AS (v agtype);
SELECT * FROM cypher('history', $$
    MATCH (a:Person {name:'Napoleon'}), (b:Person {name:'Wellington'})
    CREATE (a)-[:FOUGHT {at:'Waterloo'}]->(b)
$$) AS (v agtype);

SELECT * FROM cypher('history', $$ MATCH (n:Person) RETURN n.name, n.born $$) AS (name agtype, born agtype);
