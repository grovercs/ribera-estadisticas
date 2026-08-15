-- ====================================================================
-- MIGRACIÓN: Normalizar source de sync_requests a manual/auto
-- Fecha: 2026-08-14
-- Descripción: Elimina distinción de horario automático. Todos los
--              sources automáticos pasan a 'auto'. NO borra filas.
-- ====================================================================

-- 1. Normalizar filas existentes antes de cambiar el CHECK
UPDATE sync_requests
SET source = 'auto'
WHERE source IN ('auto_13:15', 'auto_19:15');

-- 2. Quitar constraint anterior
ALTER TABLE sync_requests
DROP CONSTRAINT IF EXISTS sync_requests_source_check;

-- 3. Agregar constraint final: solo manual / auto
ALTER TABLE sync_requests
ADD CONSTRAINT sync_requests_source_check
CHECK (source IN ('manual', 'auto'));

-- 4. Actualizar política RLS (source = 'manual' sigue igual)
DROP POLICY IF EXISTS insert_sync_requests ON sync_requests;
CREATE POLICY insert_sync_requests ON sync_requests
    FOR INSERT TO authenticated
    WITH CHECK (
        requested_by = auth.uid()
        AND source = 'manual'
    );
