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
                return $this->buildDashboardData($year, $anioAnteriores, $periodo);
            } catch (\Exception $e) {
                \Log::error('Store Dashboard Error: ' . $e->getMessage());
                return $this->getEmptyDashboardData($year, $anioAnteriores, $periodo, $e->getMessage());
            }
        });

        return view('store-dashboard.index', $data);
    }

    /**
     * Endpoint API para health check de conectividad local con SQL Server ERP.
     */
    public function apiHealth()
    {
        $startTime = microtime(true);
        try {
            $erp = DB::connection('erp');
            $check = $erp->select("SELECT 1 as is_alive");
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            $isAlive = !empty($check) && ((int)($check[0]->is_alive ?? 0) === 1);

            if (!$isAlive) {
                return response()->json([
                    'ok' => false,
                    'erp' => false,
                    'source' => 'local_erp',
                    'error' => 'ERP query returned invalid state',
                    'latency_ms' => $durationMs,
                    'timestamp' => now()->toISOString(),
                ], 503);
            }

            return response()->json([
                'ok' => true,
                'erp' => true,
                'source' => 'local_erp',
                'latency_ms' => $durationMs,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            \Log::warning('API Health Check Failed: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'erp' => false,
                'source' => 'local_erp',
                'error' => 'ERP database connection unavailable',
                'latency_ms' => $durationMs,
                'timestamp' => now()->toISOString(),
            ], 503);
        }
    }

    /**
     * Endpoint API JSON que devuelve el resumen completo del Cuadro de Dirección directamente desde el ERP.
     */
    public function apiSummary(Request $request)
    {
        $startTime = microtime(true);
        $periodo = $request->input('periodo', 'hoy');
        $year = (int)$request->input('year', date('Y'));
        $anioAnteriores = $request->input('anio_ant', 'todos');

        try {
            $data = $this->buildDashboardData($year, $anioAnteriores, $periodo);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'ok' => true,
                'source' => 'local_erp',
                'generated_at' => now()->toISOString(),
                'execution_time_ms' => $durationMs,
                'reference_date' => $data['ultimoDiaVentas'] ?? date('Y-m-d'),
                'ultimo_dia_ventas' => $data['ultimoDiaVentas'] ?? null,
                'penultimo_dia_ventas' => $data['penultimoDiaVentas'] ?? null,
                'periodo' => $periodo,
                'year' => $year,
                'anio_anteriores' => $anioAnteriores,
                'sales' => $data['sales_data'] ?? [],
                'sales_periods' => $data['sales_periods'] ?? [],
                'margins' => $data['margins_data'] ?? [],
                'impagados' => $data['impagados_data'] ?? [],
                'albaranes' => $data['albaranes_data'] ?? [],
                'purchases_periods' => $data['purchases_periods'] ?? [],
                'payables' => $data['payables_data'] ?? [],
                // Contratos adicionales / nativos para compatibilidad completa
                'tiendas' => $data['tiendas'] ?? [],
                'totales' => $data['totales'] ?? [],
                'sparklines' => $data['sparklines'] ?? [],
                'facturasCompras' => $data['facturasCompras'] ?? [],
                'pagosPendientes' => $data['pagosPendientes'] ?? [],
                'ticketMedio' => $data['ticketMedio'] ?? 0,
                'ticketMedioAnt' => $data['ticketMedioAnt'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            \Log::error('API Dashboard Summary Error: ' . $e->getMessage());

            return response()->json([
                'ok' => false,
                'source' => 'local_erp',
                'error' => 'Error al consultar datos del ERP local',
                'execution_time_ms' => $durationMs,
                'generated_at' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Lógica central de obtención y agregación de datos del ERP INTEGRAL.
     */
    public function buildDashboardData(int $year = 2026, string $anioAnteriores = 'todos', string $periodo = 'hoy'): array
    {
        $erp = DB::connection('erp');

        // === DETERMINAR ÚLTIMO Y PENÚLTIMO DÍA CON VENTAS (Unificado en 1 query) ===
        $ultimosDiasRows = $erp->select("
            SELECT DISTINCT TOP 2 CAST(fecha_venta AS DATE) as dia
            FROM hist_ventas_cabecera
            WHERE tipo_venta IN (2, 4, 5) AND ISNULL(anulada,'') <> 'S'
            ORDER BY dia DESC
        ");
        $ultimoDiaVentas = $ultimosDiasRows[0]->dia ?? date('Y-m-d');
        $penultimoDiaVentas = $ultimosDiasRows[1]->dia ?? date('Y-m-d', strtotime($ultimoDiaVentas . ' -1 day'));

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

        // === AGREGACIÓN DE VENTAS: 1) Períodos Recientes y Años (2025-2026) con CASE WHEN ===
        $ventasRecientesAgregadas = $erp->select("
            SELECT
                v.cod_almacen,

                -- Hoy (Último día con ventas)
                COUNT(CASE WHEN CAST(v.fecha_venta AS DATE) = ? THEN v.cod_venta END) as tickets_hoy,
                SUM(CASE WHEN CAST(v.fecha_venta AS DATE) = ? THEN v.importe_impuestos ELSE 0 END) as importe_hoy,

                -- Ayer (Penúltimo día con ventas)
                COUNT(CASE WHEN CAST(v.fecha_venta AS DATE) = ? THEN v.cod_venta END) as tickets_ayer,
                SUM(CASE WHEN CAST(v.fecha_venta AS DATE) = ? THEN v.importe_impuestos ELSE 0 END) as importe_ayer,

                -- Quincena Actual
                COUNT(CASE WHEN v.fecha_venta >= ? AND v.fecha_venta <= ? THEN v.cod_venta END) as tickets_quincena,
                SUM(CASE WHEN v.fecha_venta >= ? AND v.fecha_venta <= ? THEN v.importe_impuestos ELSE 0 END) as importe_quincena,

                -- Quincena Anterior
                COUNT(CASE WHEN v.fecha_venta >= ? AND v.fecha_venta <= ? THEN v.cod_venta END) as tickets_quincena_ant,
                SUM(CASE WHEN v.fecha_venta >= ? AND v.fecha_venta <= ? THEN v.importe_impuestos ELSE 0 END) as importe_quincena_ant,

                -- Año Actual
                COUNT(CASE WHEN YEAR(v.fecha_venta) = ? THEN v.cod_venta END) as tickets_year,
                SUM(CASE WHEN YEAR(v.fecha_venta) = ? THEN v.importe_impuestos ELSE 0 END) as importe_year,

                -- Año Anterior
                COUNT(CASE WHEN YEAR(v.fecha_venta) = ? THEN v.cod_venta END) as tickets_year_ant,
                SUM(CASE WHEN YEAR(v.fecha_venta) = ? THEN v.importe_impuestos ELSE 0 END) as importe_year_ant,

                -- Año Anterior Mismo Periodo
                COUNT(CASE WHEN v.fecha_venta >= ? AND v.fecha_venta <= ? THEN v.cod_venta END) as tickets_year_ant_periodo,
                SUM(CASE WHEN v.fecha_venta >= ? AND v.fecha_venta <= ? THEN v.importe_impuestos ELSE 0 END) as importe_year_ant_periodo
            FROM hist_ventas_cabecera v
            WHERE v.tipo_venta IN (2, 4, 5)
                AND ISNULL(v.anulada, '') <> 'S'
                AND YEAR(v.fecha_venta) IN (?, ?)
            GROUP BY v.cod_almacen
        ", [
            $ultimoDiaVentas, $ultimoDiaVentas,
            $penultimoDiaVentas, $penultimoDiaVentas,
            $qActualStart, $qActualEnd, $qActualStart, $qActualEnd,
            $qAntStart, $qAntEnd, $qAntStart, $qAntEnd,
            $year, $year,
            $yearPrev, $yearPrev,
            $yearAntPeriodoStart, $yearAntPeriodoEnd, $yearAntPeriodoStart, $yearAntPeriodoEnd,
            $year, $yearPrev
        ]);

        // === AGREGACIÓN DE VENTAS: 2) Ventas Anteriores a la quincena actual ===
        $anioBase = $anioAnteriores === 'todos' ? 0 : ($year - (int)$anioAnteriores);
        if ($anioAnteriores === 'todos') {
            $whereAnteriores = "v.fecha_venta < ?";
            $bindsAnteriores = [$qActualStart];
        } else {
            $whereAnteriores = "v.fecha_venta >= ? AND v.fecha_venta < ?";
            $bindsAnteriores = ["{$anioBase}0101 00:00:00", $qActualStart];
        }

        $ventasAnterioresRaw = $erp->select("
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

        // Mapear arrays específicos conservando exactamente el comportamiento de consultas individuales
        $ventasHoy = [];
        $ventasAyer = [];
        $ventasQuincena = [];
        $ventasQuincenaAnt = [];
        $ventasYear = [];
        $ventasYearAnt = [];
        $facturasYearAntPeriodo = [];
        $ventasAnteriores = [];

        foreach ($ventasRecientesAgregadas as $va) {
            $alm = (int)$va->cod_almacen;

            if ((int)$va->tickets_hoy > 0 || (float)$va->importe_hoy > 0) {
                $ventasHoy[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_hoy, 'importe' => (float)$va->importe_hoy];
            }
            if ((int)$va->tickets_ayer > 0 || (float)$va->importe_ayer > 0) {
                $ventasAyer[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_ayer, 'importe' => (float)$va->importe_ayer];
            }
            if ((int)$va->tickets_quincena > 0 || (float)$va->importe_quincena > 0) {
                $ventasQuincena[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_quincena, 'importe' => (float)$va->importe_quincena];
            }
            if ((int)$va->tickets_quincena_ant > 0 || (float)$va->importe_quincena_ant > 0) {
                $ventasQuincenaAnt[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_quincena_ant, 'importe' => (float)$va->importe_quincena_ant];
            }
            if ((int)$va->tickets_year > 0 || (float)$va->importe_year > 0) {
                $ventasYear[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_year, 'importe' => (float)$va->importe_year];
            }
            if ((int)$va->tickets_year_ant > 0 || (float)$va->importe_year_ant > 0) {
                $ventasYearAnt[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_year_ant, 'importe' => (float)$va->importe_year_ant];
            }
            if ((int)$va->tickets_year_ant_periodo > 0 || (float)$va->importe_year_ant_periodo > 0) {
                $facturasYearAntPeriodo[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets_year_ant_periodo, 'importe' => (float)$va->importe_year_ant_periodo];
            }
        }

        foreach ($ventasAnterioresRaw as $va) {
            $alm = (int)$va->cod_almacen;
            if ((int)$va->tickets > 0 || (float)$va->importe > 0) {
                $ventasAnteriores[] = (object)['cod_almacen' => $alm, 'tickets' => (int)$va->tickets, 'importe' => (float)$va->importe];
            }
        }

        $facturasQuincena = $ventasQuincena;
        $facturasQuincenaAnt = $ventasQuincenaAnt;
        $facturasYear = $ventasYear;
        $facturasYearAnt = $ventasYearAnt;

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

        // === MÁRGENES ===
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
            $m['venta'] = (float)$m['venta'];
            $m['coste'] = (float)$m['coste'];
            $m['margen'] = $m['venta'] - $m['coste'];
            $m['margen_porcentaje'] = $m['venta'] > 0 ? ($m['margen'] / $m['venta']) * 100 : 0;
            return $m;
        }, $margenesPeriodoRaw);

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
            $m['venta'] = (float)$m['venta'];
            $m['coste'] = (float)$m['coste'];
            $m['margen'] = $m['venta'] - $m['coste'];
            $m['margen_porcentaje'] = $m['venta'] > 0 ? ($m['margen'] / $m['venta']) * 100 : 0;
            return $m;
        }, $margenesYearRaw);

        // Si el periodo es 'hoy', reutilizar margenesPeriodo en lugar de consultar de nuevo
        if ($periodo === 'hoy') {
            $margenesHoyRaw = $margenesPeriodoRaw;
            $margenesHoy = $margenesPeriodo;
        } else {
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
                $m['venta'] = (float)$m['venta'];
                $m['coste'] = (float)$m['coste'];
                $m['margen'] = $m['venta'] - $m['coste'];
                $m['margen_porcentaje'] = $m['venta'] > 0 ? ($m['margen'] / $m['venta']) * 100 : 0;
                return $m;
            }, $margenesHoyRaw);
        }

        // === ALBARANES DE COMPRA MES ===
        // Referencia temporal propia del bloque 6: si el último día con ventas es día 1 del
        // mes siguiente, el cierre operativo/Delphi sigue siendo el mes anterior. Reutilizamos
        // aquí la misma referencia que el bloque 6 para mantener coherencia mensual.
        $albaranesCompraMesRaw = $erp->select("
            SELECT
                c.cod_almacen,
                COUNT(c.cod_compra) as count,
                SUM(c.importe) as importe
            FROM hist_compras_cabecera c
            WHERE YEAR(c.fecha_compra) = ? AND MONTH(c.fecha_compra) = MONTH(GETDATE())
                AND c.tipo_compra = 2
            GROUP BY c.cod_almacen
        ", [$year]);
        $albaranesCompraMes = array_map(function($a) {
            $arr = (array)$a;
            return [
                'cod_almacen' => (int)($arr['cod_almacen'] ?? 0),
                'count' => (int)($arr['count'] ?? $arr['albaranes'] ?? 0),
                'albaranes' => (int)($arr['count'] ?? $arr['albaranes'] ?? 0),
                'importe' => (float)($arr['importe'] ?? 0),
            ];
        }, $albaranesCompraMesRaw);

        // === FACTURAS COMPRAS Y GASTOS (Unificado en 1 sola consulta con CASE WHEN) ===
        // Referencia temporal propia del bloque 6: si el último día con ventas en ERP
        // es el día 1 del mes, significa que aún hay datos parciales del nuevo mes y
        // el cierre operativo/Delphi sigue siendo el mes anterior. En ese caso usamos
        // el penúltimo día con ventas como cierre, alineándonos con Delphi 31/08/2026.
        // Esto evita depender de la fecha del sistema ni de MAX(fecha_venta) a secas.
        $fcUltimoDia = ((int)(new \DateTime($ultimoDiaVentas))->format('d')) === 1
            ? $penultimoDiaVentas
            : $ultimoDiaVentas;
        $fcRefDate = new \DateTime($fcUltimoDia);
        if ($year !== (int)$fcRefDate->format('Y')) {
            $fcRefDate->setDate($year, (int)$fcRefDate->format('m'), (int)$fcRefDate->format('d'));
        }
        $fcRefYear = (int)$fcRefDate->format('Y');
        $fcRefMonth = (int)$fcRefDate->format('m');
        $fcRefPrevMonth = $fcRefMonth === 1 ? 12 : $fcRefMonth - 1;
        $fcYearPrev = $fcRefYear - 1;
        // Límite superior exclusivo para "Año Anterior mismo período": se usa el día del cierre
        // operativo ($fcRefDate) del año anterior. Formato ISO sin hora y CONVERT(date, ..., 23)
        // evita conversiones erróneas de nvarchar en smalldatetime con regionalización española.
        $fcYearAntPeriodoStart = (clone $fcRefDate)->setDate($fcYearPrev, 1, 1)->format('Y-m-d');
        $fcYearAntPeriodoEnd = (clone $fcRefDate)->setDate($fcYearPrev, (int)$fcRefDate->format('m'), (int)$fcRefDate->format('d'))->format('Y-m-d');

        $fcRow = $erp->select("
            SELECT
                COUNT(CASE WHEN YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = ? THEN 1 END) as mes_actual_count,
                SUM(CASE WHEN YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = ? THEN i.importe ELSE 0 END) as mes_actual_importe,

                COUNT(CASE WHEN YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = ? THEN 1 END) as mes_anterior_count,
                SUM(CASE WHEN YEAR(c.fecha_factura) = ? AND MONTH(c.fecha_factura) = ? THEN i.importe ELSE 0 END) as mes_anterior_importe,

                COUNT(CASE WHEN YEAR(c.fecha_factura) = ? THEN 1 END) as year_actual_count,
                SUM(CASE WHEN YEAR(c.fecha_factura) = ? THEN i.importe ELSE 0 END) as year_actual_importe,

                COUNT(CASE WHEN c.fecha_factura >= CONVERT(date, ?, 23) AND c.fecha_factura <= CONVERT(date, ?, 23) THEN 1 END) as year_ant_periodo_count,
                SUM(CASE WHEN c.fecha_factura >= CONVERT(date, ?, 23) AND c.fecha_factura <= CONVERT(date, ?, 23) THEN i.importe ELSE 0 END) as year_ant_periodo_importe,

                COUNT(CASE WHEN YEAR(c.fecha_factura) = ? THEN 1 END) as year_anterior_count,
                SUM(CASE WHEN YEAR(c.fecha_factura) = ? THEN i.importe ELSE 0 END) as year_anterior_importe
            FROM impuestos_facturas_compras i
            JOIN facturas_compras_cabecera c
              ON i.cod_factura = c.cod_factura AND i.cod_empresa = c.cod_empresa AND i.cod_proveedor = c.cod_proveedor
            WHERE c.cod_empresa = 1
              AND (
                  YEAR(c.fecha_factura) IN (?, ?)
                  OR (
                      c.fecha_factura >= CONVERT(date, ?, 23)
                      AND c.fecha_factura <= CONVERT(date, ?, 23)
                  )
              )
        ", [
            $fcRefYear, $fcRefMonth, $fcRefYear, $fcRefMonth,
            $fcRefYear, $fcRefPrevMonth, $fcRefYear, $fcRefPrevMonth,
            $fcRefYear, $fcRefYear,
            $fcYearAntPeriodoStart, $fcYearAntPeriodoEnd, $fcYearAntPeriodoStart, $fcYearAntPeriodoEnd,
            $fcYearPrev, $fcYearPrev,
            $fcRefYear, $fcYearPrev, $fcYearAntPeriodoStart, $fcYearAntPeriodoEnd
        ])[0] ?? null;

        $fcRowArr = (array)($fcRow ?? []);

        $facturasCompras = [
            'mes_actual' => [
                'count' => (int)($fcRowArr['mes_actual_count'] ?? 0),
                'importe' => (float)($fcRowArr['mes_actual_importe'] ?? 0),
            ],
            'mes_anterior' => [
                'count' => (int)($fcRowArr['mes_anterior_count'] ?? 0),
                'importe' => (float)($fcRowArr['mes_anterior_importe'] ?? 0),
            ],
            'year_actual' => [
                'count' => (int)($fcRowArr['year_actual_count'] ?? 0),
                'importe' => (float)($fcRowArr['year_actual_importe'] ?? 0),
            ],
            'year_anterior_periodo' => [
                'count' => (int)($fcRowArr['year_ant_periodo_count'] ?? 0),
                'importe' => (float)($fcRowArr['year_ant_periodo_importe'] ?? 0),
            ],
            'year_anterior' => [
                'count' => (int)($fcRowArr['year_anterior_count'] ?? 0),
                'importe' => (float)($fcRowArr['year_anterior_importe'] ?? 0),
            ],
        ];

        // Sanitizar facturas compras a tipos numéricos estrictos
        $facturasComprasArray = [];
        foreach ($facturasCompras as $key => $f) {
            $facturasComprasArray[$key] = [
                'count' => (int)($f['count'] ?? 0),
                'importe' => (float)($f['importe'] ?? 0),
            ];
        }

        // === PAGOS PENDIENTES POR VENCIMIENTO ===
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
        $pagosPendientes = array_map(function($p) {
            $arr = (array)$p;
            return [
                'periodo' => $arr['periodo'],
                'importe' => (float)($arr['importe'] ?? 0),
            ];
        }, $pagosPendientesRaw);

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

        $impagadosPorAlmacen = [];
        foreach ($impagadosRaw as $row) {
            $rArray = (array)$row;
            $cod = (int)($rArray['cod_almacen'] ?? 0);
            $impagadosPorAlmacen[] = [
                'cod_almacen' => $cod,
                'tickets' => (int)$rArray['tickets'],
                'importe' => (float)$rArray['importe']
            ];
            if ($cod && isset($tiendas[$cod])) {
                $tiendas[$cod]['impagados'] = [
                    'tickets' => (int)$rArray['tickets'],
                    'importe' => (float)$rArray['importe']
                ];
            }
        }

        foreach ($impagadosDevueltosRaw as $row) {
            $rArray = (array)$row;
            $cod = (int)($rArray['cod_almacen'] ?? 0);
            if ($cod && isset($tiendas[$cod])) {
                $tiendas[$cod]['impagados_devueltos'] = [
                    'tickets' => (int)$rArray['tickets'],
                    'importe' => (float)$rArray['importe']
                ];
            }
        }

        $pendientesPorAlmacen = [];
        foreach ($pendientesRaw as $row) {
            $rArray = (array)$row;
            $cod = (int)($rArray['cod_almacen'] ?? 0);
            $pendientesPorAlmacen[] = [
                'cod_almacen' => $cod,
                'tickets' => (int)$rArray['tickets'],
                'importe' => (float)$rArray['importe']
            ];
            if ($cod && isset($tiendas[$cod])) {
                $tiendas[$cod]['pendientes'] = [
                    'tickets' => (int)$rArray['tickets'],
                    'importe' => (float)$rArray['importe']
                ];
            }
        }

        // Helper para mapear ventas y facturas a arrays estructurados
        $formatStoreRows = function($datos) {
            return array_map(function($d) {
                $arr = (array)$d;
                return [
                    'cod_almacen' => (int)($arr['cod_almacen'] ?? 0),
                    'tickets' => (int)($arr['tickets'] ?? 0),
                    'importe' => (float)($arr['importe'] ?? 0),
                ];
            }, $datos);
        };

        $ventasHoyFormatted = $formatStoreRows($ventasHoy);
        $ventasAyerFormatted = $formatStoreRows($ventasAyer);
        $ventasQuincenaFormatted = $formatStoreRows($ventasQuincena);
        $ventasQuincenaAntFormatted = $formatStoreRows($ventasQuincenaAnt);
        $ventasYearFormatted = $formatStoreRows($ventasYear);
        $ventasYearAntFormatted = $formatStoreRows($ventasYearAnt);
        $ventasAnterioresFormatted = $formatStoreRows($ventasAnteriores);

        $facturasQuincenaFormatted = $formatStoreRows($facturasQuincena);
        $facturasQuincenaAntFormatted = $formatStoreRows($facturasQuincenaAnt);
        $facturasYearFormatted = $formatStoreRows($facturasYear);
        $facturasYearAntPeriodoFormatted = $formatStoreRows($facturasYearAntPeriodo);
        $facturasYearAntFormatted = $formatStoreRows($facturasYearAnt);

        $procesarVentas = function($datos, $key) use (&$tiendas) {
            foreach ($datos as $d) {
                $dArray = (array)$d;
                $cod = (int)($dArray['cod_almacen'] ?? 0);
                if (!isset($tiendas[$cod])) continue;
                $tiendas[$cod]['ventas'][$key] = [
                    'tickets' => (int)$dArray['tickets'],
                    'importe' => (float)$dArray['importe'],
                ];
            }
        };

        $procesarVentas($ventasHoyFormatted, 'hoy');
        $procesarVentas($ventasAyerFormatted, 'ayer');
        $procesarVentas($ventasQuincenaFormatted, 'quincena');
        $procesarVentas($ventasQuincenaAntFormatted, 'quincena_anterior');
        $procesarVentas($ventasYearFormatted, 'year');
        $procesarVentas($ventasYearAntFormatted, 'year_anterior');
        $procesarVentas($ventasAnterioresFormatted, 'anteriores');

        $procesarFacturas = function($datos, $key) use (&$tiendas) {
            foreach ($datos as $d) {
                $dArray = (array)$d;
                $cod = (int)($dArray['cod_almacen'] ?? 0);
                if (!isset($tiendas[$cod])) continue;
                $tiendas[$cod]['facturas'][$key] = [
                    'tickets' => (int)$dArray['tickets'],
                    'importe' => (float)$dArray['importe'],
                ];
            }
        };

        $procesarFacturas($facturasQuincenaFormatted, 'quincena');
        $procesarFacturas($facturasQuincenaAntFormatted, 'quincena_anterior');
        $procesarFacturas($facturasYearFormatted, 'year');
        $procesarFacturas($facturasYearAntPeriodoFormatted, 'year_ant_periodo');
        $procesarFacturas($facturasYearAntFormatted, 'year_anterior');

        foreach ($margenesPeriodo as $m) {
            $cod = (int)($m['cod_almacen'] ?? 0);
            if (!isset($tiendas[$cod])) continue;
            $tiendas[$cod]['margenes'] = [
                'venta' => (float)$m['venta'],
                'coste' => (float)$m['coste'],
                'margen' => (float)$m['margen'],
                'margen_porcentaje' => (float)$m['margen_porcentaje'],
            ];
        }

        foreach ($margenesHoy as $m) {
            $cod = (int)($m['cod_almacen'] ?? 0);
            if (!isset($tiendas[$cod])) continue;
            $tiendas[$cod]['margenes_hoy'] = [
                'venta' => (float)$m['venta'],
                'coste' => (float)$m['coste'],
                'margen' => (float)$m['margen'],
                'margen_porcentaje' => (float)$m['margen_porcentaje'],
            ];
        }

        foreach ($albaranesCompraMes as $a) {
            $cod = (int)($a['cod_almacen'] ?? 0);
            if (!isset($tiendas[$cod])) continue;
            $tiendas[$cod]['albaranes'] = [
                'count' => (int)$a['count'],
                'albaranes' => (int)$a['count'],
                'importe' => (float)$a['importe'],
            ];
        }

        // Totales Ventas
        $totalVentas = function($datos) {
            $tickets = array_sum(array_column($datos, 'tickets'));
            $importe = array_sum(array_column($datos, 'importe'));
            return ['tickets' => $tickets, 'importe' => $importe];
        };

        $totales = [
            'ventas_hoy' => $totalVentas($ventasHoyFormatted),
            'ventas_ayer' => $totalVentas($ventasAyerFormatted),
            'ventas_quincena' => $totalVentas($ventasQuincenaFormatted),
            'ventas_quincena_anterior' => $totalVentas($ventasQuincenaAntFormatted),
            'ventas_year' => $totalVentas($ventasYearFormatted),
            'ventas_year_anterior' => $totalVentas($ventasYearAntFormatted),
            'ventas_anteriores' => $totalVentas($ventasAnterioresFormatted),
            'facturas_quincena' => $totalVentas($facturasQuincenaFormatted),
            'facturas_quincena_anterior' => $totalVentas($facturasQuincenaAntFormatted),
            'facturas_year' => $totalVentas($facturasYearFormatted),
            'facturas_year_ant_periodo' => $totalVentas($facturasYearAntPeriodoFormatted),
            'facturas_year_anterior' => $totalVentas($facturasYearAntFormatted),
            'margen_venta' => array_sum(array_column($margenesPeriodo, 'venta')),
            'margen_coste' => array_sum(array_column($margenesPeriodo, 'coste')),
            'margen' => array_sum(array_column($margenesPeriodo, 'margen')),
            'margen_porcentaje' => $margenesPeriodo ? (array_sum(array_column($margenesPeriodo, 'margen')) / (array_sum(array_column($margenesPeriodo, 'venta')) ?: 1)) * 100 : 0,
            'margen_hoy_venta' => array_sum(array_column($margenesHoy, 'venta')),
            'margen_hoy_coste' => array_sum(array_column($margenesHoy, 'coste')),
            'margen_hoy' => array_sum(array_column($margenesHoy, 'margen')),
            'margen_hoy_porcentaje' => $margenesHoy ? (array_sum(array_column($margenesHoy, 'margen')) / (array_sum(array_column($margenesHoy, 'venta')) ?: 1)) * 100 : 0,
            'albaranes_mes' => array_sum(array_column($albaranesCompraMes, 'importe')),
            'ticket_medio'     => round($ticketMedio, 2),
            'ticket_medio_ant' => round($ticketMedioAnt, 2),
            'ticket_medio_pct' => $ticketMedioAnt > 0 ? (($ticketMedio - $ticketMedioAnt) / $ticketMedioAnt) * 100 : 0,
            'ventas_year_pct'  => 0,
            'margen_pct_ant'   => 0,
        ];

        // Ventas year pct
        $importeYearActual = $totales['ventas_year']['importe'] ?? 0;
        $importeYearAntPeriodo = $totales['facturas_year_ant_periodo']['importe'] ?? 0;
        $totales['ventas_year_pct'] = $importeYearAntPeriodo > 0 ? (($importeYearActual - $importeYearAntPeriodo) / $importeYearAntPeriodo) * 100 : 0;

        $totalPayablesImporte = array_sum(array_column($pagosPendientes, 'importe'));

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
            'totales' => $totales,
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

            // === SECCIONES ESTRUCTURADAS PARA DATA PROVIDER / SUPABASE CONTRACT ===
            'sales_data' => [
                'ultimo_dia' => $ultimoDiaVentas,
                'penultimo_dia' => $penultimoDiaVentas,
                'hoy' => $ventasHoyFormatted,
                'ayer' => $ventasAyerFormatted,
                'quincena_actual' => $ventasQuincenaFormatted,
                'quincena_anterior' => $ventasQuincenaAntFormatted,
                'anteriores' => $ventasAnterioresFormatted,
            ],
            'sales_periods' => [
                'quincena_actual' => $facturasQuincenaFormatted,
                'quincena_anterior' => $facturasQuincenaAntFormatted,
                'year' => $facturasYearFormatted,
                'year_ant_periodo' => $facturasYearAntPeriodoFormatted,
                'year_anterior' => $facturasYearAntFormatted,
            ],
            'margins_data' => [
                'periodo' => $periodo,
                'periodo_rows' => $margenesPeriodo,
                'hoy_rows' => $margenesHoy,
                'year_rows' => $margenesYear,
            ],
            'impagados_data' => [
                'impagados_por_almacen' => $impagadosPorAlmacen,
                'pendientes_por_almacen' => $pendientesPorAlmacen,
                'totales' => [
                    'impagados_importe' => (float)($totalImpagadosImporte ?? 0),
                    'impagados_count' => (int)($totalImpagadosTickets ?? 0),
                    'impagados_devueltos_importe' => (float)($totalImpagadosDevueltosImporte ?? 0),
                    'impagados_devueltos_count' => (int)($totalImpagadosDevueltosTickets ?? 0),
                    'pendientes_importe' => (float)($totalPendientesImporte ?? 0),
                    'pendientes_count' => (int)($totalPendientesTickets ?? 0),
                ],
            ],
            'albaranes_data' => $albaranesCompraMes,
            'purchases_periods' => $facturasComprasArray,
            'payables_data' => [
                'periodos' => $pagosPendientes,
                'total_importe' => (float)$totalPayablesImporte,
                'total_ops' => count($pagosPendientes),
            ],
        ];
    }

    private function getEmptyDashboardData(int $year, string $anioAnteriores, string $periodo, string $error): array
    {
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
            'error' => $error,
            'sales_data' => [],
            'sales_periods' => [],
            'margins_data' => [],
            'impagados_data' => [],
            'albaranes_data' => [],
            'purchases_periods' => [],
            'payables_data' => [],
        ];
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
