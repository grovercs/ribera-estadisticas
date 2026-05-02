<?php

namespace App\Console\Commands;

use App\Models\ErpStock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-stock')]
#[Description('Importa stock actual desde el ERP')]
class ImportStock extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpStock::class;
    }

    protected function getErpTable(): string
    {
        return 'stocks';
    }

    protected function getMapping(): array
    {
        return [
            'cod_almacen' => 'cod_almacen',
            'cod_articulo' => 'cod_articulo',
            'existencias' => 'existencias',
            'fecha_ultima_entrada' => 'fecha_ultima_entrada',
            'fecha_ultima_salida' => 'fecha_ultima_salida',
            'maximos' => 'maximos',
            'minimos' => 'minimos',
            'ubicacion' => 'ubicacion',
            'fecha_ultimo_inventario' => 'fecha_ultimo_inventario',
            'hora_ultimo_inventario' => 'hora_ultimo_inventario',
            'permitir_venta_bajo_stock' => 'permitir_venta_bajo_stock',
            'cantidad_pendiente_servir' => 'cantidad_pendiente_servir',
            'cantidad_pendiente_recibir' => 'cantidad_pendiente_recibir',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_almacen', 'cod_articulo'];
    }
}
