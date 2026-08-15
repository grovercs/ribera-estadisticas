CREATE OR REPLACE FUNCTION public.get_store_dashboard_sales(
    p_year integer DEFAULT NULL::integer,
    p_anio_ant text DEFAULT 'todos'::text
)
RETURNS json
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path TO 'public'
AS $function$
DECLARE
    v_year int;
    v_ultimo_dia date;
    v_penultimo_dia date;
    v_q_start date;
    v_q_end date;
    v_q_ant_start date;
    v_q_ant_end date;
    v_anio_base date;
    res json;
BEGIN
    v_year := COALESCE(p_year, EXTRACT(YEAR FROM CURRENT_DATE)::int);

    SELECT MAX(fecha_venta::date) INTO v_ultimo_dia
    FROM sales_headers
    WHERE tipo_venta IN (2, 4, 5) AND anulada IS NOT TRUE;
    IF v_ultimo_dia IS NULL THEN v_ultimo_dia := CURRENT_DATE; END IF;

    SELECT MAX(fecha_venta::date) INTO v_penultimo_dia
    FROM sales_headers
    WHERE tipo_venta IN (2, 4, 5) AND anulada IS NOT TRUE AND fecha_venta::date < v_ultimo_dia;
    IF v_penultimo_dia IS NULL THEN v_penultimo_dia := v_ultimo_dia - INTERVAL '1 day'; END IF;

    IF EXTRACT(DAY FROM CURRENT_DATE) >= 15 THEN
        v_q_start := date_trunc('month', CURRENT_DATE)::date + 14;
        v_q_end := (date_trunc('month', CURRENT_DATE) + INTERVAL '1 month - 1 day')::date;
        v_q_ant_start := date_trunc('month', CURRENT_DATE)::date;
        v_q_ant_end := date_trunc('month', CURRENT_DATE)::date + 13;
    ELSE
        v_q_start := date_trunc('month', CURRENT_DATE)::date;
        v_q_end := date_trunc('month', CURRENT_DATE)::date + 13;
        v_q_ant_start := (date_trunc('month', CURRENT_DATE) - INTERVAL '1 month')::date + 14;
        v_q_ant_end := (date_trunc('month', CURRENT_DATE) - INTERVAL '1 day')::date;
    END IF;

    IF p_anio_ant = 'todos' OR p_anio_ant IS NULL THEN
        v_anio_base := '2000-01-01'::date;
    ELSE
        v_anio_base := make_date(v_year - p_anio_ant::int, 1, 1);
    END IF;

    SELECT json_build_object(
        'ultimo_dia', v_ultimo_dia,
        'penultimo_dia', v_penultimo_dia,
        'hoy', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date = v_ultimo_dia AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'ayer', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date = v_penultimo_dia AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'quincena_actual', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date >= v_q_start AND fecha_venta::date <= v_q_end AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'quincena_anterior', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date >= v_q_ant_start AND fecha_venta::date <= v_q_ant_end AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        ),
        'anteriores', (
            SELECT COALESCE(json_agg(json_build_object('cod_almacen', cod_almacen, 'tickets', tickets, 'importe', importe)), '[]'::json)
            FROM (
                SELECT cod_almacen, COUNT(cod_venta) as tickets, SUM(total_amount) as importe
                FROM sales_headers
                WHERE fecha_venta::date >= v_anio_base AND fecha_venta::date < v_q_start AND tipo_venta IN (2,4,5) AND anulada IS NOT TRUE
                GROUP BY cod_almacen
            ) t
        )
    ) INTO res;
    RETURN res;
END;
$function$;
