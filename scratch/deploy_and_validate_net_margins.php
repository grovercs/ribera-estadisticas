<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$fmtEur = function ($n) {
    return number_format((float) $n, 2, ',', '.') . ' €';
};

function section($t) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo $t . "\n";
    echo str_repeat('=', 70) . "\n";
}

function row($label, $value) {
    echo str_pad($label, 30) . $value . "\n";
}

// ----------------------------------------------------------------------------
// 1. Leer y mostrar SQL
// ----------------------------------------------------------------------------
$sqlFile = __DIR__ . '/rpc_net_margins.sql';
$sql = file_get_contents($sqlFile);

section('1. SQL A EJECUTAR');
echo "Archivo: {$sqlFile}\n";
echo "Longitud: " . strlen($sql) . " bytes\n";
echo "\n--- Primeras 20 líneas ---\n";
echo implode("\n", array_slice(explode("\n", $sql), 0, 20)) . "\n";

// ----------------------------------------------------------------------------
// 2. Ejecutar SQL en Supabase
// ----------------------------------------------------------------------------
section('2. CREANDO RPC EN SUPABASE LIVE');
try {
    DB::connection('supabase')->unprepared($sql);
    echo "✓ RPC creada/actualizada correctamente.\n";
} catch (Throwable $e) {
    echo "✗ Error creando RPC: " . $e->getMessage() . "\n";
    exit(1);
}

