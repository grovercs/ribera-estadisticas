-- Migración para almacenar snapshots precalculados del dashboard (Fases A + B)
-- Permite que Netlify consuma una única fuente de verdad validada desde Laravel/ERP.

CREATE TABLE IF NOT EXISTS public.dashboard_snapshots (
    scope VARCHAR(50) NOT NULL,
    year INTEGER NOT NULL,
    periodo VARCHAR(20) NOT NULL,
    anio_ant VARCHAR(20) NOT NULL,
    payload JSONB NOT NULL,
    generated_at TIMESTAMPTZ NOT NULL,
    execution_time_ms NUMERIC(10, 2) NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'erp_integral_snapshot',
    version INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (scope, year, periodo, anio_ant)
);

CREATE INDEX IF NOT EXISTS idx_dashboard_snapshots_lookup
    ON public.dashboard_snapshots (scope, year, periodo, anio_ant);

-- Habilitar Row Level Security (RLS)
ALTER TABLE public.dashboard_snapshots ENABLE ROW LEVEL SECURITY;

-- Política de solo lectura para usuarios autenticados y anónimos
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_policies
        WHERE schemaname = 'public'
          AND tablename = 'dashboard_snapshots'
          AND policyname = 'select_dashboard_snapshots'
    ) THEN
        CREATE POLICY select_dashboard_snapshots ON public.dashboard_snapshots
            FOR SELECT TO authenticated, anon USING (true);
    END IF;
END $$;

GRANT SELECT ON public.dashboard_snapshots TO authenticated, anon;
