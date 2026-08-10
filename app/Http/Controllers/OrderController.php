<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        // Filtros por defecto: año en curso hasta hoy (01/01/año_actual → día_actual)
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        $currentDay = (int) date('d');

        $yearFrom = $request->input('year_from', $currentYear);
        $yearTo = $request->input('year_to', $currentYear);
        $monthFrom = $request->input('month_from', 1);
        $monthTo = $request->input('month_to', $currentMonth);
        $dayFrom = $request->input('day_from', 1);
        $dayTo = $request->input('day_to', $currentDay);

        $yearFrom = is_numeric($yearFrom) ? (int) $yearFrom : $currentYear;
        $yearTo = is_numeric($yearTo) ? (int) $yearTo : $currentYear;
        $monthFrom = is_numeric($monthFrom) ? (int) $monthFrom : 1;
        $monthTo = is_numeric($monthTo) ? (int) $monthTo : $currentMonth;
        $dayFrom = is_numeric($dayFrom) ? (int) $dayFrom : 1;
        $dayTo = is_numeric($dayTo) ? (int) $dayTo : $currentDay;

        // Normalizar días inválidos al último día del mes (evita 31 en febrero)
        $dayFrom = min($dayFrom, (int) date('t', mktime(0, 0, 0, $monthFrom, 1, $yearFrom)));
        $dayTo = min($dayTo, (int) date('t', mktime(0, 0, 0, $monthTo, 1, $yearTo)));

        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        // Por defecto mostramos "Facturas de Venta" (tipo_venta 2,4,5) para coincidir con Cuadro de Mando
        $tipoVentaRaw = $request->input('tipo_venta');
        $tipoVenta = (string) ($tipoVentaRaw ?? '2,4,5');
        $codAlmacen = (string) ($request->input('cod_almacen') ?? '');
        $codVendedor = (string) ($request->input('cod_vendedor') ?? '');
        $codCliente = (string) ($request->input('cod_cliente') ?? '');
        $razonSocial = (string) ($request->input('razon_social') ?? '');
        $codFormaPago = (string) ($request->input('cod_forma_pago') ?? '');
        $estado = (string) ($request->input('estado') ?? '');
        $minImporte = (string) ($request->input('min_importe') ?? '');
        $maxImporte = (string) ($request->input('max_importe') ?? '');

        $sort = $request->input('sort', 'fecha_venta');
        $direction = in_array(strtolower($request->input('direction', 'desc')), ['asc', 'desc']) ? strtolower($request->input('direction', 'desc')) : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        // Rangos de años disponibles
        $yearRange = $this->fetchAvailableYears();
        $minYear = reset($yearRange);
        $maxYear = end($yearRange);

        // Maestros para filtros (excluyendo vendedor, que se deriva del período)
        $tiposVenta = $this->fetchTiposVenta();
        $almacenes = $this->fetchAlmacenes();
        $formasPago = $this->fetchFormasPago();

        // Fechas exactas para SQL Server (formato Ymd sin separadores evita errores de conversión regional)
        $dateFrom = sprintf('%04d%02d%02d', $yearFrom, $monthFrom, $dayFrom);
        $dateTo = sprintf('%04d%02d%02d', $yearTo, $monthTo, $dayTo);

        // Build WHERE dinámico SIN filtro de vendedor para calcular el top vendedores del período
        [$whereSqlNoSeller, $paramsNoSeller] = $this->buildFilters(
            $dateFrom, $dateTo,
            $tipoVenta, $codAlmacen, '', $codCliente, $razonSocial,
            $codFormaPago, $estado, $minImporte, $maxImporte
        );

        // Top vendedores del período (para el gráfico, la tabla y el filtro)
        $summaryBySeller = $this->fetchSummaryBySeller($whereSqlNoSeller, $paramsNoSeller);
        $vendedores = array_slice($summaryBySeller, 0, 20);

        // Build WHERE dinámico FINAL con filtro de vendedor si el usuario lo ha seleccionado
        [$whereSql, $params] = $this->buildFilters(
            $dateFrom, $dateTo,
            $tipoVenta, $codAlmacen, $codVendedor, $codCliente, $razonSocial,
            $codFormaPago, $estado, $minImporte, $maxImporte
        );

        // KPIs del período filtrado
        $kpis = $this->fetchKpis($whereSql, $params);

        // Resúmenes
        $summaryByType = $this->fetchSummaryByType($whereSql, $params);
        $summaryByWarehouse = $this->fetchSummaryByWarehouse($whereSql, $params);
        $topClients = $this->fetchTopClients($whereSql, $params);
        $topProducts = $this->fetchTopProducts($whereSql, $params);

        // Listado paginado de ventas
        $allowedSort = ['fecha_venta', 'importe_impuestos', 'importe_pendiente', 'cod_venta', 'razon_social', 'cod_almacen'];
        $orderBy = in_array($sort, $allowedSort) ? $sort : 'fecha_venta';
        $orderDir = $direction;

        $total = $this->fetchCount($whereSql, $params);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $orders = $this->fetchOrders($whereSql, $params, $orderBy, $orderDir, $offset, self::PER_PAGE);

        // Si viene export=csv, devolver CSV
        if ($request->input('export') === 'csv') {
            return $this->exportCsv($whereSql, $params, $orderBy, $orderDir);
        }

        return view('sales.index', compact(
            'orders', 'total', 'page', 'totalPages', 'kpis',
            'summaryByType', 'summaryByWarehouse', 'summaryBySeller',
            'topClients', 'topProducts',
            'yearFrom', 'yearTo', 'monthFrom', 'monthTo', 'dayFrom', 'dayTo',
            'minYear', 'maxYear', 'yearRange',
            'tipoVenta', 'codAlmacen', 'codVendedor', 'codCliente', 'razonSocial',
            'codFormaPago', 'estado', 'minImporte', 'maxImporte',
            'sort', 'direction',
            'tiposVenta', 'almacenes', 'vendedores', 'formasPago'
        ));
    }

    /**
     * Devuelve las líneas de una venta vía AJAX.
     */
    public function lines(Request $request)
    {
        $codVenta = $request->input('cod_venta');
        $tipoVenta = $request->input('tipo_venta');
        $codEmpresa = $request->input('cod_empresa');
        $codCaja = $request->input('cod_caja');

        if (!$codVenta || !$tipoVenta || !$codEmpresa || !$codCaja) {
            return response()->json(['error' => 'Faltan parámetros'], 400);
        }

        try {
            $lines = DB::connection('erp')->select("
                SELECT
                    l.linea,
                    l.cod_articulo,
                    l.descripcion,
                    l.cantidad,
                    l.precio,
                    l.dto1,
                    l.dto2,
                    l.importe,
                    l.importe_impuestos
                FROM hist_ventas_linea l
                WHERE l.cod_venta = ?
                    AND l.tipo_venta = ?
                    AND l.cod_empresa = ?
                    AND l.cod_caja = ?
                ORDER BY l.linea
            ", [$codVenta, $tipoVenta, $codEmpresa, $codCaja]);

            return response()->json($lines);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function fetchAvailableYears(): array
    {
        try {
            $rows = DB::connection('erp')->select("
                SELECT DISTINCT YEAR(fecha_venta) as year
                FROM hist_ventas_cabecera
                WHERE fecha_venta IS NOT NULL
                ORDER BY year ASC
            ");
            return array_column($rows, 'year');
        } catch (\Exception $e) {
            \Log::warning('OrderController: no se pudieron obtener años del ERP: ' . $e->getMessage());
            $current = (int) date('Y');
            return range($current - 3, $current);
        }
    }

    private function fetchTiposVenta(): array
    {
        try {
            $rows = DB::connection('erp')->select("
                SELECT DISTINCT tipo_venta
                FROM hist_ventas_cabecera
                WHERE tipo_venta IS NOT NULL
                ORDER BY tipo_venta
            ");
            return array_column($rows, 'tipo_venta');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchAlmacenes(): array
    {
        try {
            $rows = DB::connection('erp')->select("
                SELECT DISTINCT cod_almacen
                FROM hist_ventas_cabecera
                WHERE cod_almacen IS NOT NULL AND cod_almacen <> ''
                ORDER BY cod_almacen
            ");
            return array_column($rows, 'cod_almacen');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchFormasPago(): array
    {
        try {
            $rows = DB::connection('erp')->select("
                SELECT DISTINCT cod_forma_liquidacion
                FROM hist_ventas_cabecera
                WHERE cod_forma_liquidacion IS NOT NULL AND cod_forma_liquidacion <> ''
                ORDER BY cod_forma_liquidacion
            ");
            return array_column($rows, 'cod_forma_liquidacion');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function buildFilters(
        string $dateFrom, string $dateTo,
        string $tipoVenta, string $codAlmacen, string $codVendedor,
        string $codCliente, string $razonSocial, string $codFormaPago,
        string $estado, string $minImporte, string $maxImporte
    ): array {
        $where = ["ISNULL(v.anulada, '') <> 'S'"];
        $params = [];

        // Rango de fechas exacto (incluye día)
        $where[] = "v.fecha_venta >= ? AND v.fecha_venta < ?";
        $params[] = $dateFrom;
        // Sumamos un día al límite superior para incluir todo el día final
        $params[] = date('Ymd', strtotime($dateTo . ' +1 day'));

        if ($tipoVenta !== '') {
            if (strpos($tipoVenta, ',') !== false) {
                // Múltiples tipos de venta separados por coma (ej. "2,4,5")
                $types = array_filter(array_map('trim', explode(',', $tipoVenta)), fn($t) => is_numeric($t));
                if (!empty($types)) {
                    $placeholders = implode(',', array_fill(0, count($types), '?'));
                    $where[] = "v.tipo_venta IN ({$placeholders})";
                    $params = array_merge($params, $types);
                }
            } else {
                $where[] = "v.tipo_venta = ?";
                $params[] = $tipoVenta;
            }
        }
        if ($codAlmacen !== '') {
            $where[] = "v.cod_almacen = ?";
            $params[] = $codAlmacen;
        }
        if ($codVendedor !== '') {
            $where[] = "v.cod_vendedor = ?";
            $params[] = $codVendedor;
        }
        if ($codCliente !== '') {
            $where[] = "v.cod_cliente = ?";
            $params[] = $codCliente;
        }
        if ($razonSocial !== '') {
            $where[] = "(v.razon_social LIKE ? OR v.nombre_comercial LIKE ?)";
            $params[] = "%{$razonSocial}%";
            $params[] = "%{$razonSocial}%";
        }
        if ($codFormaPago !== '') {
            $where[] = "v.cod_forma_liquidacion = ?";
            $params[] = $codFormaPago;
        }
        if ($estado === 'pendiente') {
            $where[] = "v.importe_pendiente > 0";
        } elseif ($estado === 'cobrada') {
            $where[] = "v.importe_pendiente = 0 AND v.importe_cobrado > 0";
        } elseif ($estado === 'anulada') {
            $where = array_filter($where, fn($w) => $w !== "ISNULL(v.anulada, '') <> 'S'");
            $where[] = "ISNULL(v.anulada, '') = 'S'";
        }
        if ($minImporte !== '' && is_numeric($minImporte)) {
            $where[] = "v.importe_impuestos >= ?";
            $params[] = $minImporte;
        }
        if ($maxImporte !== '' && is_numeric($maxImporte)) {
            $where[] = "v.importe_impuestos <= ?";
            $params[] = $maxImporte;
        }

        return [implode(' AND ', $where), $params];
    }

    private function fetchKpis(string $whereSql, array $params): array
    {
        $row = DB::connection('erp')->select("
            SELECT
                COUNT(*) as total_orders,
                SUM(v.importe_impuestos) as total_sales,
                AVG(v.importe_impuestos) as avg_ticket,
                SUM(v.importe_pendiente) as total_pending,
                SUM(v.importe_cobrado) as total_collected,
                COUNT(DISTINCT v.cod_cliente) as unique_clients
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
        ", $params)[0] ?? null;

        $totalSales = (float) ($row->total_sales ?? 0);
        $totalPending = (float) ($row->total_pending ?? 0);
        $totalCollected = (float) ($row->total_collected ?? 0);

        // Artículos vendidos
        $articlesRow = DB::connection('erp')->select("
            SELECT SUM(l.cantidad) as total_qty
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            WHERE {$whereSql}
        ", $params)[0] ?? null;

        return [
            'total_orders' => (int) ($row->total_orders ?? 0),
            'total_sales' => $totalSales,
            'avg_ticket' => (float) ($row->avg_ticket ?? 0),
            'total_pending' => $totalPending,
            'total_collected' => $totalCollected,
            'collected_pct' => $totalSales > 0 ? ($totalCollected / $totalSales) * 100 : 0,
            'unique_clients' => (int) ($row->unique_clients ?? 0),
            'total_qty' => (float) ($articlesRow->total_qty ?? 0),
        ];
    }

    private function fetchSummaryByType(string $whereSql, array $params): array
    {
        return DB::connection('erp')->select("
            SELECT
                v.tipo_venta,
                COUNT(*) as orders,
                SUM(v.importe_impuestos) as total
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
            GROUP BY v.tipo_venta
            ORDER BY total DESC
        ", $params);
    }

    private function fetchSummaryByWarehouse(string $whereSql, array $params): array
    {
        return DB::connection('erp')->select("
            SELECT
                v.cod_almacen,
                COUNT(*) as orders,
                SUM(v.importe_impuestos) as total
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
            GROUP BY v.cod_almacen
            ORDER BY total DESC
        ", $params);
    }

    private function fetchSummaryBySeller(string $whereSql, array $params): array
    {
        return DB::connection('erp')->select("
            SELECT
                v.cod_vendedor,
                MAX(v.nombre_vendedor) as nombre_vendedor,
                COUNT(*) as orders,
                SUM(v.importe_impuestos) as total
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
            GROUP BY v.cod_vendedor
            ORDER BY total DESC
        ", $params);
    }

    private function fetchTopClients(string $whereSql, array $params): array
    {
        return DB::connection('erp')->select("
            SELECT TOP 5
                v.cod_cliente,
                MAX(v.razon_social) as razon_social,
                COUNT(*) as orders,
                SUM(v.importe_impuestos) as total
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
            GROUP BY v.cod_cliente
            ORDER BY total DESC
        ", $params);
    }

    private function fetchTopProducts(string $whereSql, array $params): array
    {
        return DB::connection('erp')->select("
            SELECT TOP 5
                l.cod_articulo,
                MAX(l.descripcion) as descripcion,
                SUM(l.cantidad) as qty,
                SUM(l.importe_impuestos) as total
            FROM hist_ventas_linea l
            INNER JOIN hist_ventas_cabecera v
                ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta
                AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja
            WHERE {$whereSql}
            GROUP BY l.cod_articulo
            ORDER BY total DESC
        ", $params);
    }

    private function fetchCount(string $whereSql, array $params): int
    {
        $row = DB::connection('erp')->select("
            SELECT COUNT(*) as total
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
        ", $params)[0] ?? null;
        return (int) ($row->total ?? 0);
    }

    private function fetchOrders(string $whereSql, array $params, string $orderBy, string $direction, int $offset, int $limit): array
    {
        $orderSql = match ($orderBy) {
            'fecha_venta' => "v.fecha_venta {$direction}",
            'importe_impuestos' => "v.importe_impuestos {$direction}",
            'importe_pendiente' => "v.importe_pendiente {$direction}",
            'cod_venta' => "v.cod_venta {$direction}",
            'razon_social' => "v.razon_social {$direction}",
            'cod_almacen' => "v.cod_almacen {$direction}",
            default => "v.fecha_venta DESC",
        };

        return DB::connection('erp')->select("
            SELECT
                v.cod_venta,
                v.tipo_venta,
                v.cod_empresa,
                v.cod_caja,
                v.cod_almacen,
                v.cod_cliente,
                v.razon_social,
                v.nombre_comercial,
                v.fecha_venta,
                v.cod_forma_liquidacion,
                v.cod_vendedor,
                v.nombre_vendedor,
                v.importe_impuestos,
                v.importe_pendiente,
                v.importe_cobrado,
                v.anulada
            FROM hist_ventas_cabecera v
            WHERE {$whereSql}
            ORDER BY {$orderSql}
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ", array_merge($params, [$offset, $limit]));
    }

    private function exportCsv(string $whereSql, array $params, string $orderBy, string $direction): StreamedResponse
    {
        $orderSql = match ($orderBy) {
            'fecha_venta' => "v.fecha_venta {$direction}",
            'importe_impuestos' => "v.importe_impuestos {$direction}",
            'importe_pendiente' => "v.importe_pendiente {$direction}",
            'cod_venta' => "v.cod_venta {$direction}",
            'razon_social' => "v.razon_social {$direction}",
            'cod_almacen' => "v.cod_almacen {$direction}",
            default => "v.fecha_venta DESC",
        };

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ventas.csv"',
        ];

        return response()->stream(function () use ($whereSql, $params, $orderSql) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($output, ['Código', 'Tipo', 'Fecha', 'Almacén', 'Cliente', 'Razón social', 'Vendedor', 'Forma pago', 'Total', 'Pendiente', 'Cobrado', 'Estado'], ';');

            $rows = DB::connection('erp')->select("
                SELECT
                    v.cod_venta,
                    v.tipo_venta,
                    v.fecha_venta,
                    v.cod_almacen,
                    v.cod_cliente,
                    v.razon_social,
                    v.nombre_vendedor,
                    v.cod_forma_liquidacion,
                    v.importe_impuestos,
                    v.importe_pendiente,
                    v.importe_cobrado,
                    v.anulada
                FROM hist_ventas_cabecera v
                WHERE {$whereSql}
                ORDER BY {$orderSql}
            ", $params);

            foreach ($rows as $r) {
                $estado = (is_null($r->anulada) || $r->anulada === '' || $r->anulada === 'N')
                    ? ($r->importe_pendiente > 0 ? 'Pendiente' : 'Cobrada')
                    : 'Anulada';
                fputcsv($output, [
                    $r->cod_venta,
                    $r->tipo_venta,
                    $r->fecha_venta ? date('d/m/Y H:i', strtotime($r->fecha_venta)) : '',
                    $r->cod_almacen,
                    $r->cod_cliente,
                    $r->razon_social,
                    $r->nombre_vendedor,
                    $r->cod_forma_liquidacion,
                    number_format($r->importe_impuestos, 2, ',', '.'),
                    number_format($r->importe_pendiente, 2, ',', '.'),
                    number_format($r->importe_cobrado, 2, ',', '.'),
                    $estado,
                ], ';');
            }

            fclose($output);
        }, 200, $headers);
    }
}
