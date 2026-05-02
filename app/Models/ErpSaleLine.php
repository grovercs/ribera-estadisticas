<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpSaleLine extends Model
{
    protected $table = 'erp_sale_lines';

    protected $fillable = [
        'cod_venta',
        'tipo_venta',
        'cod_empresa',
        'cod_caja',
        'linea',
        'cod_articulo',
        'descripcion',
        'cantidad',
        'dto1',
        'dto2',
        'precio',
        'precio_coste',
        'cargo',
        'importe',
        'importe_impuestos',
        'cod_impuesto',
        'porcentaje',
        'inventariable',
        'cod_unidad',
        'kit',
        'unidades_venta',
        'precio_venta_base',
        'precio_venta_publico',
        'estado_venta',
        'descuento_maximo',
        'cod_barras',
        'cod_almacen',
        'fecha_entrega',
        'observacion',
        'peso_unitario',
        'peso_total',
        'cod_tarifa',
        'version',
        'rowguid',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'dto1' => 'decimal:3',
        'dto2' => 'decimal:3',
        'precio' => 'decimal:6',
        'precio_coste' => 'decimal:6',
        'cargo' => 'decimal:3',
        'importe' => 'decimal:6',
        'importe_impuestos' => 'decimal:6',
        'porcentaje' => 'decimal:3',
        'unidades_venta' => 'decimal:6',
        'precio_venta_base' => 'decimal:6',
        'precio_venta_publico' => 'decimal:6',
        'descuento_maximo' => 'decimal:3',
        'peso_unitario' => 'decimal:6',
        'peso_total' => 'decimal:6',
        'fecha_entrega' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ErpSale::class, 'cod_venta', 'cod_venta');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ErpProduct::class, 'cod_articulo', 'cod_articulo');
    }
}
