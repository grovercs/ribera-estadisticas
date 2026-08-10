<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    private const PER_PAGE = 25;
    private const DEFAULT_MONTHS = 12;

    public function index(Request $request)
    {
        // Filtros
        $search = trim($request->input('search', ''));
        $codFamilia = $request->input('cod_familia', '');
        $codSubfamilia = $request->input('cod_subfamilia', '');
        $codAlmacen = $request->input('cod_almacen', '');
        $stockFilter = $request->input('stock_filter', ''); // '', 'con_stock', 'sin_stock', 'bajo_minimo'
        $salesMonths = max(1, min(36, (int) $request->input('sales_months', self::DEFAULT_MONTHS)));
        $order = $request->input('order', 'revenue');
        $direction = in_array(strtolower($request->input('direction', 'desc')), ['asc', 'desc']) ? strtolower($request->input('direction', 'desc')) : 'desc';
        $page = max(1, (int) $request->input('page', 1));

        // Maestros para filtros
        $families = $this->fetchFamilies();
        $almacenes = $this->fetchWarehouses();

        // Si no hay filtros de búsqueda, forzamos un filtro por defecto: artículos con ventas en el período
        $requireSales = $search === '' && $codFamilia === '' && $codSubfamilia === '' && $codAlmacen === '' && $stockFilter === '';

        // Build WHERE dinámico (sobre articulos a)
        [$whereSql, $params] = $this->buildFilters(
            $search, $codFamilia, $codSubfamilia, $codAlmacen, $stockFilter, $salesMonths, $requireSales
        );

        // KPIs
        $kpis = $this->fetchKpis($whereSql, $params, $salesMonths, $codAlmacen);

        // Resúmenes
        $summaryByFamily = $this->fetchSummaryByFamily($whereSql, $params, $salesMonths, $codAlmacen);
        $summaryByWarehouse = $this->fetchSummaryByWarehouse($whereSql, $params, $codAlmacen);

        // Listado paginado
        $allowedSort = ['revenue', 'qty', 'stock', 'precio_coste', 'precio_venta_publico', 'marca', 'cod_articulo'];
        $sortBy = in_array($order, $allowedSort) ? $order : 'revenue';
        $orderSql = $this->buildOrderSql($sortBy, $direction);

        $total = $this->fetchCount($whereSql, $params, $salesMonths, $codAlmacen);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * self::PER_PAGE;

        $products = $total > 0 ? $this->fetchProducts($whereSql, $params, $orderSql, $offset, self::PER_PAGE, $salesMonths, $codAlmacen) : [];

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($whereSql, $params, $orderSql, $salesMonths, $codAlmacen);
        }

        return view('stock.index', compact(
            'products', 'total', 'page', 'totalPages', 'kpis',
            'summaryByFamily', 'summaryByWarehouse',
            'search', 'codFamilia', 'codSubfamilia', 'codAlmacen', 'stockFilter', 'salesMonths',
            'order', 'direction',
            'families', 'almacenes'
        ));
    }

    /**
     * Devuelve subfamilias de una familia vía AJAX.
     */
    public function subfamilies(Request $request)
    {
        $codFamilia = $request->input('cod_familia');
        if (!$codFamilia) {
            return response()->json([]);
        }

        try {
            $rows = DB::connection('erp')->select("
                SELECT cod_subfamilia, descripcion
                FROM subfamilias
                WHERE cod_familia = ?
                ORDER BY descripcion
            ", [$codFamilia]);
            return response()->json($rows);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function fetchFamilies(): array
    {
        try {
            return DB::connection('erp')->select("
                SELECT cod_familia, descripcion
                FROM familias
                ORDER BY descripcion
            ");
        } catch (\Exception $e) {
            \Log::warning('ProductController: no se pudieron obtener familias: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchWarehouses(): array
    {
        try {
            $rows = DB::connection('erp')->select("
                SELECT DISTINCT cod_almacen
                FROM stocks
                WHERE cod_almacen IS NOT NULL AND cod_almacen <> ''
                ORDER BY cod_almacen
            ");
            return array_column($rows, 'cod_almacen');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function buildFilters(
        string $search, string $codFamilia, string $codSubfamilia,
        string $codAlmacen, string $stockFilter, int $salesMonths, bool $requireSales
    ): array {
        $where = ["ISNULL(a.fecha_baja, '') = ''"];
        $params = [];

        if ($search !== '') {
            $where[] = "(a.cod_articulo LIKE ? OR a.marca LIKE ? OR a.descripcion_web LIKE ? OR a.cod_barras LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($codFamilia !== '') {
            $where[] = "a.cod_familia = ?";
            $params[] = $codFamilia;
        }

        if ($codSubfamilia !== '') {
            $where[] = "a.cod_subfamilia = ?";
            $params[] = $codSubfamilia;
        }

        // Almacén y stock se aplican en el join, no aquí

        if ($requireSales) {
            $where[] = "EXISTS (SELECT 1 FROM hist_ventas_linea l INNER JOIN hist_ventas_cabecera v ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja WHERE l.cod_articulo = a.cod_articulo AND v.fecha_venta >= DATEADD(MONTH, -?, GETDATE()) AND ISNULL(v.anulada, '') <> 'S')";
            $params[] = $salesMonths;
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildOrderSql(string $sortBy, string $direction): string
    {
        $dir = strtoupper($direction);
        return match ($sortBy) {
            'revenue' => "total_revenue {$dir}",
            'qty' => "total_qty {$dir}",
            'stock' => "stock_total {$dir}",
            'precio_coste' => "a.precio_coste {$dir}",
            'precio_venta_publico' => "a.precio_venta_publico {$dir}",
            'marca' => "a.marca {$dir}",
            'cod_articulo' => "a.cod_articulo {$dir}",
            default => "total_revenue {$dir}",
        };
    }

    private function stockCteParams(string $codAlmacen): array
    {
        return $codAlmacen !== '' ? [$codAlmacen] : [];
    }

    private function stockCteSql(string $codAlmacen): string
    {
        if ($codAlmacen !== '') {
            return "SELECT cod_articulo, SUM(existencias) as existencias, MAX(minimos) as minimos_sum FROM stocks WHERE cod_almacen = ? GROUP BY cod_articulo";
        }
        return "SELECT cod_articulo, SUM(existencias) as existencias, SUM(minimos) as minimos_sum FROM stocks GROUP BY cod_articulo";
    }

    private function salesCteSql(int $salesMonths): string
    {
        return "SELECT l.cod_articulo, SUM(l.cantidad) as total_qty, SUM(l.importe_impuestos) as total_revenue FROM hist_ventas_linea l INNER JOIN hist_ventas_cabecera v ON l.cod_venta = v.cod_venta AND l.tipo_venta = v.tipo_venta AND l.cod_empresa = v.cod_empresa AND l.cod_caja = v.cod_caja WHERE v.fecha_venta >= DATEADD(MONTH, -?, GETDATE()) AND ISNULL(v.anulada, '') <> 'S' GROUP BY l.cod_articulo";
    }

    private function baseFrom(string $whereSql, int $salesMonths, string $codAlmacen): array
    {
        $stockParams = $this->stockCteParams($codAlmacen);
        $salesParams = [$salesMonths];

        $sql = "
            FROM articulos a
            LEFT JOIN familias f ON a.cod_familia = f.cod_familia
            LEFT JOIN subfamilias sf ON a.cod_familia = sf.cod_familia AND a.cod_subfamilia = sf.cod_subfamilia
            LEFT JOIN ({$this->stockCteSql($codAlmacen)}) s ON s.cod_articulo = a.cod_articulo
            LEFT JOIN ({$this->salesCteSql($salesMonths)}) vsales ON vsales.cod_articulo = a.cod_articulo
            WHERE {$whereSql}
        ";

        $params = array_merge($stockParams, $salesParams);
        return [$sql, $params];
    }

    private function fetchCount(string $whereSql, array $params, int $salesMonths, string $codAlmacen): int
    {
        [$fromSql, $fromParams] = $this->baseFrom($whereSql, $salesMonths, $codAlmacen);
        $bound = array_merge($fromParams, $params);

        $row = DB::connection('erp')->select("SELECT COUNT(*) as total {$fromSql}", $bound)[0] ?? null;
        return (int) ($row->total ?? 0);
    }

    private function fetchProducts(string $whereSql, array $params, string $orderSql, int $offset, int $limit, int $salesMonths, string $codAlmacen): array
    {
        [$fromSql, $fromParams] = $this->baseFrom($whereSql, $salesMonths, $codAlmacen);
        $bound = array_merge($fromParams, $params, [$offset, $limit]);

        return DB::connection('erp')->select("
            SELECT
                a.cod_articulo,
                a.marca,
                a.descripcion_web,
                f.descripcion as familia,
                sf.descripcion as subfamilia,
                a.precio_coste,
                a.precio_venta_publico,
                ISNULL(s.existencias, 0) as stock_total,
                ISNULL(s.existencias * a.precio_coste, 0) as stock_valued,
                ISNULL(vsales.total_qty, 0) as total_qty,
                ISNULL(vsales.total_revenue, 0) as total_revenue
            {$fromSql}
            ORDER BY {$orderSql}
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ", $bound);
    }

    private function fetchKpis(string $whereSql, array $params, int $salesMonths, string $codAlmacen): array
    {
        [$fromSql, $fromParams] = $this->baseFrom($whereSql, $salesMonths, $codAlmacen);
        $bound = array_merge($fromParams, $params);

        $row = DB::connection('erp')->select("
            SELECT
                COUNT(*) as total_products,
                SUM(ISNULL(s.existencias, 0)) as total_stock,
                SUM(ISNULL(s.existencias * a.precio_coste, 0)) as stock_valued,
                SUM(CASE WHEN ISNULL(s.existencias, 0) <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN ISNULL(s.existencias, 0) <= ISNULL(s.minimos_sum, 0) AND ISNULL(s.minimos_sum, 0) > 0 THEN 1 ELSE 0 END) as below_minimum,
                SUM(ISNULL(vsales.total_qty, 0)) as total_qty,
                SUM(ISNULL(vsales.total_revenue, 0)) as total_revenue
            {$fromSql}
        ", $bound)[0] ?? null;

        return [
            'total_products' => (int) ($row->total_products ?? 0),
            'total_stock' => (float) ($row->total_stock ?? 0),
            'stock_valued' => (float) ($row->stock_valued ?? 0),
            'out_of_stock' => (int) ($row->out_of_stock ?? 0),
            'below_minimum' => (int) ($row->below_minimum ?? 0),
            'total_qty' => (float) ($row->total_qty ?? 0),
            'total_revenue' => (float) ($row->total_revenue ?? 0),
        ];
    }

    private function fetchSummaryByFamily(string $whereSql, array $params, int $salesMonths, string $codAlmacen): array
    {
        [$fromSql, $fromParams] = $this->baseFrom($whereSql, $salesMonths, $codAlmacen);
        $bound = array_merge($fromParams, $params);

        return DB::connection('erp')->select("
            SELECT TOP 10
                a.cod_familia,
                MAX(f.descripcion) as familia,
                COUNT(*) as products,
                SUM(ISNULL(s.existencias, 0)) as stock,
                SUM(ISNULL(vsales.total_revenue, 0)) as revenue
            {$fromSql}
            GROUP BY a.cod_familia
            ORDER BY revenue DESC
        ", $bound);
    }

    private function fetchSummaryByWarehouse(string $whereSql, array $params, string $codAlmacen): array
    {
        if ($codAlmacen !== '') {
            // Solo un almacén seleccionado
            return DB::connection('erp')->select("
                SELECT ? as cod_almacen, COUNT(DISTINCT s.cod_articulo) as products, SUM(s.existencias) as stock, SUM(s.existencias * a.precio_coste) as valued
                FROM stocks s
                INNER JOIN articulos a ON s.cod_articulo = a.cod_articulo
                WHERE s.cod_almacen = ? AND {$whereSql}
            ", array_merge([$codAlmacen, $codAlmacen], $params));
        }

        return DB::connection('erp')->select("
            SELECT
                s.cod_almacen,
                COUNT(DISTINCT s.cod_articulo) as products,
                SUM(s.existencias) as stock,
                SUM(s.existencias * a.precio_coste) as valued
            FROM stocks s
            INNER JOIN articulos a ON s.cod_articulo = a.cod_articulo
            WHERE {$whereSql}
            GROUP BY s.cod_almacen
            ORDER BY valued DESC
        ", $params);
    }

    private function exportCsv(string $whereSql, array $params, string $orderSql, int $salesMonths, string $codAlmacen): StreamedResponse
    {
        [$fromSql, $fromParams] = $this->baseFrom($whereSql, $salesMonths, $codAlmacen);
        $bound = array_merge($fromParams, $params);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="stock.csv"',
        ];

        return response()->stream(function () use ($fromSql, $fromParams, $params, $orderSql, $salesMonths, $codAlmacen) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Código', 'Marca', 'Descripción', 'Familia', 'Subfamilia', 'Stock', 'Uds vendidas', 'Facturación', 'P. coste', 'PVP'], ';');

            $bound = array_merge($fromParams, $params);
            $rows = DB::connection('erp')->select("
                SELECT
                    a.cod_articulo,
                    a.marca,
                    a.descripcion_web,
                    f.descripcion as familia,
                    sf.descripcion as subfamilia,
                    a.precio_coste,
                    a.precio_venta_publico,
                    ISNULL(s.existencias, 0) as stock_total,
                    ISNULL(vsales.total_qty, 0) as total_qty,
                    ISNULL(vsales.total_revenue, 0) as total_revenue
                {$fromSql}
                ORDER BY {$orderSql}
            ", $bound);

            foreach ($rows as $r) {
                fputcsv($output, [
                    $r->cod_articulo,
                    $r->marca,
                    $r->descripcion_web,
                    $r->familia,
                    $r->subfamilia,
                    number_format($r->stock_total, 2, ',', '.'),
                    number_format($r->total_qty, 0, ',', '.'),
                    number_format($r->total_revenue, 2, ',', '.'),
                    number_format($r->precio_coste, 2, ',', '.'),
                    number_format($r->precio_venta_publico, 2, ',', '.'),
                ], ';');
            }

            fclose($output);
        }, 200, $headers);
    }
}
