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
        $year = $request->input('year', date('Y'));
        $anioAnteriores = $request->input('anio_ant', '5'); // Años atrás para "Anteriores": 1, 2, 3, 5, 10, todos

        $cacheKey = "store_dashboard_v4_{$periodo}_{$year}_ant{$anioAnteriores}";

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

                // === VENTAS HOY ===
                $ventasHoy = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE CAST(v.fecha_venta AS DATE) = CAST(GETDATE() AS DATE)
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ");

                // === VENTAS AYER ===
                $ventasAyer = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE CAST(v.fecha_venta AS DATE) = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE)
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ");

                // === VENTAS QUINCENA ACTUAL ===
                // Primera quincena del mes actual (días 1-15)
                $ventasQuincena = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) = MONTH(GETDATE()) AND DAY(v.fecha_venta) <= 15
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                // === VENTAS QUINCENA ANTERIOR ===
                // Segunda quincena del mes anterior (días 16-30/31)
                $ventasQuincenaAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) = MONTH(DATEADD(MONTH, -1, GETDATE())) AND DAY(v.fecha_venta) >= 16
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                // === VENTAS AÑO ACTUAL ===
                $ventasYear = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                // === VENTAS AÑO ANTERIOR ===
                $ventasYearAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year - 1]);

                // === VENTAS ANTERIORES (acumulado histórico - configurable por años) ===
                $anioBase = $anioAnteriores === 'todos' ? 0 : ($year - (int)$anioAnteriores);
                $whereAnteriores = $anioAnteriores === 'todos'
                    ? "YEAR(v.fecha_venta) < ?"
                    : "YEAR(v.fecha_venta) >= ? AND YEAR(v.fecha_venta) < ?";
                $bindsAnteriores = $anioAnteriores === 'todos'
                    ? [$year]
                    : [$anioBase, $year];

                $ventasAnteriores = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE $whereAnteriores
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", $bindsAnteriores);

                // === FACTURAS DE VENTA (usando tipo_venta IN (2,4,5) como el dashboard .NET) ===
                $facturasQuincena = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) = MONTH(GETDATE()) AND DAY(v.fecha_venta) <= 15
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                $facturasQuincenaAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) = MONTH(DATEADD(MONTH, -1, GETDATE())) AND DAY(v.fecha_venta) >= 16
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                $facturasYear = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                $facturasYearAntPeriodo = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) <= MONTH(GETDATE())
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);

                $facturasYearAnt = $erp->select("
                    SELECT
                        v.cod_almacen,
                        COUNT(DISTINCT v.cod_venta) as tickets,
                        SUM(l.importe_impuestos) as importe
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ?
                        AND v.tipo_venta IN (2, 4, 5)
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year - 1]);

                // === IMPAGADOS ===
                // Usamos hist_ventas_cabecera con importe_pendiente > 0 del año actual
                // El dashboard .NET muestra 63 impagados (74.198,04 €), 668 pendientes (250.996,72 €)
                $impagadosRaw = $erp->select("
                    SELECT
                        COUNT(DISTINCT cod_venta) as impagados_count,
                        SUM(importe_pendiente) as impagados_importe
                    FROM hist_ventas_cabecera
                    WHERE importe_pendiente > 0
                        AND ISNULL(anulada, '') <> 'S'
                        AND YEAR(fecha_venta) = ?
                        AND MONTH(fecha_venta) <= MONTH(GETDATE())
                ", [$year]);
                $impagados = !empty($impagadosRaw) ? (array)$impagadosRaw[0] : ['impagados_count' => 0, 'impagados_importe' => 0];

                // Pendientes de cobro (ventas con pendiente del año anterior)
                $pendientesRaw = $erp->select("
                    SELECT
                        COUNT(DISTINCT cod_venta) as pendientes_count,
                        SUM(importe_pendiente) as pendientes_importe
                    FROM hist_ventas_cabecera
                    WHERE importe_pendiente > 0
                        AND ISNULL(anulada, '') <> 'S'
                        AND YEAR(fecha_venta) = ?
                ", [$year - 1]);
                $pendientes = !empty($pendientesRaw) ? (array)$pendientesRaw[0] : ['pendientes_count' => 0, 'pendientes_importe' => 0];

                // === MÁRGENES AÑO ACTUAL ===
                // Usamos TODOS los tipo_venta para márgenes (como el dashboard .NET)
                $margenesYearRaw = $erp->select("
                    SELECT
                        v.cod_almacen,
                        SUM(l.importe_impuestos) as venta,
                        SUM(l.precio_coste * l.cantidad) as coste,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as margen,
                        CASE WHEN SUM(l.importe_impuestos) > 0
                            THEN (SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) / SUM(l.importe_impuestos)) * 100
                            ELSE 0
                        END as margen_porcentaje
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
                    WHERE YEAR(v.fecha_venta) = ?
                        AND l.cod_articulo IS NOT NULL
                        AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                        AND l.precio_coste IS NOT NULL AND l.precio_coste > 0 AND l.precio_coste < 100000
                        AND l.cantidad > 0
                        AND ISNULL(v.anulada, '') <> 'S'
                    GROUP BY v.cod_almacen
                ", [$year]);
                $margenesYear = array_map(function($m) { return (array)$m; }, $margenesYearRaw);

                // === ALBARANES DE COMPRA MES ===
                // Filtramos por tipo_compra 2 (albaranes, no facturas)
                $albaranesCompraMesRaw = $erp->select("
                    SELECT
                        l.cod_almacen,
                        COUNT(DISTINCT c.cod_compra) as albaranes,
                        SUM(l.importe) as importe
                    FROM hist_compras_linea l
                    INNER JOIN hist_compras_cabecera c ON l.cod_compra = c.cod_compra AND l.tipo_compra = c.tipo_compra
                    WHERE YEAR(c.fecha_compra) = ? AND MONTH(c.fecha_compra) = MONTH(GETDATE())
                        AND c.tipo_compra = 2
                    GROUP BY l.cod_almacen
                ", [$year]);
                $albaranesCompraMes = array_map(function($a) { return (array)$a; }, $albaranesCompraMesRaw);

                // === FACTURAS COMPRAS Y GASTOS ===
                // Filtramos por tipo_compra = 1 (facturas, no albaranes)
                $facturasCompras = [
                    'mes_actual' => $erp->select("
                        SELECT COUNT(*) as count, SUM(importe) as importe
                        FROM hist_compras_cabecera
                        WHERE YEAR(fecha_compra) = ? AND MONTH(fecha_compra) = MONTH(GETDATE())
                            AND tipo_compra = 1
                    ", [$year])[0],
                    'mes_anterior' => $erp->select("
                        SELECT COUNT(*) as count, SUM(importe) as importe
                        FROM hist_compras_cabecera
                        WHERE YEAR(fecha_compra) = ? AND MONTH(fecha_compra) = MONTH(DATEADD(MONTH, -1, GETDATE()))
                            AND tipo_compra = 1
                    ", [$year])[0],
                    'year_actual' => $erp->select("
                        SELECT COUNT(*) as count, SUM(importe) as importe
                        FROM hist_compras_cabecera
                        WHERE YEAR(fecha_compra) = ?
                            AND tipo_compra = 1
                    ", [$year])[0],
                    'year_anterior_periodo' => $erp->select("
                        SELECT COUNT(*) as count, SUM(importe) as importe
                        FROM hist_compras_cabecera
                        WHERE YEAR(fecha_compra) = ? AND MONTH(fecha_compra) <= MONTH(GETDATE())
                            AND tipo_compra = 1
                    ", [$year])[0],
                    'year_anterior' => $erp->select("
                        SELECT COUNT(*) as count, SUM(importe) as importe
                        FROM hist_compras_cabecera
                        WHERE YEAR(fecha_compra) = ?
                            AND tipo_compra = 1
                    ", [$year - 1])[0],
                ];

                // === PAGOS PENDIENTES POR VENCIMIENTO ===
                $pagosPendientesRaw = $erp->select("
                    SELECT
                        CASE
                            WHEN p.fecha <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                            ELSE 'Mas de 3 meses'
                        END as periodo,
                        SUM(p.importe) as importe
                    FROM pagos_vencimientos_facturas p
                    WHERE p.importe > 0 AND p.fecha IS NOT NULL AND p.fecha >= GETDATE()
                    GROUP BY
                        CASE
                            WHEN p.fecha <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 2, GETDATE())) THEN 'En 2 meses'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 3, GETDATE())) THEN 'En 3 meses'
                            ELSE 'Mas de 3 meses'
                        END
                ");
                // Convertir stdClass a array para evitar problemas de serialización
                $pagosPendientes = array_map(function($p) { return (array)$p; }, $pagosPendientesRaw);

                // === PROCESAR DATOS POR TIENDA ===
                $tiendas = [
                    1 => ['nombre' => 'Pont de Suert', 'ventas' => [], 'facturas' => [], 'margenes' => null, 'albaranes' => 0],
                    2 => ['nombre' => 'Vielha', 'ventas' => [], 'facturas' => [], 'margenes' => null, 'albaranes' => 0],
                ];

                // Helper para procesar datos
                $procesarVentas = function($datos, $key) use (&$tiendas) {
                    foreach ($datos as $d) {
                        $dArray = (array)$d;
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

                foreach ($margenesYear as $m) {
                    $tiendas[$m['cod_almacen']]['margenes'] = [
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
                        'impagados_importe' => (float)($impagados['impagados_importe'] ?? 0),
                        'impagados_count' => (int)($impagados['impagados_count'] ?? 0),
                        'pendientes_importe' => (float)($pendientes['pendientes_importe'] ?? 0),
                        'pendientes_count' => (int)($pendientes['pendientes_count'] ?? 0),
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
                        'margen_venta' => array_sum(array_column($margenesYear, 'venta')),
                        'margen_coste' => array_sum(array_column($margenesYear, 'coste')),
                        'margen' => array_sum(array_column($margenesYear, 'margen')),
                        'margen_porcentaje' => $margenesYear ? (array_sum(array_column($margenesYear, 'margen')) / array_sum(array_column($margenesYear, 'venta'))) * 100 : 0,
                        'albaranes_mes' => array_sum(array_column($albaranesCompraMes, 'importe')),
                    ],
                    'periodo' => $periodo,
                    'year' => $year,
                    'anioAnteriores' => $anioAnteriores,
                    'fechaTexto' => $this->getPeriodoTexto($periodo, $year),
                    'ultima_actualizacion' => now()->format('Y-m-d H:i:s'),
                ];

            } catch (\Exception $e) {
                \Log::error('Store Dashboard Error: ' . $e->getMessage());
                return [
                    'tiendas' => [],
                    'impagados' => ['impagados_importe' => 0, 'impagados_count' => 0, 'pendientes_importe' => 0, 'pendientes_count' => 0],
                    'facturasCompras' => [],
                    'pagosPendientes' => [],
                    'totales' => ['ventas_hoy' => ['tickets' => 0, 'importe' => 0], 'ventas_quincena' => ['tickets' => 0, 'importe' => 0], 'ventas_year' => ['tickets' => 0, 'importe' => 0]],
                    'periodo' => $periodo,
                    'year' => $year,
                    'anioAnteriores' => $anioAnteriores,
                    'fechaTexto' => $this->getPeriodoTexto($periodo, $year),
                    'error' => $e->getMessage(),
                ];
            }
        });

        return view('store-dashboard.index', $data);
    }

    private function getPeriodoTexto(string $periodo, int $year): string
    {
        $textos = [
            'hoy' => 'Hoy',
            'ayer' => 'Ayer',
            'quincena' => 'Quincena Actual',
            'year' => "Año $year"
        ];
        return $textos[$periodo] ?? $periodo;
    }
}
