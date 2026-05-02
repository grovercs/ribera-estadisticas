<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpInvoice extends Model
{
    protected $table = 'erp_invoices';

    protected $fillable = [
        'cod_factura',
        'tipo_factura',
        'cod_empresa',
        'cod_caja',
        'cod_cliente',
        'cod_seccion',
        'nombre_seccion',
        'cif',
        'razon_social',
        'nombre_comercial',
        'direccion1',
        'direccion2',
        'cp',
        'poblacion',
        'provincia',
        'cod_idioma',
        'cod_pais',
        'cod_divisa',
        'cambio',
        'impuestos_incluidos',
        'importe',
        'importe_impuestos',
        'importe_divisa',
        'importe_divisa_impuestos',
        'importe_cobrado',
        'importe_divisa_cobrado',
        'factura_oficial',
        'cod_almacen',
        'cargo_financiacion',
        'importe_financiacion',
        'importe_divisa_financiacion',
        'tipo_factura_rectificada',
        'cod_factura_rectificada',
        'fecha_factura_rectificada',
        'retencion_irpf',
        'importe_retencion_irpf',
        'importe_divisa_retencion_irpf',
        'cod_arqueo',
        'cod_primera_venta',
        'cod_ultima_venta',
        'tipo_operacion_iva',
        'cod_proveedor_otorgado',
        'referencia_contrato_facturae',
        'rowguid',
    ];

    protected $casts = [
        'cambio' => 'decimal:6',
        'importe' => 'decimal:6',
        'importe_impuestos' => 'decimal:6',
        'importe_divisa' => 'decimal:6',
        'importe_divisa_impuestos' => 'decimal:6',
        'importe_cobrado' => 'decimal:6',
        'importe_divisa_cobrado' => 'decimal:6',
        'cargo_financiacion' => 'decimal:3',
        'importe_financiacion' => 'decimal:6',
        'importe_divisa_financiacion' => 'decimal:6',
        'retencion_irpf' => 'decimal:3',
        'importe_retencion_irpf' => 'decimal:6',
        'importe_divisa_retencion_irpf' => 'decimal:6',
        'fecha_factura_rectificada' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ErpClient::class, 'cod_cliente', 'cod_cliente');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ErpWarehouse::class, 'cod_almacen', 'cod_almacen');
    }
}
