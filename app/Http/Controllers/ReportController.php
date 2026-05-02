<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function comparison(Request $request)
    {
        $year1 = $request->input('year1', date('Y') - 1);
        $year2 = $request->input('year2', date('Y'));

        $results = null;

        if ($request->has('compare')) {
            $results = $this->queryErpComparison($year1, $year2);
        }

        return view('reports.comparison', compact('year1', 'year2', 'results'));
    }

    private function queryErpComparison(int $year1, int $year2): array
    {
        $erp = DB::connection('erp');

        // KPIs por año
        $kpis = [];
        foreach ([$year1, $year2] as $year) {
            $kpi = $erp->select("
                SELECT
                    COUNT(*) as total_orders,
                    SUM(importe_impuestos) as total_sales,
                    AVG(importe_impuestos) as avg_ticket,
                    COUNT(DISTINCT cod_cliente) as unique_clients
                FROM hist_ventas_cabecera
                WHERE YEAR(fecha_venta) = ?
                    AND ISNULL(anulada, '') <> 'S'
            ", [$year])[0];

            $kpis[$year] = $kpi;
        }

        // Top 10 clientes por año
        $topClients = [];
        foreach ([$year1, $year2] as $year) {
            $topClients[$year] = $erp->select("
                SELECT TOP 10
                    v.cod_cliente,
                    MAX(c.razon_social) as razon_social,
                    SUM(v.importe_impuestos) as total_spent,
                    COUNT(*) as order_count
                FROM hist_ventas_cabecera v
                LEFT JOIN clientes c ON v.cod_cliente = c.cod_cliente
                WHERE YEAR(v.fecha_venta) = ?
                    AND ISNULL(v.anulada, '') <> 'S'
                GROUP BY v.cod_cliente
                ORDER BY total_spent DESC
            ", [$year]);
        }

        // Top 10 productos por año
        $topProducts = [];
        foreach ([$year1, $year2] as $year) {
            $topProducts[$year] = $erp->select("
                SELECT TOP 10
                    l.cod_articulo,
                    MAX(l.descripcion) as descripcion,
                    SUM(l.cantidad) as total_qty,
                    SUM(l.importe_impuestos) as total_revenue
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera v
                    ON l.cod_venta = v.cod_venta
                    AND l.tipo_venta = v.tipo_venta
                    AND l.cod_empresa = v.cod_empresa
                    AND l.cod_caja = v.cod_caja
                WHERE YEAR(v.fecha_venta) = ?
                    AND v.anulada <> 'S'
                    AND l.cod_articulo IS NOT NULL
                GROUP BY l.cod_articulo
                ORDER BY total_revenue DESC
            ", [$year]);
        }

        // Evolucion mensual combinada
        $monthly = $erp->select("
            SELECT
                YEAR(fecha_venta) as year,
                MONTH(fecha_venta) as month,
                SUM(importe_impuestos) as total
            FROM hist_ventas_cabecera
            WHERE YEAR(fecha_venta) IN (?, ?)
                AND ISNULL(anulada, '') <> 'S'
            GROUP BY YEAR(fecha_venta), MONTH(fecha_venta)
            ORDER BY year, month
        ", [$year1, $year2]);

        // Ventas por familia
        $byFamily = [];
        foreach ([$year1, $year2] as $year) {
            $byFamily[$year] = $erp->select("
                SELECT TOP 10
                    f.cod_familia,
                    MAX(f.descripcion) as familia,
                    SUM(l.importe_impuestos) as total_revenue
                FROM hist_ventas_linea l
                INNER JOIN hist_ventas_cabecera v
                    ON l.cod_venta = v.cod_venta
                    AND l.tipo_venta = v.tipo_venta
                    AND l.cod_empresa = v.cod_empresa
                    AND l.cod_caja = v.cod_caja
                INNER JOIN articulos a ON l.cod_articulo = a.cod_articulo
                INNER JOIN familias f ON a.cod_familia = f.cod_familia
                WHERE YEAR(v.fecha_venta) = ?
                    AND v.anulada <> 'S'
                GROUP BY f.cod_familia
                ORDER BY total_revenue DESC
            ", [$year]);
        }

        return compact('kpis', 'topClients', 'topProducts', 'monthly', 'byFamily');
    }
}
