<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpSale extends Model
{
    protected $table = 'erp_sales';

    protected $fillable = [
        'cod_venta',
        'tipo_venta',
        'cod_empresa',
        'cod_caja',
        'cod_almacen',
        'cod_cliente',
        'nombre_comercial',
        'razon_social',
        'cif',
        'direccion1',
        'cp',
        'poblacion',
        'provincia',
        'cod_pais',
        'cod_divisa',
        'impuestos_incluidos',
        'cambio',
        'fecha_venta',
        'hora_venta',
        'cod_forma_liquidacion',
        'cod_vendedor',
        'nombre_vendedor',
        'su_pedido',
        'importe_cobrado',
        'importe_pendiente',
        'facturado',
        'importe',
        'importe_impuestos',
        'estado_venta',
        'anulada',
        'cod_factura_asignada',
        'fecha_entrega',
        'cod_arqueo',
        'peso_total',
        'reparto',
        'cod_ruta',
        'cod_transportista',
        'bulbos',
        'dto_pronto_pago',
        'cargo_financiacion',
        'importe_financiacion',
        'historico',
        'rowguid',
    ];

    protected $casts = [
        'cambio' => 'decimal:6',
        'fecha_venta' => 'datetime',
        'importe_cobrado' => 'decimal:6',
        'importe_pendiente' => 'decimal:6',
        'importe' => 'decimal:6',
        'importe_impuestos' => 'decimal:6',
        'peso_total' => 'decimal:6',
        'dto_pronto_pago' => 'decimal:3',
        'cargo_financiacion' => 'decimal:3',
        'importe_financiacion' => 'decimal:6',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ErpClient::class, 'cod_cliente', 'cod_cliente');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(ErpSeller::class, 'cod_vendedor', 'cod_vendedor');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ErpWarehouse::class, 'cod_almacen', 'cod_almacen');
    }

    public function saleLines(): HasMany
    {
        return $this->hasMany(ErpSaleLine::class, 'cod_venta', 'cod_venta');
    }
}
