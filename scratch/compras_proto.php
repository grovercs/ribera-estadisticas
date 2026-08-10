<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Claves de join cabecera
echo "=== hist_compras_cabecera: claves PK ===\n";
foreach ($erp->select("SELECT TOP 1 name FROM sys.columns WHERE object_id=object_id('hist_compras_cabecera') AND name='cod_compra'") as $r) echo "  cod_compra existe\n";

echo "\n=== cod_empresa en hist_compras_linea (2024-2026) ===\n";
foreach ($erp->select("SELECT cod_empresa, COUNT(*) c, SUM(CAST(cantidad AS float)) qty, SUM(CAST(importe AS float)) imp
    FROM hist_compras_linea
    WHERE cantidad > 0 AND importe IS NOT NULL
    GROUP BY cod_empresa ORDER BY c DESC") as $r)
    printf("  emp=%s lineas=%d qty=%.0f importe=%.2f\n", $r->cod_empresa, $r->c, $r->qty, $r->imp);

echo "\n=== Evolucion mensual del precio de compra (emp=1, 2024+) — total y avg neto ===\n";
$t0 = microtime(true);
foreach ($erp->select("SELECT YEAR(c.fecha_compra) y, MONTH(c.fecha_compra) m,
    COUNT(*) lineas, SUM(CAST(l.importe AS float)) importe, SUM(CAST(l.cantidad AS float)) cantidad,
    SUM(CAST(l.importe AS float))/NULLIF(SUM(CAST(l.cantidad AS float)),0) avg_precio
    FROM hist_compras_linea l
    JOIN hist_compras_cabecera c ON l.cod_compra=c.cod_compra AND l.tipo_compra=c.tipo_compra AND l.cod_empresa=c.cod_empresa
    WHERE l.cod_empresa=1 AND l.cantidad>0 AND l.importe>0 AND c.fecha_compra>='20240101'
    GROUP BY YEAR(c.fecha_compra), MONTH(c.fecha_compra)
    ORDER BY y, m") as $r)
    printf("  %d-%02d lineas=%5d importe=%11.2f qty=%9.0f avg=%.4f\n", $r->y, $r->m, $r->lineas, $r->importe, $r->cantidad, $r->avg_precio);
printf("  tiempo: %.2fs\n", microtime(true)-$t0);