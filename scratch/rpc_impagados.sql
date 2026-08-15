-- ============================================================================
-- get_store_dashboard_impagados
-- ============================================================================
-- Devuelve impagados y pendientes de cobro agrupados por almacén,
-- leyendo únicamente del batch activo de la tabla snapshot `receivables`.
--
-- Dependencias:
--   - public.receivables (batch_id, cod_almacen, cod_forma_liquidacion,
--                         cod_remesa, importe_pendiente)
--   - public.get_active_receivables_batch()
--   - public.sync_state.dataset = 'receivables'
-- ============================================================================

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

-- ============================================================================
-- Permisos: solo usuarios autenticados; anon y PUBLIC sin acceso
-- ============================================================================

REVOKE ALL ON FUNCTION public.get_store_dashboard_impagados() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.get_store_dashboard_impagados() FROM anon;

GRANT EXECUTE ON FUNCTION public.get_store_dashboard_impagados() TO authenticated;
