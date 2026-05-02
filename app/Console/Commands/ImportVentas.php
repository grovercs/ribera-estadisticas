<?php

namespace App\Console\Commands;

use App\Models\ErpSale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-sales')]
#[Description('Importa cabeceras de ventas desde el ERP')]
class ImportVentas extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpSale::class;
    }

    protected function getErpTable(): string
    {
        return 'ventas_cabecera';
    }

    protected function getMapping(): array
    {
        return [
            'cod_venta' => 'cod_venta',
            'tipo_venta' => 'tipo_venta',
            'cod_empresa' => 'cod_empresa',
            'cod_caja' => 'cod_caja',
            'cod_almacen' => 'cod_almacen',
            'cod_cliente' => 'cod_cliente',
            'nombre_comercial' => 'nombre_comercial',
            'razon_social' => 'razon_social',
            'cif' => 'cif',
            'direccion1' => 'direccion1',
            'cp' => 'cp',
            'poblacion' => 'poblacion',
            'provincia' => 'provincia',
            'cod_pais' => 'cod_pais',
            'cod_divisa' => 'cod_divisa',
            'impuestos_incluidos' => 'impuestos_incluidos',
            'cambio' => 'cambio',
            'fecha_venta' => 'fecha_venta',
            'hora_venta' => 'hora_venta',
            'cod_forma_liquidacion' => 'cod_forma_liquidacion',
            'cod_vendedor' => 'cod_vendedor',
            'nombre_vendedor' => 'nombre_vendedor',
            'su_pedido' => 'su_pedido',
            'importe_cobrado' => 'importe_cobrado',
            'importe_pendiente' => 'importe_pendiente',
            'facturado' => 'facturado',
            'importe' => 'importe',
            'importe_impuestos' => 'importe_impuestos',
            'estado_venta' => 'estado_venta',
            'anulada' => 'anulada',
            'cod_factura_asignada' => 'cod_factura_asignada',
            'fecha_entrega' => 'fecha_entrega',
            'cod_arqueo' => 'cod_arqueo',
            'peso_total' => 'peso_total',
            'reparto' => 'reparto',
            'cod_ruta' => 'cod_ruta',
            'cod_transportista' => 'cod_transportista',
            'bulbos' => 'bulbos',
            'dto_pronto_pago' => 'dto_pronto_pago',
            'cargo_financiacion' => 'cargo_financiacion',
            'importe_financiacion' => 'importe_financiacion',
            'historico' => 'historico',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_venta', 'tipo_venta', 'cod_empresa', 'cod_caja'];
    }
}
