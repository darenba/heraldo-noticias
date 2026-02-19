-- ============================================================
-- triggers.sql
-- Hemeroteca Digital El Heraldo — heraldo-noticias
-- Ejecutar DESPUÉS de las migraciones en Supabase SQL editor
-- ============================================================

-- ── Trigger 1: Auto-update search_vector en articles ────────────────────────
-- Uses weighted tsvector: A=título (más importante), B=sección, C=extracto, D=cuerpo

CREATE OR REPLACE FUNCTION update_article_search_vector()
RETURNS TRIGGER AS $$
BEGIN
    NEW.search_vector :=
        setweight(to_tsvector('spanish', coalesce(NEW.title, '')), 'A') ||
        setweight(to_tsvector('spanish', coalesce(NEW.section, '')), 'B') ||
        setweight(to_tsvector('spanish', coalesce(NEW.body_excerpt, '')), 'C') ||
        setweight(to_tsvector('spanish', coalesce(substring(NEW.body, 1, 2000), '')), 'D');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS articles_search_vector_update ON articles;

CREATE TRIGGER articles_search_vector_update
    BEFORE INSERT OR UPDATE OF title, body, body_excerpt, section
    ON articles
    FOR EACH ROW
    EXECUTE FUNCTION update_article_search_vector();

-- ── Trigger 2: Mantener article_count en tags sincronizado ──────────────────

CREATE OR REPLACE FUNCTION update_tag_article_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE tags SET article_count = article_count + 1 WHERE id = NEW.tag_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE tags SET article_count = GREATEST(0, article_count - 1) WHERE id = OLD.tag_id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS article_tag_count_update ON article_tag;

CREATE TRIGGER article_tag_count_update
    AFTER INSERT OR DELETE ON article_tag
    FOR EACH ROW
    EXECUTE FUNCTION update_tag_article_count();

-- ── Verificar triggers ───────────────────────────────────────────────────────
-- SELECT tgname, tgrelid::regclass FROM pg_trigger WHERE tgname LIKE '%heraldo%' OR tgrelid::regclass IN ('articles'::regclass, 'article_tag'::regclass);
