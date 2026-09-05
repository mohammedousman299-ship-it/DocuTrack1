-- Extensions requises par le moteur de rapprochement (docs/MATCHING.md §7).
-- Les trois sont bloquantes : sans elles, la conception du moteur ne tient pas.
--
-- Ce fichier n'est exécuté qu'à la première initialisation du volume. Les
-- migrations Laravel créent ces mêmes extensions de façon idempotente, afin que
-- le projet fonctionne aussi sur une base fournie autrement.

CREATE EXTENSION IF NOT EXISTS unaccent;       -- normalisation des noms
CREATE EXTENSION IF NOT EXISTS pg_trgm;        -- similarité trigramme + index GIN
CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;  -- levenshtein(), score composite (D-018)
CREATE EXTENSION IF NOT EXISTS pgcrypto;       -- primitives cryptographiques
