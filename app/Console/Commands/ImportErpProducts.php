<?php

namespace App\Console\Commands;

use App\Models\ErpProduct;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-products')]
#[Description('Importa artículos desde el ERP')]
class ImportErpProducts extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpProduct::class;
    }

    protected function getErpTable(): string
    {
        return 'articulos';
    }

    protected function getMapping(): array
    {
        return [
            'cod_articulo' => 'cod_articulo',
            'marca' => 'marca',
            'cod_barras' => 'cod_barras',
            'cod_familia' => 'cod_familia',
            'cod_subfamilia' => 'cod_subfamilia',
            'cod_impuesto' => 'cod_impuesto',
            'unidades_venta' => 'unidades_venta',
            'cod_unidad' => 'cod_unidad',
            'cod_proveedor_activo' => 'cod_proveedor_activo',
            'fecha_alta' => 'fecha_alta',
            'fecha_baja' => 'fecha_baja',
            'precio_coste' => 'precio_coste',
            'incremento' => 'incremento',
            'incremento_minimo' => 'incremento_minimo',
            'cargo' => 'cargo',
            'precio_venta_base' => 'precio_venta_base',
            'precio_venta_publico' => 'precio_venta_publico',
            'precio_medio_ponderado' => 'precio_medio_ponderado',
            'descuento_maximo' => 'descuento_maximo',
            'cod_gama' => 'cod_gama',
            'peso_web' => 'peso_web',
            'alto_web' => 'alto_web',
            'ancho_web' => 'ancho_web',
            'profundo_web' => 'profundo_web',
            'tipo_articulo' => 'tipo_articulo',
            'advertencia' => 'advertencia',
            'observaciones' => 'observaciones',
            'imagen' => 'imagen',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_articulo'];
    }
}
