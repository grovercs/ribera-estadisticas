<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class BackfillSalesNetAmount extends Command
{
    private const BATCH_SIZE = 200;

    protected $signature = 'ribera:backfill-sales-net-amount
                            {--from= : Fecha inicial inclusiva (YYYY-MM-DD)}
                            {--to= : Fecha final inclusiva (YYYY-MM-DD)}
                            {--dry-run : Muestra el precheck sin actualizar datos}';

    protected $description = 'Rellena exclusivamente sales_lines.net_amount desde el ERP por un rango de fechas';

    public function handle(): int
    {
        try {
            $from = $this->parseDateOption('from');
            $to = $this->parseDateOption('to');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($from->greaterThan($to)) {
            $this->error('La fecha --from no puede ser posterior a --to.');

            return self::FAILURE;
        }

        $fromDate = $from->toDateString();
        $toExclusive = $to->addDay()->toDateString();
        $precheck = $this->periodCounts($fromDate, $toExclusive);

        $this->info("Backfill net_amount: {$fromDate} hasta {$to->toDateString()} (ambos inclusive).");
        $this->line("Precheck — total: {$precheck['total']}; ya rellenadas: {$precheck['filled']}; pendientes: {$precheck['null']}.");

        if ($this->option('dry-run')) {
            $this->info('Dry run: no se ha modificado ninguna fila.');

            return self::SUCCESS;
        }

        $processed = 0;
        $updated = 0;
        $erpNotFound = 0;
        $errors = 0;
        $cursor = null;

        try {
            while (true) {
                $query = $this->pendingLinesQuery($fromDate, $toExclusive);
                if ($cursor !== null) {
                    $this->applyAfterCursor($query, $cursor);
                }

                $candidates = $query
                    ->orderBy('l.cod_venta')
                    ->orderBy('l.tipo_venta')
                    ->orderBy('l.cod_empresa')
                    ->orderBy('l.cod_caja')
                    ->orderBy('l.linea')
                    ->limit(self::BATCH_SIZE)
                    ->get();

                if ($candidates->isEmpty()) {
                    break;
                }

                $processed += $candidates->count();
                $cursor = $this->lineCursorFromRow($candidates->last());
                $erpAmounts = $this->fetchErpNetAmounts($candidates->all());
                $updates = [];

                foreach ($candidates as $candidate) {
                    $key = $this->lineKeyFromRow($candidate);
                    if (!array_key_exists($key, $erpAmounts)) {
                        $erpNotFound++;
                        continue;
                    }

                    $updates[] = [
                        'cod_venta' => trim((string) $candidate->cod_venta),
                        'tipo_venta' => (int) $candidate->tipo_venta,
                        'cod_empresa' => trim((string) $candidate->cod_empresa),
                        'cod_caja' => trim((string) $candidate->cod_caja),
                        'linea' => (int) $candidate->linea,
                        'net_amount' => $erpAmounts[$key],
                    ];
                }

                if ($updates !== []) {
                    $updated += $this->updateNetAmounts($updates);
                }

                $this->line("Progreso — procesadas: {$processed}; actualizadas: {$updated}; no encontradas ERP: {$erpNotFound}; errores: {$errors}.");
            }
        } catch (Throwable $exception) {
            $errors++;
            $this->error('El backfill se detuvo por un error: ' . $exception->getMessage());
        }

        $postcheck = $this->periodCounts($fromDate, $toExclusive);
        $this->line("Resultado — procesadas: {$processed}; actualizadas: {$updated}; no encontradas ERP: {$erpNotFound}; errores: {$errors}; pendientes: {$postcheck['null']}.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function parseDateOption(string $name): CarbonImmutable
    {
        $value = $this->option($name);
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException("La opción --{$name} debe usar el formato YYYY-MM-DD.");
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        $errors = CarbonImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("La opción --{$name} no contiene una fecha válida.");
        }

        return $date;
    }

    private function periodCounts(string $fromDate, string $toExclusive): array
    {
        $base = $this->periodLinesQuery($fromDate, $toExclusive);

        return [
            'total' => (clone $base)->count(),
            'filled' => (clone $base)->whereNotNull('l.net_amount')->count(),
            'null' => (clone $base)->whereNull('l.net_amount')->count(),
        ];
    }

    private function pendingLinesQuery(string $fromDate, string $toExclusive): Builder
    {
        return $this->periodLinesQuery($fromDate, $toExclusive)
            ->whereNull('l.net_amount')
            ->select('l.cod_venta', 'l.tipo_venta', 'l.cod_empresa', 'l.cod_caja', 'l.linea');
    }

    private function periodLinesQuery(string $fromDate, string $toExclusive): Builder
    {
        return DB::connection('supabase')
            ->table('sales_lines as l')
            ->join('sales_headers as h', function ($join) {
                $join->on('h.cod_venta', '=', 'l.cod_venta')
                    ->on('h.tipo_venta', '=', 'l.tipo_venta')
                    ->on('h.cod_empresa', '=', 'l.cod_empresa')
                    ->on('h.cod_caja', '=', 'l.cod_caja');
            })
            ->where('h.fecha_venta', '>=', $fromDate)
            ->where('h.fecha_venta', '<', $toExclusive);
    }

    private function applyAfterCursor(Builder $query, array $cursor): void
    {
        $query->where(function (Builder $where) use ($cursor) {
            $where->where('l.cod_venta', '>', $cursor['cod_venta'])
                ->orWhere(function (Builder $next) use ($cursor) {
                    $next->where('l.cod_venta', $cursor['cod_venta'])
                        ->where('l.tipo_venta', '>', $cursor['tipo_venta']);
                })
                ->orWhere(function (Builder $next) use ($cursor) {
                    $next->where('l.cod_venta', $cursor['cod_venta'])
                        ->where('l.tipo_venta', $cursor['tipo_venta'])
                        ->where('l.cod_empresa', '>', $cursor['cod_empresa']);
                })
                ->orWhere(function (Builder $next) use ($cursor) {
                    $next->where('l.cod_venta', $cursor['cod_venta'])
                        ->where('l.tipo_venta', $cursor['tipo_venta'])
                        ->where('l.cod_empresa', $cursor['cod_empresa'])
                        ->where('l.cod_caja', '>', $cursor['cod_caja']);
                })
                ->orWhere(function (Builder $next) use ($cursor) {
                    $next->where('l.cod_venta', $cursor['cod_venta'])
                        ->where('l.tipo_venta', $cursor['tipo_venta'])
                        ->where('l.cod_empresa', $cursor['cod_empresa'])
                        ->where('l.cod_caja', $cursor['cod_caja'])
                        ->where('l.linea', '>', $cursor['linea']);
                });
        });
    }

    private function fetchErpNetAmounts(array $lines): array
    {
        $predicates = [];
        $bindings = [];

        foreach ($lines as $line) {
            $predicates[] = '(cod_venta = ? AND tipo_venta = ? AND cod_empresa = ? AND cod_caja = ? AND linea = ?)';
            $bindings[] = $line->cod_venta;
            $bindings[] = $line->tipo_venta;
            $bindings[] = $line->cod_empresa;
            $bindings[] = $line->cod_caja;
            $bindings[] = $line->linea;
        }

        $erpLines = DB::connection('erp')->select(
            'SELECT cod_venta, tipo_venta, cod_empresa, cod_caja, linea, importe FROM hist_ventas_linea WHERE ' . implode(' OR ', $predicates),
            $bindings,
        );
        $amounts = [];

        foreach ($erpLines as $line) {
            if ($line->importe === null) {
                continue;
            }

            $amounts[$this->lineKeyFromRow($line)] = (float) $line->importe;
        }

        return $amounts;
    }

    private function updateNetAmounts(array $updates): int
    {
        $values = [];
        $bindings = [];

        foreach ($updates as $update) {
            $values[] = '(?::varchar, ?::integer, ?::varchar, ?::varchar, ?::integer, ?::numeric)';
            array_push(
                $bindings,
                $update['cod_venta'],
                $update['tipo_venta'],
                $update['cod_empresa'],
                $update['cod_caja'],
                $update['linea'],
                $update['net_amount'],
            );
        }

        $sql = '
            UPDATE sales_lines AS target
            SET net_amount = source.net_amount
            FROM (VALUES ' . implode(', ', $values) . ') AS source
                (cod_venta, tipo_venta, cod_empresa, cod_caja, linea, net_amount)
            WHERE target.cod_venta = source.cod_venta
              AND target.tipo_venta = source.tipo_venta
              AND target.cod_empresa = source.cod_empresa
              AND target.cod_caja = source.cod_caja
              AND target.linea = source.linea
              AND target.net_amount IS NULL
        ';

        return DB::connection('supabase')->update($sql, $bindings);
    }

    private function lineKeyFromRow(object $line): string
    {
        return implode('|', $this->lineCursorFromRow($line));
    }

    private function lineCursorFromRow(object $line): array
    {
        return [
            'cod_venta' => trim((string) $line->cod_venta),
            'tipo_venta' => (int) $line->tipo_venta,
            'cod_empresa' => trim((string) $line->cod_empresa),
            'cod_caja' => trim((string) $line->cod_caja),
            'linea' => (int) $line->linea,
        ];
    }
}
