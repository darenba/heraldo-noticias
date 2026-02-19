-- ============================================================
-- extensions.sql
-- Hemeroteca Digital El Heraldo — heraldo-noticias
-- Ejecutar ANTES de las migraciones en Supabase SQL editor
-- ============================================================

-- Enable trigram extension for ILIKE search and similarity queries
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Enable unaccent for accent-insensitive search
CREATE EXTENSION IF NOT EXISTS unaccent;

-- Create a custom text search configuration that uses unaccent
-- for better Spanish full-text search (handles accented characters)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_ts_config WHERE cfgname = 'spanish_unaccent'
    ) THEN
        CREATE TEXT SEARCH CONFIGURATION spanish_unaccent (COPY = spanish);

        ALTER TEXT SEARCH CONFIGURATION spanish_unaccent
            ALTER MAPPING FOR hword, hword_part, word
            WITH unaccent, spanish_stem;
    END IF;
END
$$;
