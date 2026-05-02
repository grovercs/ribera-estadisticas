<?php

namespace App\Console\Commands;

use App\Models\ErpStockMovement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-stock-movements')]
#[Description('Importa movimientos de stock desde el ERP')]
class ImportErpStockMovements extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpStockMovement::class;
    }

    protected function getErpTable(): string
    {
        return 'movimiento_stock';
    }

    protected function getMapping(): array
    {
        return [
            'cod_articulo' => 'cod_articulo',
            'cod_empresa' => 'cod_empresa',
            'cod_documento' => 'cod_documento',
            'tipo_documento' => 'tipo_documento',
            'cod_caja' => 'cod_caja',
            'operacion' => 'operacion',
            'cod_cliente' => 'cod_cliente',
            'cod_proveedor' => 'cod_proveedor',
            'fecha' => 'fecha',
            'hora' => 'hora',
            'cod_almacen' => 'cod_almacen',
            'cantidad' => 'cantidad',
            'linea' => 'linea',
            'cod_movimiento' => 'cod_movimiento',
            'linea_kit' => 'linea_kit',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_movimiento'];
    }
}
