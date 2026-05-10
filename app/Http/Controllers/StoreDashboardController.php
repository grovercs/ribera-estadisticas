<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Filtros de fecha
        $periodo = $request->input('periodo', 'hoy'); // hoy, ayer, quincena, mes, year
        $year = $request->input('year', date('Y'));

        $cacheKey = "store_dashboard_{$periodo}_{$year}";

        // Optimizar tiempo de caché según el periodo
        $cacheTime = match($periodo) {
            'hoy', 'ayer' => now()->addMinutes(2),      // Datos frescos (2 min)
            'quincena', 'mes' => now()->addMinutes(15), // 15 minutos
            'year' => now()->addHours(1),               // 1 hora para años completos
            default => now()->addMinutes(5),
        };

        $data = cache()->remember($cacheKey, $cacheTime, function () use ($periodo, $year) {
            try {
                $erp = DB::connection('erp');

                // Construir filtro de fecha según periodo
                $dateFilter = $this->buildDateFilter($periodo, $year);

                // === VENTAS POR TIENDA ===
                // El cod_almacen está en cabecera, no en línea
                // Filtrar por tipo_venta 2, 4, 5 (ventas al contado / tickets)
                $ventasPorTienda = $erp->select("
                    SELECT
                        v.cod_almacen,
                        ISNULL(a.nombre, 'Almacén ' + CAST(v.cod_almacen AS VARCHAR)) as nombre_almacen,
                        SUM(l.cantidad) as cantidad,
                        SUM(l.importe_impuestos) as importe,
                        COUNT(DISTINCT v.cod_venta) as pedidos
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l
                        ON l.cod_venta = v.cod_venta
                        AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa
                        AND l.cod_caja = v.cod_caja
                    LEFT JOIN almacenes a ON v.cod_almacen = a.cod_almacen
                    WHERE {$dateFilter['where']}
                        AND v.tipo_venta IN (2, 4, 5)
                        AND l.cod_articulo IS NOT NULL
                        AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                        AND l.precio_coste IS NOT NULL
                        AND l.precio_coste > 0
                        AND l.precio_coste < 100000
                        AND l.cantidad > 0
                    GROUP BY v.cod_almacen, a.nombre
                    ORDER BY v.cod_almacen
                ", $dateFilter['params']);

                // Almacenes: 1 = PontdeSuert, 2 = Vielha

                // === MÁRGENES POR TIENDA ===
                // Filtrar por tipo_venta 2, 4, 5 (ventas al contado / tickets)
                $margenesPorTienda = $erp->select("
                    SELECT
                        v.cod_almacen,
                        ISNULL(a.nombre, 'Almacén ' + CAST(v.cod_almacen AS VARCHAR)) as nombre_almacen,
                        SUM(l.importe_impuestos) as venta,
                        SUM(l.precio_coste * l.cantidad) as coste,
                        SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) as margen,
                        CASE
                            WHEN SUM(l.importe_impuestos) > 0
                            THEN (SUM(l.importe_impuestos - (l.precio_coste * l.cantidad)) / SUM(l.importe_impuestos)) * 100
                            ELSE 0
                        END as margen_porcentaje
                    FROM hist_ventas_cabecera v
                    INNER JOIN hist_ventas_linea l
                        ON l.cod_venta = v.cod_venta
                        AND l.tipo_venta = v.tipo_venta
                        AND l.cod_empresa = v.cod_empresa
                        AND l.cod_caja = v.cod_caja
                    LEFT JOIN almacenes a ON v.cod_almacen = a.cod_almacen
                    WHERE {$dateFilter['where']}
                        AND v.tipo_venta IN (2, 4, 5)
                        AND l.cod_articulo IS NOT NULL
                        AND l.cod_articulo NOT IN ('ALMACEN', 'FERRETERIA', 'SANITARIOS', 'COCINAS', 'MARMOLES')
                        AND l.precio_coste IS NOT NULL
                        AND l.precio_coste > 0
                        AND l.precio_coste < 100000
                        AND l.cantidad > 0
                    GROUP BY v.cod_almacen, a.nombre
                    ORDER BY v.cod_almacen
                ", $dateFilter['params']);

                // === TOTAL ACUMULADO ===
                $total = [
                    'cantidad' => array_sum(array_column($ventasPorTienda, 'cantidad')),
                    'importe' => array_sum(array_column($ventasPorTienda, 'importe')),
                    'pedidos' => array_sum(array_column($ventasPorTienda, 'pedidos')),
                    'venta' => array_sum(array_column($margenesPorTienda, 'venta')),
                    'coste' => array_sum(array_column($margenesPorTienda, 'coste')),
                    'margen' => array_sum(array_column($margenesPorTienda, 'margen')),
                    'margen_porcentaje' => 0
                ];
                if ($total['venta'] > 0) {
                    $total['margen_porcentaje'] = ($total['margen'] / $total['venta']) * 100;
                }

                // === ALBARANES DE COMPRA (Mes Actual) ===
                $comprasMes = $erp->select("
                    SELECT
                        l.cod_almacen,
                        SUM(l.cantidad) as cantidad,
                        SUM(l.importe) as importe
                    FROM hist_compras_linea l
                    INNER JOIN hist_compras_cabecera c ON l.cod_compra = c.cod_compra AND l.tipo_compra = c.tipo_compra
                    WHERE YEAR(c.fecha_compra) = ? AND MONTH(c.fecha_compra) = MONTH(GETDATE())
                    GROUP BY l.cod_almacen
                ", [$year]);

                // === PAGOS PENDIENTES POR VENCIMIENTO ===
                // Usamos la tabla pagos_vencimientos_facturas que tiene las fechas de vencimiento
                $pagosPendientes = $erp->select("
                    SELECT
                        CASE
                            WHEN p.fecha < GETDATE() THEN 'Vencido'
                            WHEN p.fecha <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                            ELSE 'Mas de 2 meses'
                        END as periodo,
                        SUM(p.importe) as importe
                    FROM pagos_vencimientos_facturas p
                    WHERE p.importe > 0 AND p.fecha IS NOT NULL
                    GROUP BY
                        CASE
                            WHEN p.fecha < GETDATE() THEN 'Vencido'
                            WHEN p.fecha <= EOMONTH(GETDATE()) THEN 'Mes Actual'
                            WHEN p.fecha <= EOMONTH(DATEADD(MONTH, 1, GETDATE())) THEN 'Mes Siguiente'
                            ELSE 'Mas de 2 meses'
                        END
                ");

                return [
                    'ventasPorTienda' => $ventasPorTienda,
                    'margenesPorTienda' => $margenesPorTienda,
                    'total' => $total,
                    'comprasMes' => $comprasMes,
                    'pagosPendientes' => $pagosPendientes,
                    'periodo' => $periodo,
                    'year' => $year,
                    'fechaTexto' => $this->getPeriodoTexto($periodo, $year),
                    'ultima_actualizacion' => now(),
                ];

            } catch (\Exception $e) {
                \Log::error('Store Dashboard Error: ' . $e->getMessage());
                return [
                    'ventasPorTienda' => [],
                    'margenesPorTienda' => [],
                    'total' => ['cantidad' => 0, 'importe' => 0, 'pedidos' => 0, 'venta' => 0, 'coste' => 0, 'margen' => 0, 'margen_porcentaje' => 0],
                    'comprasMes' => [],
                    'pagosPendientes' => [],
                    'periodo' => $periodo,
                    'year' => $year,
                    'fechaTexto' => $this->getPeriodoTexto($periodo, $year),
                    'error' => $e->getMessage(),
                ];
            }
        });

        return view('store-dashboard.index', $data);
    }

    private function buildDateFilter(string $periodo, int $year): array
    {
        $params = [];
        $where = "1=1";

        switch ($periodo) {
            case 'hoy':
                $where .= " AND CAST(v.fecha_venta AS DATE) = CAST(GETDATE() AS DATE)";
                break;
            case 'ayer':
                $where .= " AND CAST(v.fecha_venta AS DATE) = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE)";
                break;
            case 'quincena':
                $where .= " AND v.fecha_venta >= DATEADD(DAY, -15, GETDATE()) AND v.fecha_venta <= GETDATE()";
                break;
            case 'mes':
                $where .= " AND YEAR(v.fecha_venta) = ? AND MONTH(v.fecha_venta) = MONTH(GETDATE())";
                $params[] = $year;
                break;
            case 'year':
            default:
                $where .= " AND YEAR(v.fecha_venta) = ?";
                $params[] = $year;
                break;
        }

        // Excluir ventas anuladas
        $where .= " AND ISNULL(v.anulada, '') <> 'S'";

        return ['where' => $where, 'params' => $params];
    }

    private function getPeriodoTexto(string $periodo, int $year): string
    {
        $textos = [
            'hoy' => 'Hoy',
            'ayer' => 'Ayer',
            'quincena' => 'Últimos 15 días',
            'mes' => 'Mes actual',
            'year' => "Año $year"
        ];
        return $textos[$periodo] ?? $periodo;
    }
}
