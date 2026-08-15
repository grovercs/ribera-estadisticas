-- ====================================================================
-- MIGRACIÓN: sync_requests
-- Fecha: 2026-08-14
-- Descripción: Cola de solicitudes de sincronización manual/automática
--              con lock lógico único por dataset.
-- ====================================================================

-- 1. Tabla
CREATE TABLE IF NOT EXISTS sync_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    dataset VARCHAR(100) NOT NULL DEFAULT 'sales',
    status VARCHAR(50) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','running','success','failed')),
    requested_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    source VARCHAR(50) NOT NULL
        CHECK (source IN ('manual','auto')),
    requested_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    started_at TIMESTAMP WITH TIME ZONE,
    finished_at TIMESTAMP WITH TIME ZONE,
    error_message TEXT,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);

-- 2. Índices
DROP INDEX IF EXISTS idx_sync_requests_one_active_per_dataset;
CREATE UNIQUE INDEX idx_sync_requests_one_active_per_dataset
    ON sync_requests(dataset)
    WHERE status IN ('pending','running');

DROP INDEX IF EXISTS idx_sync_requests_pending_dataset;
CREATE INDEX idx_sync_requests_pending_dataset
    ON sync_requests(dataset, requested_at)
    WHERE status = 'pending';

-- 3. RLS
ALTER TABLE sync_requests ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS select_sync_requests ON sync_requests;
CREATE POLICY select_sync_requests ON sync_requests
    FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS insert_sync_requests ON sync_requests;
CREATE POLICY insert_sync_requests ON sync_requests
    FOR INSERT TO authenticated
    WITH CHECK (
        requested_by = auth.uid()
        AND source = 'manual'
    );

-- 4. Trigger updated_at
CREATE OR REPLACE FUNCTION sync_requests_set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS tr_sync_requests_updated_at ON sync_requests;
CREATE TRIGGER tr_sync_requests_updated_at
    BEFORE UPDATE ON sync_requests
    FOR EACH ROW
    EXECUTE FUNCTION sync_requests_set_updated_at();
