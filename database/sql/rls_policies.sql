-- ============================================================
-- rls_policies.sql
-- Hemeroteca Digital El Heraldo — heraldo-noticias
-- Row Level Security para Supabase
-- NOTA: Laravel usa SUPABASE_SERVICE_KEY que bypassa RLS
-- ============================================================

-- Habilitar RLS en todas las tablas
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE editions ENABLE ROW LEVEL SECURITY;
ALTER TABLE articles ENABLE ROW LEVEL SECURITY;
ALTER TABLE tags ENABLE ROW LEVEL SECURITY;
ALTER TABLE article_tag ENABLE ROW LEVEL SECURITY;
ALTER TABLE extraction_jobs ENABLE ROW LEVEL SECURITY;

-- ── Políticas de LECTURA PÚBLICA (portal sin auth) ──────────────────────────

-- Artículos: acceso público de lectura
CREATE POLICY "articles_public_read"
    ON articles FOR SELECT
    USING (true);

-- Tags: acceso público de lectura
CREATE POLICY "tags_public_read"
    ON tags FOR SELECT
    USING (true);

-- Article_tag: acceso público de lectura
CREATE POLICY "article_tag_public_read"
    ON article_tag FOR SELECT
    USING (true);

-- Editions: solo las completadas son públicas
CREATE POLICY "editions_public_read_completed"
    ON editions FOR SELECT
    USING (status = 'completed');

-- ── Políticas RESTRICTIVAS (no acceso público) ───────────────────────────────

-- Users: sin acceso público
CREATE POLICY "users_no_public_access"
    ON users FOR SELECT
    USING (false);

-- Extraction_jobs: sin acceso público
CREATE POLICY "extraction_jobs_no_public_access"
    ON extraction_jobs FOR SELECT
    USING (false);

-- ── NOTA IMPORTANTE ─────────────────────────────────────────────────────────
-- Laravel usa SUPABASE_SERVICE_KEY (service_role) que BYPASSA RLS automáticamente.
-- Estas políticas protegen acceso directo desde frontend/PostgREST API.
-- Para acceso autenticado de admin desde Supabase Dashboard, usar anon key
-- con JWT de admin (fuera de scope v1.0).

-- ── Verificar políticas ──────────────────────────────────────────────────────
-- SELECT schemaname, tablename, policyname, cmd, qual FROM pg_policies WHERE schemaname = 'public' ORDER BY tablename;
