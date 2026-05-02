<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpStockMovement extends Model
{
    protected $table = 'erp_stock_movements';

    protected $fillable = [
        'cod_articulo',
        'cod_empresa',
        'cod_documento',
        'tipo_documento',
        'cod_caja',
        'operacion',
        'cod_cliente',
        'cod_proveedor',
        'fecha',
        'hora',
        'cod_almacen',
        'cantidad',
        'linea',
        'cod_movimiento',
        'linea_kit',
        'rowguid',
    ];

    protected $casts = [
        'cantidad' => 'decimal:6',
        'fecha' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ErpProduct::class, 'cod_articulo', 'cod_articulo');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ErpWarehouse::class, 'cod_almacen', 'cod_almacen');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ErpClient::class, 'cod_cliente', 'cod_cliente');
    }
}
