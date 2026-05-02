<?php

namespace App\Console\Commands;

use App\Models\ErpSaleLine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-sale-lines')]
#[Description('Importa líneas de venta desde el ERP')]
class ImportErpSaleLines extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpSaleLine::class;
    }

    protected function getErpTable(): string
    {
        return 'ventas_linea';
    }

    protected function getMapping(): array
    {
        return [
            'cod_venta' => 'cod_venta',
            'tipo_venta' => 'tipo_venta',
            'cod_empresa' => 'cod_empresa',
            'cod_caja' => 'cod_caja',
            'linea' => 'linea',
            'cod_articulo' => 'cod_articulo',
            'descripcion' => 'descripcion',
            'cantidad' => 'cantidad',
            'dto1' => 'dto1',
            'dto2' => 'dto2',
            'precio' => 'precio',
            'precio_coste' => 'precio_coste',
            'cargo' => 'cargo',
            'importe' => 'importe',
            'importe_impuestos' => 'importe_impuestos',
            'cod_impuesto' => 'cod_impuesto',
            'porcentaje' => 'porcentaje',
            'inventariable' => 'inventariable',
            'cod_unidad' => 'cod_unidad',
            'kit' => 'kit',
            'unidades_venta' => 'unidades_venta',
            'precio_venta_base' => 'precio_venta_base',
            'precio_venta_publico' => 'precio_venta_publico',
            'estado_venta' => 'estado_venta',
            'descuento_maximo' => 'descuento_maximo',
            'cod_barras' => 'cod_barras',
            'cod_almacen' => 'cod_almacen',
            'fecha_entrega' => 'fecha_entrega',
            'observacion' => 'observacion',
            'peso_unitario' => 'peso_unitario',
            'peso_total' => 'peso_total',
            'cod_tarifa' => 'cod_tarifa',
            'version' => 'version',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja', 'linea'];
    }
}
