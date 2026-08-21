-- Mantiene el contrato existente del dashboard, pero usa el detalle vigente
-- sincronizado desde el ERP en lugar de la fotografia historica agregada.

CREATE OR REPLACE FUNCTION public.get_store_dashboard_albaranes(
    p_year INTEGER DEFAULT NULL
)
RETURNS JSON
LANGUAGE plpgsql
SET search_path TO 'public'
AS $function$
DECLARE
    v_now_madrid TIMESTAMP;
    v_year INTEGER;
    v_month INTEGER;
    v_period_start DATE;
    v_period_end DATE;
    res JSON;
BEGIN
    v_now_madrid := CURRENT_TIMESTAMP AT TIME ZONE 'Europe/Madrid';
    v_year := COALESCE(p_year, EXTRACT(YEAR FROM v_now_madrid)::INTEGER);
    v_month := EXTRACT(MONTH FROM v_now_madrid)::INTEGER;
    v_period_start := make_date(v_year, v_month, 1);
    v_period_end := (v_period_start + INTERVAL '1 month')::DATE;

    SELECT json_agg(json_build_object(
        'cod_almacen', grouped.cod_almacen,
        'albaranes', grouped.num_albaranes,
        'importe', grouped.total_importe
    ) ORDER BY grouped.cod_almacen)
    INTO res
    FROM (
        SELECT
            cod_almacen,
            COUNT(*) AS num_albaranes,
            COALESCE(SUM(importe), 0) AS total_importe
        FROM public.purchase_headers
        WHERE tipo_compra = 2
          AND fecha_compra >= v_period_start
          AND fecha_compra < v_period_end
        GROUP BY cod_almacen
    ) AS grouped;

    RETURN COALESCE(res, '[]'::JSON);
END;
$function$;
