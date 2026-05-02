<?php

namespace App\Console\Commands;

use App\Models\ErpInvoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-invoices')]
#[Description('Importa facturas desde el ERP')]
class ImportErpInvoices extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpInvoice::class;
    }

    protected function getErpTable(): string
    {
        return 'facturas_ventas_cabecera';
    }

    protected function getMapping(): array
    {
        return [
            'cod_factura' => 'cod_factura',
            'tipo_factura' => 'tipo_factura',
            'cod_empresa' => 'cod_empresa',
            'cod_caja' => 'cod_caja',
            'cod_cliente' => 'cod_cliente',
            'cod_seccion' => 'cod_seccion',
            'nombre_seccion' => 'nombre_seccion',
            'cif' => 'cif',
            'razon_social' => 'razon_social',
            'nombre_comercial' => 'nombre_comercial',
            'direccion1' => 'direccion1',
            'direccion2' => 'direccion2',
            'cp' => 'cp',
            'poblacion' => 'poblacion',
            'provincia' => 'provincia',
            'cod_idioma' => 'cod_idioma',
            'cod_pais' => 'cod_pais',
            'cod_divisa' => 'cod_divisa',
            'cambio' => 'cambio',
            'impuestos_incluidos' => 'impuestos_incluidos',
            'importe' => 'importe',
            'importe_impuestos' => 'importe_impuestos',
            'importe_divisa' => 'importe_divisa',
            'importe_divisa_impuestos' => 'importe_divisa_impuestos',
            'importe_cobrado' => 'importe_cobrado',
            'importe_divisa_cobrado' => 'importe_divisa_cobrado',
            'factura_oficial' => 'factura_oficial',
            'cod_almacen' => 'cod_almacen',
            'cargo_financiacion' => 'cargo_financiacion',
            'importe_financiacion' => 'importe_financiacion',
            'importe_divisa_financiacion' => 'importe_divisa_financiacion',
            'tipo_factura_rectificada' => 'tipo_factura_rectificada',
            'cod_factura_rectificada' => 'cod_factura_rectificada',
            'fecha_factura_rectificada' => 'fecha_factura_rectificada',
            'retencion_irpf' => 'retencion_irpf',
            'importe_retencion_irpf' => 'importe_retencion_irpf',
            'importe_divisa_retencion_irpf' => 'importe_divisa_retencion_irpf',
            'cod_arqueo' => 'cod_arqueo',
            'cod_primera_venta' => 'cod_primera_venta',
            'cod_ultima_venta' => 'cod_ultima_venta',
            'tipo_operacion_iva' => 'tipo_operacion_iva',
            'cod_proveedor_otorgado' => 'cod_proveedor_otorgado',
            'referencia_contrato_facturae' => 'referencia_contrato_facturae',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_factura', 'tipo_factura', 'cod_empresa', 'cod_caja'];
    }
}
