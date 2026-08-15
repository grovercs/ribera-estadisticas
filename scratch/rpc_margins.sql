CREATE OR REPLACE FUNCTION public.get_store_dashboard_margins(
    p_periodo text DEFAULT 'year'::text
)
RETURNS json
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path TO 'public'
AS $function$
DECLARE
    v_year int;
    v_ref_date date;
    v_ultimo_dia date;
    v_q_start date;
    v_q_end date;
    v_period_where text;
    v_period_where_sub text;
    res json;
BEGIN
    v_year := EXTRACT(YEAR FROM CURRENT_DATE)::int;
    v_ref_date := CURRENT_DATE;

    SELECT MAX(fecha_venta::date) INTO v_ultimo_dia
    FROM sales_headers
    WHERE tipo_venta IN (2, 4, 5) AND anulada IS NOT TRUE;
    IF v_ultimo_dia IS NULL THEN v_ultimo_dia := v_ref_date; END IF;

    IF EXTRACT(DAY FROM v_ref_date) >= 15 THEN
        v_q_start := date_trunc('month', v_ref_date)::date + 14;
        v_q_end := (date_trunc('month', v_ref_date) + INTERVAL '1 month - 1 day')::date;
    ELSE
        v_q_start := date_trunc('month', v_ref_date)::date;
        v_q_end := date_trunc('month', v_ref_date)::date + 13;
    END IF;

    IF p_periodo = 'hoy' THEN
        v_period_where := format('v.fecha_venta::date = %L', v_ultimo_dia);
        v_period_where_sub := format('vc.fecha_venta::date = %L', v_ultimo_dia);
    ELSIF p_periodo = 'quincena' THEN
        v_period_where := format('v.fecha_venta::date >= %L AND v.fecha_venta::date <= %L', v_q_start, v_q_end);
        v_period_where_sub := format('vc.fecha_venta::date >= %L AND vc.fecha_venta::date <= %L', v_q_start, v_q_end);
    ELSE
        v_period_where := format('EXTRACT(YEAR FROM v.fecha_venta) = %s', v_year);
        v_period_where_sub := format('EXTRACT(YEAR FROM vc.fecha_venta) = %s', v_year);
    END IF;

    EXECUTE format($q$
        SELECT json_build_object(
            'periodo', %L,
            'periodo_rows', (
                SELECT COALESCE(json_agg(json_build_object(
                    'cod_almacen', m.cod_almacen,
                    'venta', m.venta,
                    'coste', m.coste,
                    'margen', m.margen,
                    'margen_porcentaje', m.margen_porcentaje
                )), '[]'::json)
                FROM (
                    SELECT
                        v.cod_almacen,
                        COALESCE(SUM(v.net_amount), 0) as venta,
                        COALESCE((
                            SELECT SUM(l.precio_coste * l.cantidad)
                            FROM sales_lines l
                            JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                AND l.tipo_venta = vc.tipo_venta
                                AND l.cod_empresa = vc.cod_empresa
                                AND l.cod_caja = vc.cod_caja
                            WHERE %s
                              AND vc.tipo_venta IN (2,4,5)
                              AND vc.cod_almacen = v.cod_almacen
                              AND l.precio_coste IS NOT NULL
                              AND vc.anulada IS NOT TRUE
                        ), 0) as coste,
                        COALESCE(SUM(v.net_amount), 0) - COALESCE((
                            SELECT SUM(l.precio_coste * l.cantidad)
                            FROM sales_lines l
                            JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                AND l.tipo_venta = vc.tipo_venta
                                AND l.cod_empresa = vc.cod_empresa
                                AND l.cod_caja = vc.cod_caja
                            WHERE %s
                              AND vc.tipo_venta IN (2,4,5)
                              AND vc.cod_almacen = v.cod_almacen
                              AND l.precio_coste IS NOT NULL
                              AND vc.anulada IS NOT TRUE
                        ), 0) as margen,
                        CASE WHEN COALESCE(SUM(v.net_amount), 0) > 0
                             THEN (COALESCE(SUM(v.net_amount), 0) - COALESCE((
                                SELECT SUM(l.precio_coste * l.cantidad)
                                FROM sales_lines l
                                JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                    AND l.tipo_venta = vc.tipo_venta
                                    AND l.cod_empresa = vc.cod_empresa
                                    AND l.cod_caja = vc.cod_caja
                                WHERE %s
                                  AND vc.tipo_venta IN (2,4,5)
                                  AND vc.cod_almacen = v.cod_almacen
                                  AND l.precio_coste IS NOT NULL
                                  AND vc.anulada IS NOT TRUE
                             ), 0)) / COALESCE(SUM(v.net_amount), 0) * 100
                             ELSE 0
                        END as margen_porcentaje
                    FROM sales_headers v
                    WHERE %s
                      AND v.tipo_venta IN (2,4,5)
                      AND v.anulada IS NOT TRUE
                    GROUP BY v.cod_almacen
                ) m
            ),
            'hoy_rows', (
                SELECT COALESCE(json_agg(json_build_object(
                    'cod_almacen', m.cod_almacen,
                    'venta', m.venta,
                    'coste', m.coste,
                    'margen', m.margen,
                    'margen_porcentaje', m.margen_porcentaje
                )), '[]'::json)
                FROM (
                    SELECT
                        v.cod_almacen,
                        COALESCE(SUM(v.net_amount), 0) as venta,
                        COALESCE((
                            SELECT SUM(l.precio_coste * l.cantidad)
                            FROM sales_lines l
                            JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                AND l.tipo_venta = vc.tipo_venta
                                AND l.cod_empresa = vc.cod_empresa
                                AND l.cod_caja = vc.cod_caja
                            WHERE vc.fecha_venta::date = %L
                              AND vc.tipo_venta IN (2,4,5)
                              AND vc.cod_almacen = v.cod_almacen
                              AND l.precio_coste IS NOT NULL
                              AND vc.anulada IS NOT TRUE
                        ), 0) as coste,
                        COALESCE(SUM(v.net_amount), 0) - COALESCE((
                            SELECT SUM(l.precio_coste * l.cantidad)
                            FROM sales_lines l
                            JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                AND l.tipo_venta = vc.tipo_venta
                                AND l.cod_empresa = vc.cod_empresa
                                AND l.cod_caja = vc.cod_caja
                            WHERE vc.fecha_venta::date = %L
                              AND vc.tipo_venta IN (2,4,5)
                              AND vc.cod_almacen = v.cod_almacen
                              AND l.precio_coste IS NOT NULL
                              AND vc.anulada IS NOT TRUE
                        ), 0) as margen,
                        CASE WHEN COALESCE(SUM(v.net_amount), 0) > 0
                             THEN (COALESCE(SUM(v.net_amount), 0) - COALESCE((
                                SELECT SUM(l.precio_coste * l.cantidad)
                                FROM sales_lines l
                                JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                    AND l.tipo_venta = vc.tipo_venta
                                    AND l.cod_empresa = vc.cod_empresa
                                    AND l.cod_caja = vc.cod_caja
                                WHERE vc.fecha_venta::date = %L
                                  AND vc.tipo_venta IN (2,4,5)
                                  AND vc.cod_almacen = v.cod_almacen
                                  AND l.precio_coste IS NOT NULL
                                  AND vc.anulada IS NOT TRUE
                             ), 0)) / COALESCE(SUM(v.net_amount), 0) * 100
                             ELSE 0
                        END as margen_porcentaje
                    FROM sales_headers v
                    WHERE v.fecha_venta::date = %L
                      AND v.tipo_venta IN (2,4,5)
                      AND v.anulada IS NOT TRUE
                    GROUP BY v.cod_almacen
                ) m
            ),
            'year_rows', (
                SELECT COALESCE(json_agg(json_build_object(
                    'cod_almacen', m.cod_almacen,
                    'venta', m.venta,
                    'coste', m.coste,
                    'margen', m.margen,
                    'margen_porcentaje', m.margen_porcentaje
                )), '[]'::json)
                FROM (
                    SELECT
                        v.cod_almacen,
                        COALESCE(SUM(v.net_amount), 0) as venta,
                        COALESCE((
                            SELECT SUM(l.precio_coste * l.cantidad)
                            FROM sales_lines l
                            JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                AND l.tipo_venta = vc.tipo_venta
                                AND l.cod_empresa = vc.cod_empresa
                                AND l.cod_caja = vc.cod_caja
                            WHERE EXTRACT(YEAR FROM vc.fecha_venta) = %s
                              AND vc.tipo_venta IN (2,4,5)
                              AND vc.cod_almacen = v.cod_almacen
                              AND l.precio_coste IS NOT NULL
                              AND vc.anulada IS NOT TRUE
                        ), 0) as coste,
                        COALESCE(SUM(v.net_amount), 0) - COALESCE((
                            SELECT SUM(l.precio_coste * l.cantidad)
                            FROM sales_lines l
                            JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                AND l.tipo_venta = vc.tipo_venta
                                AND l.cod_empresa = vc.cod_empresa
                                AND l.cod_caja = vc.cod_caja
                            WHERE EXTRACT(YEAR FROM vc.fecha_venta) = %s
                              AND vc.tipo_venta IN (2,4,5)
                              AND vc.cod_almacen = v.cod_almacen
                              AND l.precio_coste IS NOT NULL
                              AND vc.anulada IS NOT TRUE
                        ), 0) as margen,
                        CASE WHEN COALESCE(SUM(v.net_amount), 0) > 0
                             THEN (COALESCE(SUM(v.net_amount), 0) - COALESCE((
                                SELECT SUM(l.precio_coste * l.cantidad)
                                FROM sales_lines l
                                JOIN sales_headers vc ON l.cod_venta = vc.cod_venta
                                    AND l.tipo_venta = vc.tipo_venta
                                    AND l.cod_empresa = vc.cod_empresa
                                    AND l.cod_caja = vc.cod_caja
                                WHERE EXTRACT(YEAR FROM vc.fecha_venta) = %s
                                  AND vc.tipo_venta IN (2,4,5)
                                  AND vc.cod_almacen = v.cod_almacen
                                  AND l.precio_coste IS NOT NULL
                                  AND vc.anulada IS NOT TRUE
                             ), 0)) / COALESCE(SUM(v.net_amount), 0) * 100
                             ELSE 0
                        END as margen_porcentaje
                    FROM sales_headers v
                    WHERE EXTRACT(YEAR FROM v.fecha_venta) = %s
                      AND v.tipo_venta IN (2,4,5)
                      AND v.anulada IS NOT TRUE
                    GROUP BY v.cod_almacen
                ) m
            )
        )
    $q$,
    p_periodo,
    v_period_where_sub, v_period_where_sub, v_period_where_sub, v_period_where,
    v_ultimo_dia, v_ultimo_dia, v_ultimo_dia, v_ultimo_dia,
    v_year, v_year, v_year, v_year
    ) INTO res;

    RETURN res;
END;
$function$;
