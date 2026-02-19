-- ============================================================
-- indexes.sql
-- Hemeroteca Digital El Heraldo — heraldo-noticias
-- Índices adicionales de rendimiento (más allá de las migraciones)
-- Ejecutar después de triggers.sql
-- ============================================================

-- Trigram index para búsqueda ILIKE en title (fallback cuando search_vector no está listo)
CREATE INDEX IF NOT EXISTS articles_title_trgm
    ON articles USING GIN(title gin_trgm_ops);

-- Trigram index para sección (filtrado parcial)
CREATE INDEX IF NOT EXISTS articles_section_trgm
    ON articles USING GIN(section gin_trgm_ops)
    WHERE section IS NOT NULL;

-- Índice compuesto para queries de rango fecha + sección (portal de búsqueda)
CREATE INDEX IF NOT EXISTS articles_date_section
    ON articles(publication_date DESC, section);

-- Índice en editions.status para dashboard queries
CREATE INDEX IF NOT EXISTS editions_status_idx
    ON editions(status);

-- Índice en editions por fecha de publicación descendente
CREATE INDEX IF NOT EXISTS editions_publication_date_idx
    ON editions(publication_date DESC);

-- Trigram en tags.name para búsqueda de tags
CREATE INDEX IF NOT EXISTS tags_name_trgm
    ON tags USING GIN(name gin_trgm_ops);

-- Índice en article_tag.tag_id para queries de "artículos por tag"
CREATE INDEX IF NOT EXISTS article_tag_tag_id_idx
    ON article_tag(tag_id);

-- ── Verificar índices ────────────────────────────────────────────────────────
-- SELECT indexname, tablename, indexdef FROM pg_indexes WHERE tablename IN ('articles', 'editions', 'tags', 'article_tag') ORDER BY tablename, indexname;
