-- Fix Top Familias labels: current sales lacked family_name, so they fell back to raw cod_familia.
-- Use stats_historical_families as a read-only dictionary to populate real names.
CREATE OR REPLACE FUNCTION get_dashboard_top_families(
    p_year_from integer,
    p_month_from integer,
    p_year_to integer,
    p_month_to integer
)
RETURNS TABLE(cod_familia character varying, family_name character varying, total numeric)
LANGUAGE plpgsql
AS $function$
BEGIN
    IF p_year_from < 2012 OR p_year_to > 2030 OR p_month_from < 1 OR p_month_from > 12 OR p_month_to < 1 OR p_month_to > 12 THEN
        RAISE EXCEPTION 'Parámetros de fecha fuera de rango';
    END IF;

    RETURN QUERY
    WITH family_dict AS (
        SELECT DISTINCT fsh.cod_familia, fsh.family_name
        FROM stats_historical_families fsh
        WHERE fsh.family_name IS NOT NULL AND fsh.family_name <> ''
    ),
    combined AS (
        SELECT f.cod_familia, f.family_name, f.total as sales
        FROM stats_historical_families f
        WHERE (f.year > p_year_from OR (f.year = p_year_from AND f.month >= p_month_from))
          AND (f.year < p_year_to OR (f.year = p_year_to AND f.month <= p_month_to))
          AND f.year < 2025
        UNION ALL
        SELECT
            ps.cod_familia,
            MAX(hf.family_name) AS family_name,
            SUM(l.total_amount) as sales
        FROM sales_lines l
        INNER JOIN sales_headers h
            ON l.cod_venta = h.cod_venta AND l.tipo_venta = h.tipo_venta
            AND l.cod_empresa = h.cod_empresa AND l.cod_caja = h.cod_caja
        INNER JOIN products_stock ps
            ON ps.cod_articulo = l.cod_articulo
            AND ps.batch_id = (SELECT active_batch_id FROM sync_state WHERE dataset = 'stock' LIMIT 1)
        LEFT JOIN family_dict hf
            ON hf.cod_familia = ps.cod_familia
        WHERE (EXTRACT(YEAR FROM h.fecha_venta) > p_year_from OR (EXTRACT(YEAR FROM h.fecha_venta) = p_year_from AND EXTRACT(MONTH FROM h.fecha_venta) >= p_month_from))
          AND (EXTRACT(YEAR FROM h.fecha_venta) < p_year_to OR (EXTRACT(YEAR FROM h.fecha_venta) = p_year_to AND EXTRACT(MONTH FROM h.fecha_venta) <= p_month_to))
          AND EXTRACT(YEAR FROM h.fecha_venta) >= 2025
          AND h.anulada = false
          AND ps.cod_familia IS NOT NULL AND ps.cod_familia <> ''
        GROUP BY ps.cod_familia
    )
    SELECT
        c.cod_familia::varchar,
        COALESCE(MAX(c.family_name), c.cod_familia)::varchar,
        SUM(c.sales)
    FROM combined c
    GROUP BY c.cod_familia
    ORDER BY SUM(c.sales) DESC
    LIMIT 10;
END;
$function$;
