<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// JOIN impuestos -> cabecera para fecha. Importe total = SUM(importe) = base + cuota.
echo "=== impuestos_facturas_compras JOIN cabecera, empresa=1, por anyo ===\n";
foreach ($erp->select("SELECT YEAR(c.fecha_factura) y, COUNT(*) c, SUM(i.importe) s
    FROM impuestos_facturas_compras i
    JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa
    WHERE c.cod_empresa=1 AND c.fecha_factura IS NOT NULL
    GROUP BY YEAR(c.fecha_factura) ORDER BY y DESC") as $r)
    printf("  y=%s count=%5d sum=%12.2f\n", $r->y, $r->c, $r->s);

echo "\nEsperado: 2026=2715 / 2752955.49  |  2025=6572 / 6170109.55\n";

// 5 metricas del dashboard
echo "\n=== 5 metricas dashboard (empresa=1, importe) ===\n";
function m($erp, $sql, $b, $lbl, $exp) {
    $r = $erp->select($sql, $b)[0];
    printf("  %-28s count=%5d sum=%12.2f  (esp %s)\n", $lbl, $r->c, $r->s, $exp);
}
m($erp, "SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2026 AND MONTH(c.fecha_factura)=MONTH(GETDATE())", [], "2026 mes actual", "236/294066.72");
m($erp, "SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2026 AND MONTH(c.fecha_factura)=MONTH(DATEADD(MONTH,-1,GETDATE()))", [], "2026 mes anterior", "487/657575.31");
m($erp, "SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2026", [], "2026 year actual", "2715/2752955.49");
m($erp, "SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND c.fecha_factura>=? AND c.fecha_factura<=?", ["20250101 00:00:00","20250628 23:59:59"], "2025 periodo", "3092/2778886.19");
m($erp, "SELECT COUNT(*) c, SUM(i.importe) s FROM impuestos_facturas_compras i JOIN facturas_compras_cabecera c ON i.cod_factura=c.cod_factura AND i.cod_empresa=c.cod_empresa WHERE c.cod_empresa=1 AND YEAR(c.fecha_factura)=2025", [], "2025 year anterior", "6572/6170109.55");