-- ============================================================================
-- get_dashboard_net_margins
-- ============================================================================
-- Devuelve margen comercial neto (sin IVA) para un rango de mes/año arbitrario.
-- Usa exactamente la misma lógica documental y de costes que
-- get_store_dashboard_margins, pero parametrizada por rango MES/AÑO.
--
-- Definición empresarial:
--   Venta  = SUM(sales_headers.net_amount)
--   Coste  = SUM(sales_lines.precio_coste * cantidad)
--   Margen = Venta - Coste
--   Margen % = Margen / Venta * 100
-- ============================================================================

CREATE OR REPLACE FUNCTION public.get_dashboard_net_margins(
    p_year_from  integer,
    p_month_from integer,
    p_year_to    integer,
    p_month_to   integer
)
RETURNS TABLE(
    venta             numeric,
    coste             numeric,
    margen            numeric,
    margen_porcentaje numeric
)
LANGUAGE plpgsql
STABLE
SECURITY INVOKER
SET search_path TO 'public'
AS $function$
DECLARE
    v_start_date date;
    v_end_date   date;
BEGIN
    -- Validación de meses
    IF p_month_from < 1 OR p_month_from > 12 THEN
        RAISE EXCEPTION 'p_month_from debe estar entre 1 y 12 (recibido: %)', p_month_from;
    END IF;

    IF p_month_to < 1 OR p_month_to > 12 THEN
        RAISE EXCEPTION 'p_month_to debe estar entre 1 y 12 (recibido: %)', p_month_to;
    END IF;

    -- Construcción del rango de fechas
    v_start_date := make_date(p_year_from, p_month_from, 1);
    v_end_date   := (make_date(p_year_to, p_month_to, 1) + INTERVAL '1 month')::date;

    -- Validación de coherencia de rango
    IF v_end_date <= v_start_date THEN
        RAISE EXCEPTION 'El rango final debe ser posterior al inicial: % <= %', v_end_date, v_start_date;
    END IF;

    RETURN QUERY
    SELECT
        COALESCE(SUM(h.net_amount), 0)::numeric AS venta,
        COALESCE(SUM(doc.coste), 0)::numeric      AS coste,
        (COALESCE(SUM(h.net_amount), 0) - COALESCE(SUM(doc.coste), 0))::numeric AS margen,
        CASE
            WHEN COALESCE(SUM(h.net_amount), 0) > 0
            THEN ROUND(
                ((COALESCE(SUM(h.net_amount), 0) - COALESCE(SUM(doc.coste), 0))
                 / SUM(h.net_amount)) * 100,
                2
            )::numeric
            ELSE 0::numeric
        END AS margen_porcentaje
    FROM sales_headers h
    LEFT JOIN LATERAL (
        SELECT SUM(l.precio_coste * l.cantidad) AS coste
        FROM sales_lines l
        WHERE l.cod_venta   = h.cod_venta
          AND l.tipo_venta  = h.tipo_venta
          AND l.cod_empresa = h.cod_empresa
          AND l.cod_caja    = h.cod_caja
          AND l.precio_coste IS NOT NULL
    ) doc ON TRUE
    WHERE h.tipo_venta IN (2, 4, 5)
      AND h.anulada IS NOT TRUE
      AND h.fecha_venta >= v_start_date
      AND h.fecha_venta <  v_end_date;
END;
$function$;

-- ============================================================================
-- Permisos: solo usuarios autenticados; anon y PUBLIC sin acceso
-- ============================================================================

REVOKE ALL ON FUNCTION public.get_dashboard_net_margins(integer, integer, integer, integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.get_dashboard_net_margins(integer, integer, integer, integer) FROM anon;

GRANT EXECUTE ON FUNCTION public.get_dashboard_net_margins(integer, integer, integer, integer) TO authenticated;
