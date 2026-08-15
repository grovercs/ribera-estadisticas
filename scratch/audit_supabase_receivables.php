<?php
/**
 * Auditoria READ-ONLY de Supabase LIVE para receivables.
 * Standalone PHP 8.2 compatible. Lee credenciales de .env.
 * NO modifica datos.
 */

function env_value(string $key, string $default = ''): string
{
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        return $default;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            if ($k === $key) {
                return trim($v, " \t\n\r\v\0\"");
            }
        }
    }
    return $default;
}

$host = env_value('SUPABASE_DB_HOST');
$port = env_value('SUPABASE_DB_PORT', '5432');
$dbname = env_value('SUPABASE_DB_DATABASE');
$user = env_value('SUPABASE_DB_USERNAME');
$pass = env_value('SUPABASE_DB_PASSWORD');

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
]);

function q(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

echo "=== AUDITORIA SUPABASE LIVE — RECEIVABLES ===\n\n";

// 1. Columnas de receivables
echo "-- 1. COLUMNAS public.receivables --\n";
$cols = q($pdo, "
    SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable
    FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'receivables'
    ORDER BY ordinal_position
");
foreach ($cols as $c) {
    echo "  {$c->column_name} {$c->data_type}";
    if ($c->data_type === 'character varying' && $c->character_maximum_length) {
        echo "({$c->character_maximum_length})";
    }
    if (in_array($c->data_type, ['numeric', 'decimal'])) {
        echo "({$c->numeric_precision},{$c->numeric_scale})";
    }
    echo " " . ($c->is_nullable === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
echo "\n";

// 2. PK
echo "-- 2. PRIMARY KEY --\n";
$pk = q($pdo, "
    SELECT kcu.column_name
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu
        ON tc.constraint_name = kcu.constraint_name
        AND tc.table_schema = kcu.table_schema
    WHERE tc.table_schema = 'public'
      AND tc.table_name = 'receivables'
      AND tc.constraint_type = 'PRIMARY KEY'
    ORDER BY kcu.ordinal_position
");
foreach ($pk as $p) {
    echo "  {$p->column_name}\n";
}
echo "\n";

// 3. Indices
echo "-- 3. INDICES --\n";
$idx = q($pdo, "SELECT indexname, indexdef FROM pg_indexes WHERE schemaname='public' AND tablename='receivables'");
foreach ($idx as $i) {
    echo "  {$i->indexname}: {$i->indexdef}\n";
}
echo "\n";

// 4. RLS
echo "-- 4. ROW LEVEL SECURITY --\n";
$rls = q($pdo, "SELECT relname, relrowsecurity FROM pg_class WHERE relname='receivables'");
foreach ($rls as $r) {
    echo "  RLS: " . ($r->relrowsecurity ? 'SI' : 'NO') . "\n";
}
echo "\n";

// 5. Policies
echo "-- 5. POLICIES --\n";
$pol = q($pdo, "SELECT policyname, permissive, roles, cmd, qual FROM pg_policies WHERE schemaname='public' AND tablename='receivables'");
if (empty($pol)) {
    echo "  Sin policies\n";
} else {
    foreach ($pol as $p) {
        echo "  {$p->policyname} | cmd={$p->cmd} | roles={$p->roles}\n";
    }
}
echo "\n";

// 6. Grants
echo "-- 6. GRANTS --\n";
$grants = q($pdo, "SELECT grantee, privilege_type FROM information_schema.table_privileges WHERE table_schema='public' AND table_name='receivables' ORDER BY grantee, privilege_type");
$grantMap = [];
foreach ($grants as $g) {
    $grantMap[$g->grantee][] = $g->privilege_type;
}
foreach ($grantMap as $grantee => $privs) {
    echo "  {$grantee}: " . implode(', ', $privs) . "\n";
}
echo "\n";

// 7. Batches y filas
echo "-- 7. BATCHES Y FILAS --\n";
$batches = q($pdo, "
    SELECT batch_id, COUNT(*) as filas, MIN(synced_at) as min_synced, MAX(synced_at) as max_synced
    FROM receivables
    GROUP BY batch_id
    ORDER BY max_synced DESC NULLS LAST
");
foreach ($batches as $b) {
    echo "  batch=" . substr($b->batch_id, 0, 8) . "... filas={$b->filas} min={$b->min_synced} max={$b->max_synced}\n";
}
echo "\n";

// 8. sync_state
echo "-- 8. sync_state dataset=receivables --\n";
$state = q($pdo, "SELECT * FROM sync_state WHERE dataset = 'receivables'");
if (empty($state)) {
    echo "  NO EXISTE fila receivables\n";
    $activeBatch = null;
} else {
    $s = $state[0];
    $activeBatch = $s->active_batch_id;
    echo "  active_batch_id=" . substr((string)$activeBatch, 0, 8) . "... last_success_at={$s->last_success_at} status={$s->last_run_status}\n";
}
echo "\n";

// 9. Funciones relacionadas: existencia, firma, fuente resumido
echo "-- 9. FUNCIONES RPC RELACIONADAS --\n";
$funcs = [
    'get_active_receivables_batch',
    'get_receivables_summary',
    'get_receivables_aging',
    'get_receivables_top_debtors',
    'get_receivables_upcoming',
    'get_store_dashboard_impagados',
];
foreach ($funcs as $fn) {
    $exists = q($pdo, "SELECT 1 as existe FROM pg_proc WHERE proname = ?", [$fn]);
    $si = empty($exists) ? 'NO' : 'SI';
    echo "\n  {$fn}: {$si}\n";
    if ($si === 'SI') {
        $sig = q($pdo, "
            SELECT pg_get_function_result(oid) as result_type, pg_get_function_arguments(oid) as args
            FROM pg_proc WHERE proname = ?
        ", [$fn]);
        foreach ($sig as $sg) {
            echo "    firma: {$sg->args} -> {$sg->result_type}\n";
        }
        $src = q($pdo, "SELECT prosrc FROM pg_proc WHERE proname = ?", [$fn]);
        $body = $src[0]->prosrc ?? '';
        // Lineas clave para entender dependencias y filtros
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            $l = trim($line);
            if ($l === '' || str_starts_with($l, '--')) {
                continue;
            }
            if (stripos($l, 'batch_id') !== false
                || stripos($l, 'active_batch') !== false
                || stripos($l, 'receivable_payments') !== false
                || stripos($l, 'cod_forma_liquidacion') !== false
                || stripos($l, 'cod_remesa') !== false
                || stripos($l, 'fecha_vencimiento') !== false
                || stripos($l, 'importe_pendiente') !== false
                || stripos($l, 'importe_original') !== false
                || stripos($l, 'INSERT') !== false
                || stripos($l, 'DELETE') !== false
                || stripos($l, 'UPDATE') !== false
            ) {
                echo "    " . substr($l, 0, 120) . "\n";
            }
        }
    }
}
echo "\n";

// 10. Tabla receivable_payments si existe
echo "-- 10. receivable_payments --\n";
$paymentsExists = q($pdo, "SELECT 1 as existe FROM pg_tables WHERE schemaname='public' AND tablename='receivable_payments'");
if (empty($paymentsExists)) {
    echo "  NO EXISTE\n";
} else {
    $pcols = q($pdo, "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='receivable_payments' ORDER BY ordinal_position");
    foreach ($pcols as $c) {
        echo "  {$c->column_name} {$c->data_type}\n";
    }
    $pcount = q($pdo, "SELECT COUNT(*) as n FROM receivable_payments")[0]->n;
    echo "  filas={$pcount}\n";
}
echo "\n";

// 11. Si hay batch activo, mostrar totales usando nombres reales de columnas
echo "-- 11. TOTALES BATCH ACTIVO --\n";
if (!empty($activeBatch)) {
    $tot = q($pdo, "
        SELECT
            COUNT(*) as filas,
            COALESCE(SUM(importe_original),0) as importe_total,
            COALESCE(SUM(importe_cobrado),0) as importe_cobrado_total,
            COALESCE(SUM(importe_pendiente),0) as importe_pendiente_total,
            COALESCE(SUM(CASE WHEN cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC') THEN importe_pendiente ELSE 0 END),0) as impagados_pendiente,
            COUNT(CASE WHEN cod_forma_liquidacion IN ('ZIMP','ZJUZ','ZPER','ZCYC') THEN 1 END) as impagados_count,
            COALESCE(SUM(CASE WHEN cod_remesa IS NULL AND cod_forma_liquidacion NOT IN ('ZIMP','ZJUZ','ZPER','ZCYC') THEN importe_pendiente ELSE 0 END),0) as pendientes_normales_pendiente,
            COUNT(CASE WHEN cod_remesa IS NULL AND cod_forma_liquidacion NOT IN ('ZIMP','ZJUZ','ZPER','ZCYC') THEN 1 END) as pendientes_normales_count
        FROM receivables
        WHERE batch_id = ?
    ", [$activeBatch])[0];
    echo "  filas={$tot->filas}\n";
    echo "  importe_total={$tot->importe_total}\n";
    echo "  importe_cobrado_total={$tot->importe_cobrado_total}\n";
    echo "  importe_pendiente_total={$tot->importe_pendiente_total}\n";
    echo "  impagados_count={$tot->impagados_count} impagados_pendiente={$tot->impagados_pendiente}\n";
    echo "  pendientes_normales_count={$tot->pendientes_normales_count} pendientes_normales_pendiente={$tot->pendientes_normales_pendiente}\n";
} else {
    echo "  Sin batch activo conocido\n";
}
echo "\n";

echo "=== FIN AUDITORIA ===\n";
