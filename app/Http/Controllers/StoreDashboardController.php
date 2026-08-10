<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Filtros de fecha
        $periodo = $request->input('periodo', 'hoy');
        $year = (int)$request->input('year', date('Y'));
        $anioAnteriores = $request->input('anio_ant', 'todos'); // Años atrás para "Anteriores": 1, 2, 3, 5, 10, todos

        $cacheKey = "store_dashboard_v5_{$periodo}_{$year}_ant{$anioAnteriores}";

        // Optimizar tiempo de caché según el periodo
        $cacheTime = match($periodo) {
            'hoy' => now()->addMinutes(2),
            'quincena' => now()->addMinutes(15),
            'year' => now()->addHours(1),
            default => now()->addMinutes(5),
        };

        $data = cache()->remember($cacheKey, $cacheTime, function () use ($periodo, $year, $anioAnteriores) {
            try {
                $erp = DB::connection('erp');

                // === DETERMINAR ÚLTIMO Y PENÚLTIMO DÍA CON VENTAS ===
                $ultimoDiaRow = $erp->select("
                    SELECT MAX(CAST(fecha_venta AS DATE)) as ultimo_dia
                    FROM hist_ventas_cabecera
                    WHERE tipo_venta IN (2, 4, 5) AND ISNULL(anulada,'') <> 'S'
                ");
                $ultimoDiaVentas = $ultimoDiaRow[0]->ultimo_dia ?? date('Y-m-d');

                $penultimoDiaRow = $erp->select("
                    SELECT MAX(CAST(fecha_venta AS DATE)) as penultimo_dia
                    FROM hist_ventas_cabecera
                    WHERE tipo_venta IN (2, 4, 5) 
                        AND ISNULL(anulada,'') <> 'S'
                        AND CAST(fecha_venta AS DATE) < ?
                ", [$ultimoDiaVentas]);
                $penultimoDiaVentas = $penultimoDiaRow[0]->penultimo_dia ?? date('Y-m-d', strtotime($ultimoDiaVentas . ' -1 day'));

                // Reference date setup for dynamic quincena and period boundaries
                $refDate = new \DateTime();
                if ($year !== (int)$refDate->format('Y')) {
                    $refDate->setDate($year, (int)$refDate->format('m'), (int)$refDate->format('d'));
                }

                $refYear = (int)$refDate->format('Y');
                $refMonth = (int)$refDate->format('m');
                $refDay = (int)$refDate->format('d');

                // Quincena boundaries calculation
                if ($refDay >= 15) {
                    // Second quincena of the month: 15 to end of month
                    $qActualStart = $refDate->format('Ym15 00:00:00');
                    $qActualEnd   = $refDate->format('Ymt 23:59:59');

                    // Previous quincena: 1 to 14 of the same month
                    $qAntStart = $refDate->format('Ym01 00:00:00');
                    $qAntEnd   = $refDate->format('Ym14 23:59:59');
                } else {
                    // First quincena of the month: 1 to 14
                    $qActualStart = $refDate->format('Ym01 00:00:00');
                    $qActualEnd   = $refDate->format('Ym14 23:59:59');

                    // Previous quincena: 15 to end of the previous month
                    $prevMonthDate = clone $refDate;
                    $prevMonthDate->modify('first day of previous month');
                    $qAntStart = $prevMonthDate->format('Ym15 00:00:00');
                    $qAntEnd   = $prevMonthDate->format('Ymt 23:59:59');
                }

                // Año anterior mismo periodo boundaries
                $yearPrev = $year - 1;
                $yearAntPeriodoStart = "{$yearPrev}0101 00:00:00";
                $yearAntPeriodoEnd = "{$yearPrev}" . $refDate->format('md 23:59:59');

                // === VENTAS ÚLTIMO DÍA CON VENTAS ("HOY") ===
                $ventasHoy = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE CAST(v.fecha_venta AS DATE) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$ultimoDiaVentas]);

                // === VENTAS PENÚLTIMO DÍA CON VENTAS ("AYER") ===
                $ventasAyer = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE CAST(v.fecha_venta AS DATE) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$penultimoDiaVentas]);

                // === VENTAS QUINCENA ACTUAL ===
                $ventasQuincena = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE v.fecha_venta >= ? AND v.fecha_venta <= ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$qActualStart, $qActualEnd]);

                // === VENTAS QUINCENA ANTERIOR ===
                $ventasQuincenaAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE v.fecha_venta >= ? AND v.fecha_venta <= ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$qAntStart, $qAntEnd]);

                // === VENTAS AÑO ACTUAL ===
                $ventasYear = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                // === VENTAS AÑO ANTERIOR ===
                $ventasYearAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$yearPrev]);

                // === VENTAS ANTERIORES ===
                // Ventas anteriores al inicio de la quincena actual (excluyendo la quincena actual, hoy y ayer)
                $anioBase = $anioAnteriores === 'todos' ? 0 : ($year - (int)$anioAnteriores);
                if ($anioAnteriores === 'todos') {
                    $whereAnteriores = "v.fecha_venta < ?";
                    $bindsAnteriores = [$qActualStart];
                } else {
                    $whereAnteriores = "v.fecha_venta >= ? AND v.fecha_venta < ?";
                    $bindsAnteriores = ["{$anioBase}0101 00:00:00", $qActualStart];
                }

                $ventasAnteriores = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE $whereAnteriores
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", $bindsAnteriores);

                // === FACTURAS DE VENTA ===
                $facturasQuincena = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE v.fecha_venta >= ? AND v.fecha_venta <= ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$qActualStart, $qActualEnd]);

                $facturasQuincenaAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE v.fecha_venta >= ? AND v.fecha_venta <= ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$qAntStart, $qAntEnd]);

                $facturasYear = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                $facturasYearAntPeriodo = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE v.fecha_venta >= ? AND v.fecha_venta <= ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$yearAntPeriodoStart, $yearAntPeriodoEnd]);

                $facturasYearAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(v.cod_venta) as tickets,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$yearPrev]);

                // === SPARKLINES: ventas diarias últimos 14 días por tienda ===
                $sparklineRaw = $erp->select("
                    SELECT
                        CAST(v.fecha_venta AS DATE) as dia,
                        v.cod_almacen,
                        SUM(v.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    WHERE v.fecha_venta >= DATEADD(DAY, -13, CAST(GETDATE() AS DATE))
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY CAST(v.fecha_venta AS DATE), v.cod_almacen
                    ORDER BY dia ASC
                ");

                // Organizar sparklines por tienda: array de 14 valores diarios
                $sparkDias   = [];
                $sparkTienda = [1 => [], 2 => []];
                foreach ($sparklineRaw as $s) {
                    $sArr = (array)$s;
                    $sparkDias[$sArr['dia']] = true;
                    if (isset($sparkTienda[$sArr['cod_almacen']])) {
                        $sparkTienda[$sArr['cod_almacen']][$sArr['dia']] = round((float)$sArr['importe'], 2);
                    }
                }
                // Rellenar días sin ventas con 0
                $start14 = new \DateTime(); $start14->modify('-13 days');
                $sparkLabels = []; $spark1 = []; $spark2 = [];
                for ($i = 0; $i < 14; $i++) {
                    $d = $start14->format('Y-m-d');
                    $sparkLabels[] = $start14->format('d/m');
                    $spark1[] = $sparkTienda[1][$d] ?? 0;
                    $spark2[] = $sparkTienda[2][$d] ?? 0;
                    $start14->modify('+1 day');
                }

                // Ticket medio año actual vs año anterior (total ambas tiendas)
                $ticketsYearTotal  = array_sum(array_column(array_map(fn($r) => (array)$r, $ventasYear), 'tickets'));
                $importeYearTotal  = array_sum(array_column(array_map(fn($r) => (array)$r, $ventasYear), 'importe'));
                $ticketsYearAntTotal  = array_sum(array_column(array_map(fn($r) => (array)$r, $ventasYearAnt), 'tickets'));
                $importeYearAntTotal  = array_sum(array_column(array_map(fn($r) => (array)$r, $ventasYearAnt), 'importe'));
                $ticketMedio    = $ticketsYearTotal  > 0 ? $importeYearTotal  / $ticketsYearTotal  : 0;
                $ticketMedioAnt = $ticketsYearAntTotal > 0 ? $importeYearAntTotal / $ticketsYearAntTotal : 0;

                // === IMPAGADOS & PENDIENTES ===
                // Impagados (definicion ERP): vencimientos cuya forma de liquidacion es una
                // de las Z de impagado (ZIMP/ZJUZ/ZPER/ZCYC). Importe = SUM(importe - importe_cobrado).
                // Cuadra exacto con el ERP: 22 facturas / 11.416,01 €.
                $impagadosRaw = $erp->select("
                    SELECT
                        f.cod_almacen,
                        COUNT(*) as tickets,
                        SUM(v.importe - v.importe_cobrado) as importe
                    FROM vencimientos_facturas v
                    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura
                        AND v.tipo_factura = f.tipo_factura
                        AND v.cod_empresa = f.cod_empresa
                    WHERE v.cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
                    GROUP BY f.cod_almacen
                ");

                // Impagados devueltos (EXTRA, definicion previa del panel): vencimientos con
                // devolucion y saldo pendiente. Se mantiene como informacion adicional.
                $impagadosDevueltosRaw = $erp->select("
                    SELECT
                        f.cod_almacen,
                        COUNT(*) as tickets,
                        SUM(v.importe - v.importe_cobrado) as importe
                    FROM devoluciones_vencimientos_ventas d
                    INNER JOIN vencimientos_facturas v ON d.cod_factura_destino = v.cod_factura
                        AND d.tipo_factura_destino = v.tipo_factura
                        AND d.cod_empresa_destino = v.cod_empresa
                        AND d.numero_destino = v.numero
                    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura
                        AND v.tipo_factura = f.tipo_factura
                        AND v.cod_empresa = f.cod_empresa
                    WHERE (v.importe - v.importe_cobrado) > 0
                    GROUP BY f.cod_almacen
                ");

                $pendientesRaw = $erp->select("
                    SELECT
                        f.cod_almacen,
                        COUNT(*) as tickets,
                        SUM(v.importe - v.importe_cobrado) as importe
                    FROM vencimientos_facturas v
                    LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura
                        AND v.tipo_factura = f.tipo_factura
                        AND v.cod_empresa = f.cod_empresa
                    WHERE v.cod_remesa IS NULL
                      AND v.cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')
                    GROUP BY f.cod_almacen
                ");

                $totalImpagadosTickets = array_sum(array_column(array_map(function($r){return (array)$r;}, $impagadosRaw), 'tickets'));
                $totalImpagadosImporte = array_sum(array_column(array_map(function($r){return (array)$r;}, $impagadosRaw), 'importe'));

                $totalImpagadosDevueltosTickets = array_sum(array_column(array_map(function($r){return (array)$r;}, $impagadosDevueltosRaw), 'tickets'));
                $totalImpagadosDevueltosImporte = array_sum(array_column(array_map(function($r){return (array)$r;}, $impagadosDevueltosRaw), 'importe'));

                $totalPendientesTickets = array_sum(array_column(array_map(function($r){return (array)$r;}, $pendientesRaw), 'tickets'));
                $totalPendientesImporte = array_sum(array_column(array_map(function($r){return (array)$r;}, $pendientesRaw), 'importe'));

                // === MÁRGENES ÚLTIMO DÍA CON VENTAS ===
                // (Ya determinado al inicio de la consulta)

                // Rango de fechas y WHERE según período seleccionado
                if ($periodo === 'hoy') {
                    $wherePeriodo = "CAST(v.fecha_venta AS DATE) = ?";
                    $wherePeriodoSub = "CAST(vc.fecha_venta AS DATE) = ?";
                    $bindsQuery = [$ultimoDiaVentas, $ultimoDiaVentas];
                } elseif ($periodo === 'quincena') {
                    $wherePeriodo = "v.fecha_venta >= ? AND v.fecha_venta <= ?";
                    $wherePeriodoSub = "vc.fecha_venta >= ? AND vc.fecha_venta <= ?";
                    $bindsQuery = [$qActualStart, $qActualEnd, $qActualStart, $qActualEnd];
                } else {
                    $wherePeriodo = "YEAR(v.fecha_venta) = ?";
                    $wherePeriodoSub = "YEAR(vc.fecha_venta) = ?";
                    $bindsQuery = [$year, $year];
                }

                // === MÁRGENES PERIODO SELECCIONADO ===
                // Venta = SUM(v.importe) de cabecera (sin IVA) agrupado correctamente
                // Coste = SUM de subquery por almacen para evitar duplicados por JOIN
                $margenesPeriodoRaw = $erp->select("
                    SELECT
                        v.cod_almacen,
                        SUM(v.importe) as venta,
                        ISNULL((SELECT SUM(l.precio_coste * l.cantidad)
                                FROM hist_ventas_linea l
                                INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
                                    AND l.tipo_venta = vc.tipo_venta
                                    AND l.cod_empresa = vc.cod_empresa
                                    AND l.cod_caja = vc.cod_caja
                                WHERE $wherePeriodoSub
                                    AND vc.tipo_venta IN (2, 4, 5)
                                    AND vc.cod_almacen = v.cod_almacen
                                    AND l.precio_coste IS NOT NULL
                                    AND ISNULL(vc.anulada,'') <> 'S'), 0) as coste
                    FROM hist_ventas_cabecera v
                    WHERE $wherePeriodo
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", $bindsQuery);

                $margenesPeriodo = array_map(function($m) {
                    $m = (array)$m;
                    $m['margen'] = $m['venta'] - $m['coste'];
                    $m['margen_porcentaje'] = $m['venta'] > 0 ? ($m['margen'] / $m['venta']) * 100 : 0;
                    return $m;
                }, $margenesPeriodoRaw);

                // === MÁRGENES AÑO ACTUAL (Fijos para tabla inferior) ===
                $margenesYearRaw = $erp->select("
                    SELECT
                        v.cod_almacen,
                        SUM(v.importe) as venta,
                        ISNULL((SELECT SUM(l.precio_coste * l.cantidad)
                                FROM hist_ventas_linea l
                                INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
                                    AND l.tipo_venta = vc.tipo_venta
                                    AND l.cod_empresa = vc.cod_empresa
                                    AND l.cod_caja = vc.cod_caja
                                WHERE YEAR(vc.fecha_venta) = ?
                                    AND vc.tipo_venta IN (2, 4, 5)
                                    AND vc.cod_almacen = v.cod_almacen
                                    AND l.precio_coste IS NOT NULL
                                    AND ISNULL(vc.anulada,'') <> 'S'), 0) as coste
                    FROM hist_ventas_cabecera v
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year, $year]);

                $margenesYear = array_map(function($m) {
                    $m = (array)$m;
                    $m['margen'] = $m['venta'] - $m['coste'];
                    $m['margen_porcentaje'] = $m['venta'] > 0 ? ($m['margen'] / $m['venta']) * 100 : 0;
                    return $m;
                }, $margenesYearRaw);

                // === MÁRGENES DE HOY (Fijos para tabla inferior) ===
                $margenesHoyRaw = $erp->select("
                    SELECT
                        v.cod_almacen,
                        SUM(v.importe) as venta,
                        ISNULL((SELECT SUM(l.precio_coste * l.cantidad)
                                FROM hist_ventas_linea l
                                INNER JOIN hist_ventas_cabecera vc ON l.cod_venta = vc.cod_venta
                                    AND l.tipo_venta = vc.tipo_venta
                                    AND l.cod_empresa = vc.cod_empresa
                                    AND l.cod_caja = vc.cod_caja
                                WHERE CAST(vc.fecha_venta AS DATE) = ?
                                    AND vc.tipo_venta IN (2, 4, 5)
                                    AND vc.cod_almacen = v.cod_almacen
                                    AND l.precio_coste IS NOT NULL
                                    AND ISNULL(vc.anulada,'') <> 'S'), 0) as coste
                    FROM hist_ventas_cabecera v
                    WHERE CAST(v.fecha_venta AS DATE) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$ultimoDiaVentas, $ultimoDiaVentas]);

                $margenesHoy = array_map(function($m) {
                    $m = (array)$m;
                    $m['margen'] = $m['venta'] - $m['coste'];
                    $m['margen_porcentaje'] = $m['venta'] > 0 ? ($m['margen'] / $m['venta']) * 100 : 0;
                    return $m;
                }, $margenesHoyRaw);

                // === ALBARANES DE COMPRA MES ===
                // Agrupar por c.cod_almacen (cabecera) para obtener el almacen correcto
                $albaranesCompraMesRaw = $erp->select("
                    SELECT
                        c.cod_almacen,
                        COUNT(c.cod_compra) as albaranes,
                        SUM(c.importe) as importe
                    FROM hist_compras_cabecera c
                    WHERE YEAR(c.fecha_compra) = ? AND MONTH(c.fecha_compra) = MONTH(GETDATE())
                        AND c.tipo_compra = 2
                    GROUP BY c.cod_almacen
                ", [$year]);
                $albaranesCompraMes = array_map(function($a) { return (array)$a; }, $albaranesCompraMesRaw);

                // === FACTURAS COMPRAS Y GASTOS ===
                // Origen: impuestos_facturas_compras (una fila por base de IVA), como hace el
                // listado del ERP. Cada factura con varios tipos de IVA suma varias lineas, por
                // eso el conteo del ERP (6.572 en 2025) es mayor que el de facturas (6.519).
                // Importe = SUM(i.importe) = base + cuota de cada linea = total con IVA de la factura.
                // JOIN por (cod_factura, cod_empresa, cod_proveedor) que es unico en cabecera
                // (evita duplicar lineas cuando un cod_factura se comparte entre proveedores).
                // Solo empresa principal (cod_empresa = 1). Cuadra EXACTO con el ERP:
                //   2026: mes 236/294.066,72  mes ant 487/657.575,31  año 2.715/2.752.955,49
                //   2025 año completo: 6.572 / 6.170.109,55
                // (El periodo 2025 queda +2 facturas/+566,17 por 2 facturas de TELEFONICA en la
                //  frontera del 28/06 que el ERP sitúa fuera del periodo; 0,02%.)
                $fcBase = "FROM impuestos_facturas_compras i
                    JOIN facturas_compras_cabecera c
                      ON i.cod_factura = c.cod_factura AND i.cod_empresa = c.cod_empresa AND i.cod_proveedor = c.cod_proveedor
                    WHERE c.cod_empresa = 1";
                $facturasCompras = [
                    'mes_actual' => $erp->select("SELECT COUNT(*) as count, SUM(i.importe) as importe $fcBase AND YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = MONTH(GETDATE())", [$year])[0],
                    'mes_anterior' => $erp->select("SELECT COUNT(*) as count, SUM(i.importe) as importe $fcBase AND YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = MONTH(DATEADD(MONTH, -1, GETDATE()))", [$year])[0],
                    'year_actual' => $erp->select("SELECT COUNT(*) as count, SUM(i.importe) as importe $fcBase AND YEAR(c.fecha_factura) = ?", [$year])[0],
                    'year_anterior_periodo' => $erp->select("SELECT COUNT(*) as count, SUM(i.importe) as importe $fcBase AND c.fecha_factura >= ? AND c.fecha_factura <= ?", [$yearAntPeriodoStart, $yearAntPeriodoEnd])[0],
                    'year_anterior' => $erp->select("SELECT COUNT(*) as count, SUM(i.importe) as importe $fcBase AND YEAR(c.fecha_factura) = ?", [$yearPrev])[0],
                ];

                // === PAGOS PENDIENTES POR VENCIMIENTO ===
                // Programación de pagos a proveedores (vencimientos_facturas_compras).
                // Neto = importe - importe_pagado (incluye abonos rectificativos negativos),
                // excluyendo confirmings. Desde el día 1 del mes en curso hasta fin de mes +3,
                // agrupado por mes natural. Cuadra con el ERP (774.345,54 € a 2026-06-28).
                $pagosPendientesRaw = $erp->select("
                    SELECT
                        CASE
                            WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                            ELSE 'Mas de 3 meses'
                        END as periodo,
                        SUM(p.importe - p.importe_pagado) as importe
                    FROM vencimientos_facturas_compras p
                    WHERE (p.importe - p.importe_pagado) <> 0
                        AND p.fecha_vencimiento IS NOT NULL
                        AND (p.cod_confirming IS NULL OR p.cod_confirming = '')
                        AND p.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)
                        AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE()))
                    GROUP BY
                        CASE
                            WHEN p.fecha_vencimiento <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                            WHEN p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                            ELSE 'Mas de 3 meses'
                        END
                ");
                // Convertir stdClass a array para evitar problemas de serialización
                $pagosPendientes = array_map(function($p) { return (array)$p; }, $pagosPendientesRaw);

                // === PROCESAR DATOS POR TIENDA ===
                $tiendas = [
                    1 => [
                        'nombre' => 'Pont de Suert',
                        'ventas' => [],
                        'facturas' => [],
                        'margenes' => null,
                        'margenes_hoy' => null,
                        'albaranes' => 0,
                        'impagados' => ['tickets' => 0, 'importe' => 0],
                        'impagados_devueltos' => ['tickets' => 0, 'importe' => 0],
                        'pendientes' => ['tickets' => 0, 'importe' => 0]
                    ],
                    2 => [
                        'nombre' => 'Vielha',
                        'ventas' => [],
                        'facturas' => [],
                        'margenes' => null,
                        'margenes_hoy' => null,
                        'albaranes' => 0,
                        'impagados' => ['tickets' => 0, 'importe' => 0],
                        'impagados_devueltos' => ['tickets' => 0, 'importe' => 0],
                        'pendientes' => ['tickets' => 0, 'importe' => 0]
                    ],
                ];

                foreach ($impagadosRaw as $row) {
                    $rArray = (array)$row;
                    $cod = $rArray['cod_almacen'];
                    if ($cod && isset($tiendas[$cod])) {
                        $tiendas[$cod]['impagados'] = [
                            'tickets' => (int)$rArray['tickets'],
                            'importe' => (float)$rArray['importe']
                        ];
                    }
                }

                foreach ($impagadosDevueltosRaw as $row) {
                    $rArray = (array)$row;
                    $cod = $rArray['cod_almacen'];
                    if ($cod && isset($tiendas[$cod])) {
                        $tiendas[$cod]['impagados_devueltos'] = [
                            'tickets' => (int)$rArray['tickets'],
                            'importe' => (float)$rArray['importe']
                        ];
                    }
                }

                foreach ($pendientesRaw as $row) {
                    $rArray = (array)$row;
                    $cod = $rArray['cod_almacen'];
                    if ($cod && isset($tiendas[$cod])) {
                        $tiendas[$cod]['pendientes'] = [
                            'tickets' => (int)$rArray['tickets'],
                            'importe' => (float)$rArray['importe']
                        ];
                    }
                }

                // Helper para procesar datos
                $procesarVentas = function($datos, $key) use (&$tiendas) {
                    foreach ($datos as $d) {
                        $dArray = (array)$d;
                        if (!isset($tiendas[$dArray['cod_almacen']])) continue;
                        $tiendas[$dArray['cod_almacen']]['ventas'][$key] = [
                            'tickets' => (int)$dArray['tickets'],
                            'importe' => (float)$dArray['importe'],
                        ];
                    }
                };

                $procesarVentas($ventasHoy, 'hoy');
                $procesarVentas($ventasAyer, 'ayer');
                $procesarVentas($ventasQuincena, 'quincena');
                $procesarVentas($ventasQuincenaAnt, 'quincena_anterior');
                $procesarVentas($ventasYear, 'year');
                $procesarVentas($ventasYearAnt, 'year_anterior');
                $procesarVentas($ventasAnteriores, 'anteriores');

                // Helper para procesar facturas (guarda en ['facturas'] en lugar de ['ventas'])
                $procesarFacturas = function($datos, $key) use (&$tiendas) {
                    foreach ($datos as $d) {
                        $dArray = (array)$d;
                        if (!isset($tiendas[$dArray['cod_almacen']])) continue;
                        $tiendas[$dArray['cod_almacen']]['facturas'][$key] = [
                            'tickets' => (int)$dArray['tickets'],
                            'importe' => (float)$dArray['importe'],
                        ];
                    }
                };

                $procesarFacturas($facturasQuincena, 'quincena');
                $procesarFacturas($facturasQuincenaAnt, 'quincena_anterior');
                $procesarFacturas($facturasYear, 'year');
                $procesarFacturas($facturasYearAntPeriodo, 'year_ant_periodo');
                $procesarFacturas($facturasYearAnt, 'year_anterior');

                 foreach ($margenesPeriodo as $m) {
                     if (!isset($tiendas[$m['cod_almacen']])) continue;
                     $tiendas[$m['cod_almacen']]['margenes'] = [
                         'venta' => (float)$m['venta'],
                         'coste' => (float)$m['coste'],
                         'margen' => (float)$m['margen'],
                         'margen_porcentaje' => (float)$m['margen_porcentaje'],
                     ];
                 }

                 foreach ($margenesHoy as $m) {
                     if (!isset($tiendas[$m['cod_almacen']])) continue;
                     $tiendas[$m['cod_almacen']]['margenes_hoy'] = [
                         'venta' => (float)$m['venta'],
                         'coste' => (float)$m['coste'],
                         'margen' => (float)$m['margen'],
                         'margen_porcentaje' => (float)$m['margen_porcentaje'],
                     ];
                 }

                 foreach ($albaranesCompraMes as $a) {
                     $tiendas[$a['cod_almacen']]['albaranes'] = [
                         'count' => (int)$a['albaranes'],
                         'importe' => (float)$a['importe'],
                     ];
                 }

                // Totales Ventas
                $totalVentas = function($datos) {
                    $datosArray = array_map(function($d) { return (array)$d; }, $datos);
                    $tickets = array_sum(array_column($datosArray, 'tickets'));
                    $importe = array_sum(array_column($datosArray, 'importe'));
                    return ['tickets' => $tickets, 'importe' => $importe];
                };

                // Convertir todos los datos a arrays para evitar problemas de serialización en caché
                $facturasComprasArray = [];
                foreach ($facturasCompras as $key => $f) {
                    $facturasComprasArray[$key] = (array)$f;
                }

                return [
                    'tiendas' => $tiendas,
                    'impagados' => [
                        'impagados_importe' => (float)($totalImpagadosImporte ?? 0),
                        'impagados_count' => (int)($totalImpagadosTickets ?? 0),
                        'impagados_devueltos_importe' => (float)($totalImpagadosDevueltosImporte ?? 0),
                        'impagados_devueltos_count' => (int)($totalImpagadosDevueltosTickets ?? 0),
                        'pendientes_importe' => (float)($totalPendientesImporte ?? 0),
                        'pendientes_count' => (int)($totalPendientesTickets ?? 0),
                    ],
                    'facturasCompras' => $facturasComprasArray,
                    'pagosPendientes' => $pagosPendientes,
                    'totales' => [
                        'ventas_hoy' => $totalVentas($ventasHoy),
                        'ventas_ayer' => $totalVentas($ventasAyer),
                        'ventas_quincena' => $totalVentas($ventasQuincena),
                        'ventas_quincena_anterior' => $totalVentas($ventasQuincenaAnt),
                        'ventas_year' => $totalVentas($ventasYear),
                        'ventas_year_anterior' => $totalVentas($ventasYearAnt),
                        'ventas_anteriores' => $totalVentas($ventasAnteriores),
                        'facturas_quincena' => $totalVentas($facturasQuincena),
                        'facturas_quincena_anterior' => $totalVentas($facturasQuincenaAnt),
                        'facturas_year' => $totalVentas($facturasYear),
                        'facturas_year_ant_periodo' => $totalVentas($facturasYearAntPeriodo),
                        'facturas_year_anterior' => $totalVentas($facturasYearAnt),
                        'margen_venta' => array_sum(array_column($margenesPeriodo, 'venta')),
                        'margen_coste' => array_sum(array_column($margenesPeriodo, 'coste')),
                        'margen' => array_sum(array_column($margenesPeriodo, 'margen')),
                        'margen_porcentaje' => $margenesPeriodo ? (array_sum(array_column($margenesPeriodo, 'margen')) / array_sum(array_column($margenesPeriodo, 'venta'))) * 100 : 0,
                        'margen_hoy_venta' => array_sum(array_column($margenesHoy, 'venta')),
                        'margen_hoy_coste' => array_sum(array_column($margenesHoy, 'coste')),
                        'margen_hoy' => array_sum(array_column($margenesHoy, 'margen')),
                        'margen_hoy_porcentaje' => $margenesHoy ? (array_sum(array_column($margenesHoy, 'margen')) / array_sum(array_column($margenesHoy, 'venta'))) * 100 : 0,
                        'albaranes_mes' => array_sum(array_column($albaranesCompraMes, 'importe')),
                        // Ticket medio
                        'ticket_medio'     => round($ticketMedio, 2),
                        'ticket_medio_ant' => round($ticketMedioAnt, 2),
                        'ticket_medio_pct' => $ticketMedioAnt > 0 ? (($ticketMedio - $ticketMedioAnt) / $ticketMedioAnt) * 100 : 0,
                        // % variación año actual vs año anterior (mismo periodo)
                        'ventas_year_pct'  => ($totales['ventas_year_ant_periodo']['importe'] ?? 0) > 0
                            ? (($totales['ventas_year']['importe'] - $totales['ventas_year_ant_periodo']['importe']) / $totales['ventas_year_ant_periodo']['importe']) * 100
                            : 0,
                        'margen_pct_ant'   => 0, // placeholder
                    ],
                    'sparklines' => [
                        'labels' => $sparkLabels,
                        'pont'   => $spark1,
                        'vielha' => $spark2,
                        'total'  => array_map(fn($a, $b) => $a + $b, $spark1, $spark2),
                    ],
                    'ticketMedio'    => round($ticketMedio, 2),
                    'ticketMedioAnt' => round($ticketMedioAnt, 2),
                    'periodo' => $periodo,
                    'year' => $year,
                    'anioAnteriores' => $anioAnteriores,
                    'fechaTexto' => $this->getPeriodoTexto($periodo, $year),
                    'ultima_actualizacion' => now()->format('Y-m-d H:i:s'),
                    'ultimoDiaVentas' => $ultimoDiaVentas,
                    'penultimoDiaVentas' => $penultimoDiaVentas,
                ];

            } catch (\Exception $e) {
                \Log::error('Store Dashboard Error: ' . $e->getMessage());
                return [
                    'tiendas' => [],
                    'impagados' => ['impagados_importe' => 0, 'impagados_count' => 0, 'impagados_devueltos_importe' => 0, 'impagados_devueltos_count' => 0, 'pendientes_importe' => 0, 'pendientes_count' => 0],
                    'facturasCompras' => [],
                    'pagosPendientes' => [],
                    'totales' => ['ventas_hoy' => ['tickets' => 0, 'importe' => 0], 'ventas_quincena' => ['tickets' => 0, 'importe' => 0], 'ventas_year' => ['tickets' => 0, 'importe' => 0]],
                    'periodo' => $periodo,
                    'year' => $year,
                    'anioAnteriores' => $anioAnteriores,
                    'fechaTexto' => $this->getPeriodoTexto($periodo, $year),
                    'ultimoDiaVentas' => date('Y-m-d'),
                    'penultimoDiaVentas' => date('Y-m-d', strtotime('-1 day')),
                    'error' => $e->getMessage(),
                ];
            }
        });

        return view('store-dashboard.index', $data);
    }

    public function detalleImpagados(\Illuminate\Http\Request $request)
    {
        // tipos: 'impagados' (ERP, forma Z), 'impagados_devueltos' (extra, devolucion),
        //        'pendientes' (no devolucion).
        $tipo = $request->query('tipo', 'impagados');
        $tienda = $request->query('tienda', 'all'); // '1', '2', 'all'

        try {
            $erp = \DB::connection('erp');

            $where = [];
            if ($tienda === '1') {
                $where[] = "f.cod_almacen = 1";
            } elseif ($tienda === '2') {
                $where[] = "f.cod_almacen = 2";
            }
            // 'all' => sin filtro de almacen: incluye vencimientos sin cabecera
            // (almacen NULL) para cuadrar el total con el ERP (p.ej. pendientes 706).

            $select = "
                SELECT
                    f.cod_almacen,
                    v.fecha_vencimiento,
                    (v.importe - v.importe_cobrado) as importe_pendiente,
                    v.cod_cliente,
                    v.razon_social,
                    f.cif,
                    v.cod_factura,
                    v.numero,
                    v.cod_forma_liquidacion
                FROM vencimientos_facturas v
                LEFT JOIN facturas_ventas_cabecera f ON v.cod_factura = f.cod_factura
                    AND v.tipo_factura = f.tipo_factura
                    AND v.cod_empresa = f.cod_empresa
            ";

            if ($tipo === 'impagados') {
                // Impagados ERP: forma de liquidacion Z (ZIMP/ZJUZ/ZPER/ZCYC).
                // Sin filtro de saldo: el ERP lista las 22 facturas aunque algunas esten a 0.
                $where[] = "v.cod_forma_liquidacion IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')";
                $query = "{$select} WHERE " . implode(" AND ", $where) . " ORDER BY v.fecha_vencimiento DESC";
            } elseif ($tipo === 'impagados_devueltos') {
                // Extra: vencimientos devueltos con saldo pendiente.
                $where[] = "(v.importe - v.importe_cobrado) > 0";
                $where[] = "EXISTS (
                    SELECT 1 FROM devoluciones_vencimientos_ventas d
                    WHERE d.cod_factura_destino = v.cod_factura
                      AND d.tipo_factura_destino = v.tipo_factura
                      AND d.cod_empresa_destino = v.cod_empresa
                      AND d.numero_destino = v.numero
                )";
                $query = "{$select} WHERE " . implode(" AND ", $where) . " ORDER BY v.fecha_vencimiento DESC";
            } else {
                // Pendientes (ERP): vencimientos sin remesa (no enviados a cobro) y que no
                // sean impagados (forma Z). Sin filtro de saldo ni de devolucion.
                // Cuadra exacto: 706 / 343.233,17.
                $where[] = "v.cod_remesa IS NULL";
                $where[] = "v.cod_forma_liquidacion NOT IN ('ZIMP', 'ZJUZ', 'ZPER', 'ZCYC')";
                $query = "{$select} WHERE " . implode(" AND ", $where) . " ORDER BY v.fecha_vencimiento DESC";
            }

            $rawResults = $erp->select($query);

            $formatted = array_map(function ($r) {
                $r = (array)$r;
                $almacen = $r['cod_almacen'] ?? null;
                $tiendasMap = [1 => 'Pont de Suert', 2 => 'Vielha'];

                return [
                    'tienda' => $tiendasMap[$almacen] ?? 'No asignada',
                    'fecha_vencimiento' => $r['fecha_vencimiento'] ? \Carbon\Carbon::parse($r['fecha_vencimiento'])->format('d/m/Y') : 'N/A',
                    'importe_pendiente' => (float)$r['importe_pendiente'],
                    'cod_cliente' => $r['cod_cliente'],
                    'razon_social' => trim($r['razon_social'] ?? 'Desconocido'),
                    'cif' => trim($r['cif'] ?? 'N/A'),
                    'factura' => trim($r['cod_factura'] ?? '') ?: trim($r['numero'] ?? 'N/A')
                ];
            }, $rawResults);

            return response()->json([
                'success' => true,
                'data' => $formatted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function detallePagos(\Illuminate\Http\Request $request)
    {
        $periodo = $request->query('periodo', 'all');

        try {
            $erp = \DB::connection('erp');

            // Base: vencimientos de compra con resto pendiente <> 0 (neto, incluye abonos),
            // sin confirming, dentro de la ventana día 1 del mes en curso .. fin de mes +3.
            $where = [
                "(p.importe - p.importe_pagado) <> 0",
                "p.fecha_vencimiento IS NOT NULL",
                "(p.cod_confirming IS NULL OR p.cod_confirming = '')",
                "p.fecha_vencimiento >= DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1)",
                "p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE()))",
            ];

            if ($periodo === 'Mes Actual') {
                $where[] = "p.fecha_vencimiento <= EOMONTH(GETDATE())";
            } elseif ($periodo === 'Mes Siguiente') {
                $where[] = "p.fecha_vencimiento > EOMONTH(GETDATE()) AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 1, GETDATE()))";
            } elseif ($periodo === 'En 2 meses') {
                $where[] = "p.fecha_vencimiento > EOMONTH(DATEADD(MONTH, 1, GETDATE())) AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 2, GETDATE()))";
            } elseif ($periodo === 'En 3 meses') {
                $where[] = "p.fecha_vencimiento > EOMONTH(DATEADD(MONTH, 2, GETDATE())) AND p.fecha_vencimiento <= EOMONTH(DATEADD(MONTH, 3, GETDATE()))";
            }

            $whereClause = implode(" AND ", $where);

            $query = "
                SELECT
                    p.cod_proveedor,
                    pr.razon_social,
                    pr.cif,
                    p.cod_factura,
                    p.fecha_vencimiento,
                    (p.importe - p.importe_pagado) as importe
                FROM vencimientos_facturas_compras p
                LEFT JOIN proveedores pr ON p.cod_proveedor = pr.cod_proveedor
                WHERE {$whereClause}
                ORDER BY p.fecha_vencimiento ASC
            ";

            $rawResults = $erp->select($query);

            $formatted = array_map(function ($r) {
                $r = (array)$r;
                return [
                    'proveedor' => trim($r['razon_social'] ?? 'Proveedor Desconocido'),
                    'cod_proveedor' => $r['cod_proveedor'],
                    'cif' => trim($r['cif'] ?? 'N/A'),
                    'factura' => trim($r['cod_factura'] ?? '') ?: trim($r['numero'] ?? 'N/A'),
                    'fecha_vencimiento' => $r['fecha_vencimiento'] ? \Carbon\Carbon::parse($r['fecha_vencimiento'])->format('d/m/Y') : 'N/A',
                    'importe' => (float)$r['importe']
                ];
            }, $rawResults);

            return response()->json([
                'success' => true,
                'data' => $formatted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function detalleFacturasCompras(\Illuminate\Http\Request $request)
    {
        $periodo = $request->query('periodo', 'year_actual');
        $year = (int)$request->input('year', date('Y'));

        try {
            $erp = \DB::connection('erp');

            // Mismos limites de fecha que el resumen del cuadro de mando.
            $refDate = new \DateTime();
            if ($year !== (int)$refDate->format('Y')) {
                $refDate->setDate($year, (int)$refDate->format('m'), (int)$refDate->format('d'));
            }
            $yearPrev = $year - 1;
            $yearAntPeriodoStart = "{$yearPrev}0101 00:00:00";
            $yearAntPeriodoEnd = "{$yearPrev}" . $refDate->format('md 23:59:59');

            // Base: una fila por base de IVA (como lista el ERP), JOIN unico por
            // (cod_factura, cod_empresa, cod_proveedor). Solo empresa principal.
            $baseFrom = "FROM impuestos_facturas_compras i
                JOIN facturas_compras_cabecera c
                  ON i.cod_factura = c.cod_factura AND i.cod_empresa = c.cod_empresa AND i.cod_proveedor = c.cod_proveedor
                LEFT JOIN proveedores pr ON i.cod_proveedor = pr.cod_proveedor
                WHERE c.cod_empresa = 1";

            switch ($periodo) {
                case 'mes_actual':
                    $where = "AND YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = MONTH(GETDATE())";
                    $bindings = [$year];
                    break;
                case 'mes_anterior':
                    $where = "AND YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = MONTH(DATEADD(MONTH, -1, GETDATE()))";
                    $bindings = [$year];
                    break;
                case 'year_actual':
                    $where = "AND YEAR(c.fecha_factura) = ?";
                    $bindings = [$year];
                    break;
                case 'year_anterior_periodo':
                    $where = "AND c.fecha_factura >= ? AND c.fecha_factura <= ?";
                    $bindings = [$yearAntPeriodoStart, $yearAntPeriodoEnd];
                    break;
                case 'year_anterior':
                    $where = "AND YEAR(c.fecha_factura) = ?";
                    $bindings = [$yearPrev];
                    break;
                default:
                    return response()->json(['success' => false, 'error' => "Periodo no valido: {$periodo}"], 400);
            }

            $query = "
                SELECT
                    i.cod_factura,
                    i.cod_proveedor,
                    pr.razon_social,
                    pr.cif,
                    c.fecha_factura,
                    i.cod_impuesto,
                    i.porcentaje,
                    i.base,
                    i.importe_porcentaje AS cuota,
                    i.importe AS total
                {$baseFrom}
                {$where}
                ORDER BY c.fecha_factura DESC, i.cod_factura, i.cod_impuesto
            ";

            $rawResults = $erp->select($query, $bindings);

            $formatted = array_map(function ($r) {
                $r = (array)$r;
                return [
                    'factura' => trim($r['cod_factura'] ?? ''),
                    'cod_proveedor' => $r['cod_proveedor'],
                    'proveedor' => trim($r['razon_social'] ?? 'Proveedor Desconocido'),
                    'cif' => trim($r['cif'] ?? 'N/A'),
                    'fecha' => $r['fecha_factura'] ? \Carbon\Carbon::parse($r['fecha_factura'])->format('d/m/Y') : 'N/A',
                    'porcentaje' => (float)$r['porcentaje'],
                    'base' => (float)$r['base'],
                    'cuota' => (float)$r['cuota'],
                    'total' => (float)$r['total'],
                ];
            }, $rawResults);

            return response()->json([
                'success' => true,
                'data' => $formatted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getPeriodoTexto(string $periodo, int $year): string
    {
        $textos = [
            'hoy' => 'Último Día con Ventas',
            'ayer' => 'Penúltimo Día con Ventas',
            'quincena' => 'Quincena Actual',
            'year' => "Año $year"
        ];
        return $textos[$periodo] ?? $periodo;
    }
}
