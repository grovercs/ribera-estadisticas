<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$erp = DB::connection('erp');

// Muestra del ERP: [cod_proveedor, fecha, base_esperada, iva_esperado]
$muestra = [
    [583,   '2025-05-07', 219.90, 46.18],   // PLASTICOS TA-TAY
    [7246,  '2025-01-07', 89.73,  18.84],   // ROYO SPAIN
    [6693,  '2025-01-31', 1419.44, 298.08], // CEMENTOS MOLINS
    [12387, '2025-03-19', null, null],      // MARIA CRISTINA
    [2621,  '2025-02-04', 414.96, null],    // COFAN
    [2621,  '2025-02-14', 155.37, 32.62],   // COFAN
    [5036,  '2025-05-30', 211.68, 44.45],   // IMER DISTRIBUCION
    [18189, '2025-04-11', 44.55, 4.45],     // RENFE
    [18189, '2025-04-11', 40.91, 4.09],     // RENFE
    [9290,  '2025-05-12', 864.00, 86.40],   // PAMBE
    [841,   '2025-02-26', 11.85, 1.19],     // SERVEI I COMERÇ ELECTRODOMESTIC
    [841,   '2025-04-30', 48.84, 4.88],     // SERVEI I COMERÇ ELECTRODOMESTIC
    [18435, '2025-04-29', 68.22, null],     // AIGÜES DE BARCELONA
    [835,   '2025-05-12', 21.60, 2.16],     // MEDICLINICS
    [10011, '2025-03-01', 62.02, 6.20],     // ?
    [11180, '2025-04-24', null, null],      // CONSELH GENERAU
];

echo "cod_prov | fecha      | empresa | cod_factura          | importe(base) | imp_impuestos(total) | deducc | tipo | autofac\n";
echo str_repeat('-', 110) . "\n";

foreach ($muestra as [$prov, $fecha, $base, $iva]) {
    $rows = $erp->select("SELECT cod_empresa, cod_factura, importe, importe_impuestos, deducciones, tipo_factura, autofactura, cod_factura_proveedor
        FROM facturas_compras_cabecera
        WHERE cod_proveedor = ? AND CONVERT(date, fecha_factura) = ?
        ORDER BY cod_empresa, cod_factura", [$prov, $fecha]);
    if (empty($rows)) {
        // probar tambien por fecha_contabilizacion
        $rows2 = $erp->select("SELECT cod_empresa, cod_factura, importe, importe_impuestos, deducciones, tipo_factura, autofactura, cod_factura_proveedor, fecha_factura, fecha_contabilizacion
            FROM facturas_compras_cabecera
            WHERE cod_proveedor = ? AND CONVERT(date, fecha_contabilizacion) = ?
            ORDER BY cod_empresa, cod_factura", [$prov, $fecha]);
        if (empty($rows2)) {
            printf("%-8d | %s | *** NO ENCONTRADA en cabecera (ni fecha_factura ni contab) ***\n", $prov, $fecha);
        } else {
            printf("%-8d | %s | (por fecha_contab) rows=%d\n", $prov, $fecha, count($rows2));
            foreach ($rows2 as $r) printf("         emp=%s fac=%s imp=%.2f impIV=%.2f ffac=%s fcont=%s\n", $r->cod_empresa, $r->cod_factura, $r->importe, $r->importe_impuestos, $r->fecha_factura, $r->fecha_contabilizacion);
        }
    } else {
        foreach ($rows as $r) {
            printf("%-8d | %s | %-7s | %-20s | %12.2f | %16.2f | %.2f | %s | %s\n",
                $prov, $fecha, $r->cod_empresa, $r->cod_factura, $r->importe, $r->importe_impuestos, $r->deducciones, $r->tipo_factura, $r->autofactura);
        }
    }
}

// Ademas: cuantas facturas tiene RENFE (18189) en 2025 en cabecera, y AIGÜES (18435), IMER (5036)
echo "\n=== Conteo 2025 por proveedor en cabecera (empresa=1) ===\n";
foreach ([18189, 18435, 5036, 583, 6693, 2621, 835, 10011, 11180, 9290, 841, 7246, 12387] as $p) {
    $r = $erp->select("SELECT COUNT(*) c, SUM(importe) b, SUM(importe_impuestos) t FROM facturas_compras_cabecera WHERE cod_proveedor=? AND cod_empresa=1 AND YEAR(fecha_factura)=2025", [$p])[0];
    $nom = $erp->select("SELECT TOP 1 razon_social FROM proveedores WHERE cod_proveedor=?", [$p]);
    $n = $nom ? $nom[0]->razon_social : '?';
    printf("  %6d %-40s count=%3d base=%10.2f total=%10.2f\n", $p, substr($n,0,40), $r->c, $r->b, $r->t);
}