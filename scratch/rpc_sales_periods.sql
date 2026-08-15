CREATE OR REPLACE FUNCTION public.get_store_dashboard_sales_periods(
    p_year integer DEFAULT NULL::integer
)
RETURNS json
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path TO 'public'
AS $function$
DECLARE
    v_year int;
    v_year_prev int;
    v_ref_date date;
    v_q_start date;
    v_q_end date;
    v_q_ant_start date;
    v_q_ant_end date;
    v_yant_start date;
    v_yant_end date;
    res json;
BEGIN
    v_year := COALESCE(p_year, EXTRACT(YEAR FROM CURRENT_DATE)::int);
    v_year_prev := v_year - 1;
    v_ref_date := CURRENT_DATE;

    IF EXTRACT(DAY FROM v_ref_date) >= 15 THEN
        v_q_start := date_trunc('month', v_ref_date)::date + 14;
        v_q_end := (date_trunc('month', v_ref_date) + INTERVAL '1 month - 1 day')::date;
        v_q_ant_start := date_trunc('month', v_ref_date)::date;
        v_q_ant_end := date_trunc('month', v_ref_date)::date + 13;
    ELSE
        v_q_start := date_trunc('month', v_ref_date)::date;
        v_q_end := date_trunc('month', v_ref_date)::date + 13;
        v_q_ant_start := (date_trunc('month', v_ref_date) - INTERVAL '1 month')::date + 14;
        v_q_ant_end := (date_trunc('month', v_ref_date) - INTERVAL '1 day')::date;
    END IF;

    v_yant_start := make_date(v_year_prev, 1, 1);
    v_yant_end := make_date(v_year_prev, EXTRACT(MONTH FROM v_ref_date)::int, EXTRACT(DAY FROM v_ref_date)::int);

    SELECT json_build_object(
        'quincena_actual', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date >= v_q_start AND fecha_venta::date <= v_q_end
                  AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'quincena_anterior', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date >= v_q_ant_start AND fecha_venta::date <= v_q_ant_end
                  AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'year', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE EXTRACT(YEAR FROM fecha_venta) = v_year
                  AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'year_ant_periodo', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date >= v_yant_start AND fecha_venta::date <= v_yant_end
                  AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'year_anterior', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE EXTRACT(YEAR FROM fecha_venta) = v_year_prev
                  AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        )
    ) INTO res;
    RETURN res;
END;
$function$;