// ----------------------------------------------------------------------------
// 3. Verificar seguridad
// ----------------------------------------------------------------------------
section('3. VERIFICANDO SEGURIDAD');
$security = DB::connection('supabase')->select("
    SELECT
        p.proisstrict,
        p.prosecdef,
        p.proacl,
        pg_get_function_identity_arguments(p.oid) as args,
        n.nspname as schema,
        p.proname as name
    FROM pg_proc p
    JOIN pg_namespace n ON p.pronamespace = n.oid
    WHERE n.nspname = 'public'
      AND p.proname = 'get_dashboard_net_margins'
");

if (empty($security)) {
    echo "✗ No se encontró la función.\n";
    exit(1);
}

$s = $security[0];
row('Schema:', $s->schema);
row('Name:', $s->name);
row('Args:', $s->args);
row('SECURITY DEFINER:', $s->prosecdef ? 'SÍ (NO deseado)' : 'NO (SECURITY INVOKER)');
row('STRICT:', $s->proisstrict ? 'SÍ' : 'NO');
row('ACL:', $s->proacl ?: '(vacío)');

// Verificar grants específicos
$grants = DB::connection('supabase')->select("
    SELECT grantee, privilege_type
    FROM information_schema.role_routine_grants
    WHERE routine_schema = 'public'
      AND routine_name = 'get_dashboard_net_margins'
");

echo "\nGrants:\n";
foreach ($grants as $g) {
    row('  ' . $g->grantee . ':', $g->privilege_type);
}

// ----------------------------------------------------------------------------
// 4. Paridad A, B, C
// ----------------------------------------------------------------------------
$ranges = [
    ['A', 2026, 1, 2026, 8],
    ['B', 2025, 1, 2025, 8],
    ['C', 2025, 11, 2026, 2],
];

$results = [];

section('4. RESULTADOS RPC POR RANGO');
foreach ($ranges as [$label, $yf, $mf, $yt, $mt]) {
    $r = DB::connection('supabase')->select(
        'SELECT * FROM public.get_dashboard_net_margins(?, ?, ?, ?)',
        [$yf, $mf, $yt, $mt]
    )[0] ?? null;

    $results[$label] = $r;

    echo "\n{$label}) {$yf}-" . str_pad($mf, 2, '0', STR_PAD_LEFT) . " → {$yt}-" . str_pad($mt, 2, '0', STR_PAD_LEFT) . "\n";
    if (!$r) {
        echo "  ✗ Sin resultado\n";
        continue;
    }
    row('  Venta:', $fmtEur($r->venta));
    row('  Coste:', $fmtEur($r->coste));
    row('  Margen:', $fmtEur($r->margen));
    row('  Margen %:', $r->margen_porcentaje . ' %');
}

// ----------------------------------------------------------------------------
// 5. Validación contra consulta independiente
// ----------------------------------------------------------------------------
section('5. VALIDACIÓN CONTRA CONSULTA INDEPENDIENTE');

function controlQuery($yf, $mf, $yt, $mt) {
    $start = sprintf('%04d-%02d-01', $yf, $mf);
    $end = (new DateTime(sprintf('%04d-%02d-01', $yt, $mt)))
        ->modify('+1 month')
        ->format('Y-m-d');

    $sql = "
        SELECT
            COALESCE(SUM(h.net_amount), 0) AS venta,
            COALESCE(SUM(doc.coste), 0) AS coste,
            COALESCE(SUM(h.net_amount), 0) - COALESCE(SUM(doc.coste), 0) AS margen
        FROM sales_headers h
        LEFT JOIN LATERAL (
            SELECT SUM(l.precio_coste * l.cantidad) AS coste
            FROM sales_lines l
            WHERE l.cod_venta = h.cod_venta
              AND l.tipo_venta = h.tipo_venta
              AND l.cod_empresa = h.cod_empresa
              AND l.cod_caja = h.cod_caja
              AND l.precio_coste IS NOT NULL
        ) doc ON TRUE
        WHERE h.tipo_venta IN (2, 4, 5)
          AND h.anulada IS NOT TRUE
          AND h.fecha_venta >= '{$start}'
          AND h.fecha_venta <  '{$end}'
    ";

    return DB::connection('supabase')->select($sql)[0];
}

function diff($a, $b) {
    return (float) $a - (float) $b;
}

echo str_pad('RANGO', 10)
    . str_pad('RPC VENTA', 18, ' ', STR_PAD_LEFT)
    . str_pad('CTRL VENTA', 18, ' ', STR_PAD_LEFT)
    . str_pad('DIF', 16, ' ', STR_PAD_LEFT)
    . str_pad('RPC COSTE', 18, ' ', STR_PAD_LEFT)
    . str_pad('CTRL COSTE', 18, ' ', STR_PAD_LEFT)
    . str_pad('DIF', 16, ' ', STR_PAD_LEFT)
    . "\n";
echo str_repeat('-', 122) . "\n";

foreach ($ranges as [$label, $yf, $mf, $yt, $mt]) {
    $rpc = $results[$label] ?? null;
    $ctrl = controlQuery($yf, $mf, $yt, $mt);

    if (!$rpc) {
        echo str_pad($label, 10) . "SIN RESULTADO RPC\n";
        continue;
    }

    echo str_pad($label, 10)
        . str_pad($fmtEur($rpc->venta), 18, ' ', STR_PAD_LEFT)
        . str_pad($fmtEur($ctrl->venta), 18, ' ', STR_PAD_LEFT)
        . str_pad($fmtEur(diff($rpc->venta, $ctrl->venta)), 16, ' ', STR_PAD_LEFT)
        . str_pad($fmtEur($rpc->coste), 18, ' ', STR_PAD_LEFT)
        . str_pad($fmtEur($ctrl->coste), 18, ' ', STR_PAD_LEFT)
        . str_pad($fmtEur(diff($rpc->coste, $ctrl->coste)), 16, ' ', STR_PAD_LEFT)
        . "\n";
}

// ----------------------------------------------------------------------------
// 6. Validación contra Cuadro de Dirección (get_store_dashboard_margins)
// ----------------------------------------------------------------------------
section('6. VALIDACIÓN CONTRA CUADRO DE DIRECCIÓN');

$yearRes = DB::connection('supabase')->select("SELECT * FROM public.get_store_dashboard_margins('year')")[0] ?? null;
$yearRows = json_decode($yearRes->year_rows ?? '[]', true);
$yearTotal = array_reduce($yearRows, function ($acc, $r) {
    $acc['venta'] += (float) ($r['venta'] ?? 0);
    $acc['coste'] += (float) ($r['coste'] ?? 0);
    return $acc;
}, ['venta' => 0, 'coste' => 0]);
$yearTotal['margen'] = $yearTotal['venta'] - $yearTotal['coste'];
$yearTotal['margen_pct'] = $yearTotal['venta'] > 0
    ? round(($yearTotal['margen'] / $yearTotal['venta']) * 100, 2)
    : 0;

$rpcA = $results['A'] ?? null;

row('RPC A (2026-01→08) venta:', $fmtEur($rpcA->venta ?? 0));
row('Cuadro year 2026 venta:', $fmtEur($yearTotal['venta']));
row('Diferencia venta:', $fmtEur(diff($rpcA->venta ?? 0, $yearTotal['venta'])));
row('RPC A margen %:', ($rpcA->margen_porcentaje ?? 0) . ' %');
row('Cuadro year margen %:', $yearTotal['margen_pct'] . ' %');

echo "\nNota: get_store_dashboard_margins('year') usa CURRENT_DATE y puede\n";
echo "incluir datos hasta el último día con ventas en 2026. El rango A\n";
echo "(2026-01 → 2026-08) es un subconjunto explícito de enero a agosto.\n";
echo "Por tanto, la venta del Cuadro de Dirección debe ser MAYOR O IGUAL\n";
echo "que la del rango A. Cualquier diferencia excesiva indicaría un\n";
echo "problema de fechas o de inclusión de meses futuros.\n";

// ----------------------------------------------------------------------------
// 7. Prueba frontend (build)
// ----------------------------------------------------------------------------
section('7. BUILD FRONTEND');
echo "Ejecutando npm run build...\n";
$buildOutput = [];
$exitCode = 0;
exec('cd ' . escapeshellarg(__DIR__ . '/../frontend') . ' && npm run build 2>&1', $buildOutput, $exitCode);
echo implode("\n", $buildOutput) . "\n";

if ($exitCode === 0) {
    echo "\n✓ Build exitoso.\n";
} else {
    echo "\n✗ Build falló con código {$exitCode}.\n";
}

exit($exitCode);
