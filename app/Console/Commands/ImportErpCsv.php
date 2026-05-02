<?php

namespace App\Console\Commands;

use App\Models\ErpSale;
use App\Models\ErpSaleLine;
use Illuminate\Console\Command;

class ImportErpCsv extends Command
{
    protected $signature = 'app:import-erp-csv {path? : Ruta al CSV (por defecto: imagenes/rpt_export.csv)}';

    protected $description = 'Importa ventas desde un CSV exportado del ERP (cabeceras y líneas)';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('imagenes/rpt_export.csv');

        if (!file_exists($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return self::FAILURE;
        }

        $this->info("Leyendo {$path}...");

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->error('No se pudo abrir el archivo.');
            return self::FAILURE;
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            $this->error('El archivo CSV está vacío o mal formateado.');
            return self::FAILURE;
        }

        $headers = array_map(fn ($h) => trim($h, '"'), $headers);

        $hasLines = in_array('cod_articulo', $headers);

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lineCount = $file->key();
        $this->info("Registros a importar: " . ($lineCount - 1));
        $bar = $this->output->createProgressBar($lineCount - 1);
        $bar->start();

        rewind($handle);
        fgetcsv($handle, 0, ';'); // saltar header

        $total = 0;
        $now = now();

        if ($hasLines) {
            $headersMap = [];
            $linesChunk = [];
            $linesInserted = 0;
            $headersInserted = 0;

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $total++;
                $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));
                $map = array_combine($headers, $row);
                if ($map === false) {
                    $bar->advance();
                    continue;
                }

                $headerData = $this->mapHeader($map, $now);
                if ($headerData) {
                    $key = implode('|', [
                        $headerData['cod_empresa'],
                        $headerData['cod_venta'],
                        $headerData['cod_caja'],
                        $headerData['tipo_venta'],
                    ]);
                    $headersMap[$key] = $headerData;
                }

                $lineData = $this->mapLine($map, $now);
                if ($lineData) {
                    $linesChunk[] = $lineData;
                }

                if (count($linesChunk) >= 1000) {
                    $this->upsertLines($linesChunk);
                    $linesInserted += count($linesChunk);
                    $linesChunk = [];
                }

                $bar->advance();
            }

            if (!empty($linesChunk)) {
                $this->upsertLines($linesChunk);
                $linesInserted += count($linesChunk);
            }

            $headerChunks = array_chunk($headersMap, 1000);
            foreach ($headerChunks as $chunk) {
                $this->upsertHeaders(array_values($chunk));
                $headersInserted += count($chunk);
            }

            $bar->finish();
            $this->newLine();
            $this->info("Importadas {$linesInserted} líneas y {$headersInserted} cabeceras de {$total} registros leídos.");
        } else {
            $chunk = [];
            $imported = 0;
            $chunkSize = 500;

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $total++;
                $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));
                $map = array_combine($headers, $row);
                if ($map === false) {
                    $bar->advance();
                    continue;
                }

                $data = $this->mapHeader($map, $now);
                if ($data) {
                    $chunk[] = $data;
                }

                if (count($chunk) >= $chunkSize) {
                    $this->upsertHeaders($chunk);
                    $imported += count($chunk);
                    $bar->advance(count($chunk));
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
                $this->upsertHeaders($chunk);
                $imported += count($chunk);
                $bar->advance(count($chunk));
            }

            $bar->finish();
            $this->newLine();
            $this->info("Importados {$imported} registros de {$total} leídos.");
        }

        fclose($handle);

        return self::SUCCESS;
    }

    private function mapHeader(array $map, \DateTime $now): ?array
    {
        $codVenta = $this->int($map['cod_venta'] ?? null);
        $codEmpresa = $this->int($map['cod_empresa'] ?? null);

        if (empty($codVenta) || empty($codEmpresa)) {
            return null;
        }

        return [
            'cod_empresa' => $codEmpresa,
            'cod_venta' => $codVenta,
            'cod_caja' => $this->int($map['cod_caja'] ?? null),
            'cod_cliente' => $this->int($map['cod_cliente'] ?? null),
            'importe' => $this->decimal($map['importe'] ?? null),
            'importe_impuestos' => $this->decimal($map['importe_impuestos'] ?? null),
            'importe_cobrado' => $this->decimal($map['importe_cobrado'] ?? null),
            'importe_pendiente' => $this->decimal($map['importe_pendiente'] ?? null),
            'razon_social' => $this->strLimit($map['razon_social'] ?? null, 40),
            'tipo_venta' => $this->int($map['tipo_venta'] ?? null),
            'anulada' => $this->strLimit($map['anulada'] ?? null, 1),
            'fecha_venta' => $this->date($map['fecha_venta'] ?? null),
            'cod_forma_liquidacion' => $this->str($map['cod_forma_liquidacion'] ?? null),
            'cif' => $this->str($map['cif'] ?? null),
            'su_pedido' => $this->strLimit($map['su_pedido'] ?? null, 20),
            'cod_ruta' => $this->int($map['cod_ruta'] ?? null),
            'cod_transportista' => $this->int($map['cod_transportista'] ?? null),
            'bulbos' => $this->int($map['bultos'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function mapLine(array $map, \DateTime $now): ?array
    {
        $codVenta = $this->int($map['cod_venta'] ?? null);
        $codEmpresa = $this->int($map['cod_empresa'] ?? null);
        $linea = $this->int($map['linea'] ?? null);

        if (empty($codVenta) || empty($codEmpresa) || empty($linea)) {
            return null;
        }

        return [
            'cod_empresa' => $codEmpresa,
            'cod_venta' => $codVenta,
            'cod_caja' => $this->int($map['cod_caja'] ?? null),
            'tipo_venta' => $this->int($map['tipo_venta'] ?? null),
            'linea' => $linea,
            'cod_articulo' => $this->strLimit($map['cod_articulo'] ?? null, 15),
            'descripcion' => $this->strLimit($map['descripcion'] ?? null, 255),
            'cantidad' => $this->decimal($map['cantidad'] ?? null),
            'dto1' => $this->decimal($map['dto1'] ?? null),
            'dto2' => $this->decimal($map['dto2'] ?? null),
            'precio' => $this->decimal($map['precio'] ?? null),
            'importe' => $this->decimal($map['importe_linea'] ?? null),
            'importe_impuestos' => $this->decimal($map['importe_impuestos_linea'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function upsertHeaders(array $chunk): void
    {
        if (empty($chunk)) {
            return;
        }

        $columns = array_keys($chunk[0]);
        $updateColumns = array_diff($columns, ['created_at', 'cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja']);

        ErpSale::upsert(
            $chunk,
            ['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja'],
            $updateColumns
        );
    }

    private function upsertLines(array $chunk): void
    {
        if (empty($chunk)) {
            return;
        }

        $columns = array_keys($chunk[0]);
        $updateColumns = array_diff($columns, ['created_at', 'cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja', 'linea']);

        ErpSaleLine::upsert(
            $chunk,
            ['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja', 'linea'],
            $updateColumns
        );
    }

    private function int(mixed $v): ?int
    {
        $v = $this->str($v);
        return is_numeric($v) ? (int) $v : null;
    }

    private function decimal(mixed $v): ?float
    {
        $v = $this->str($v);
        if (empty($v)) {
            return null;
        }
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? (float) $v : null;
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v, '"');
        if ($v === '') {
            return null;
        }

        if (!mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
        }

        return $v;
    }

    private function strLimit(mixed $v, int $limit): ?string
    {
        $v = $this->str($v);
        if ($v === null) {
            return null;
        }
        return mb_substr($v, 0, $limit);
    }

    private function date(mixed $v): ?string
    {
        $v = $this->str($v);
        if (empty($v)) {
            return null;
        }

        $formats = ['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d H:i:s');
            }
        }

        return null;
    }
}
