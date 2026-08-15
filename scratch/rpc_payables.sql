CREATE OR REPLACE FUNCTION public.get_store_dashboard_payables()
RETURNS json
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path TO 'public'
AS $function$
DECLARE
    res json;
BEGIN
    SELECT json_build_object(
        'periodos', (
            SELECT COALESCE(json_agg(json_build_object(
                'periodo', periodo,
                'importe', total_pendiente,
                'ops', ops
            )), '[]'::json)
            FROM (
                SELECT periodo, SUM(importe_pendiente) as total_pendiente, COUNT(*) as ops
                FROM vendor_payables
                GROUP BY periodo
            ) t
        ),
        'total_importe', (SELECT COALESCE(SUM(importe_pendiente), 0) FROM vendor_payables),
        'total_ops', (SELECT COUNT(*) FROM vendor_payables)
    ) INTO res;
    RETURN res;
END;
$function$;
