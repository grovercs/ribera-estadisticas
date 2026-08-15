-- ============================================================================
-- DELTA MIGRATION — Receivables como dataset oficial controlado
-- Fecha: 2026-08-14
-- Proyecto: ribera-estadisticas
--
-- REGLAS:
--   - Solo lectura/validacion previa al sync.
--   - Sin DROPs, sin TRUNCATE, sin recrear tablas, sin cambiar tipos/PK.
--   - Idempotente: cada paso se ejecuta solo si el objeto no existe o es
--     estrictamente necesario corregirlo.
--
-- Estado LIVE conocido al 2026-08-14:
--   - public.receivables ya existe con su propio schema (id_vencimiento, batch_id, ...).
--   - public.receivable_payments ya existe (no la toca esta migracion).
--   - RLS y policy select para authenticated ya estan activos.
--   - get_active_receivables_batch() ya existe.
--   - get_store_dashboard_impagados() NO filtra por batch activo: es un bug
--     que corrige esta migracion.
--   - Faltan 3 indices utiles para el dashboard de cartera.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. INDICES OPTIMIZADOS PARA EL DASHBOARD DE CARTERA
-- ----------------------------------------------------------------------------
-- Estos indices aceleran las agregaciones por almacen, forma de liquidacion
-- y remesa cuando se filtra por batch_id activo.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_indexes
        WHERE schemaname = 'public' AND indexname = 'idx_receivables_almacen'
    ) THEN
        CREATE INDEX idx_receivables_almacen ON receivables(batch_id, cod_almacen);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_indexes
        WHERE schemaname = 'public' AND indexname = 'idx_receivables_forma_liquidacion'
    ) THEN
        CREATE INDEX idx_receivables_forma_liquidacion ON receivables(batch_id, cod_forma_liquidacion);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_indexes
        WHERE schemaname = 'public' AND indexname = 'idx_receivables_remesa'
    ) THEN
        CREATE INDEX idx_receivables_remesa ON receivables(batch_id, cod_remesa) WHERE cod_remesa IS NULL;
    END IF;
END $$;

-- ----------------------------------------------------------------------------
-- 2. CORRECCION DE get_store_dashboard_impagados
-- ----------------------------------------------------------------------------
-- LIVE actualmente lee toda la tabla sin filtrar por batch activo. Con el
-- snapshot semantico esto seria incorrecto en cuanto existan varios batches.
-- Se reemplaza por una version que:
--   - obtiene el batch activo via get_active_receivables_batch();
--   - filtra todas las subconsultas por batch_id = v_batch;
--   - mantiene exactamente la misma estructura JSON de salida.
CREATE OR REPLACE FUNCTION public.get_store_dashboard_impagados()
RETURNS json
LANGUAGE plpgsql
STABLE
SECURITY INVOKER
SET search_path TO 'public'
AS $function$
DECLARE
    v_batch text;
    res json;
BEGIN
    v_batch := public.get_active_receivables_batch();

    SELECT json_build_object(
        'impagados_por_almacen', (
            SELECT COALESCE(json_agg(json_build_object(
                'cod_almacen', COALESCE(cod_almacen, 0),
                'tickets', tickets,
                'importe', importe
            )), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(*) as tickets, SUM(importe_pendiente) as importe
                FROM receivables
                WHERE batch_id = v_batch
                  AND cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
                GROUP BY cod_almacen
            ) t
        ),
        'pendientes_por_almacen', (
            SELECT COALESCE(json_agg(json_build_object(
                'cod_almacen', COALESCE(cod_almacen, 0),
                'tickets', tickets,
                'importe', importe
            )), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(*) as tickets, SUM(importe_pendiente) as importe
                FROM receivables
                WHERE batch_id = v_batch
                  AND cod_remesa IS NULL
                  AND cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
                GROUP BY cod_almacen
            ) t
        ),
        'totales', json_build_object(
            'impagados_tickets', (SELECT COUNT(*) FROM receivables WHERE batch_id = v_batch AND cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')),
            'impagados_importe', (SELECT COALESCE(SUM(importe_pendiente), 0) FROM receivables WHERE batch_id = v_batch AND cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')),
            'pendientes_tickets', (SELECT COUNT(*) FROM receivables WHERE batch_id = v_batch AND cod_remesa IS NULL AND cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')),
            'pendientes_importe', (SELECT COALESCE(SUM(importe_pendiente), 0) FROM receivables WHERE batch_id = v_batch AND cod_remesa IS NULL AND cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC'))
        )
    ) INTO res;

    RETURN res;
END;
$function$;

-- Permisos: reafirmar solo para authenticated, revocar a PUBLIC/anon.
REVOKE ALL ON FUNCTION public.get_store_dashboard_impagados() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.get_store_dashboard_impagados() FROM anon;
GRANT EXECUTE ON FUNCTION public.get_store_dashboard_impagados() TO authenticated;

-- ----------------------------------------------------------------------------
-- 3. NOTAS PARA EL OPERADOR
-- ----------------------------------------------------------------------------
-- - sync_state.dataset = 'receivables' se actualiza por RiberaSyncToSupabase.
-- - get_active_receivables_batch() ya existe en LIVE; esta migracion no lo toca.
-- - La tabla receivables ya existe en LIVE; esta migracion no la recrea.
-- - La tabla receivable_payments ya existe en LIVE; esta migracion no la toca.
-- - RLS y policy select_receivables ya existen; esta migracion no las toca.
-- - La semantica del sync es: solo cartera viva (importe_pendiente > 0 logicamente),
--   impagados (cod_forma_liquidacion ZIMP/ZJUZ/ZPER/ZCYC) y pendientes no remesados.
