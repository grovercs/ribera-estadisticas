<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErpStock extends Model
{
    protected $table = 'erp_stocks';

    protected $fillable = [
        'cod_almacen',
        'cod_articulo',
        'existencias',
        'fecha_ultima_entrada',
        'fecha_ultima_salida',
        'maximos',
        'minimos',
        'ubicacion',
        'fecha_ultimo_inventario',
        'hora_ultimo_inventario',
        'permitir_venta_bajo_stock',
        'cantidad_pendiente_servir',
        'cantidad_pendiente_recibir',
        'rowguid',
    ];

    protected $casts = [
        'existencias' => 'decimal:6',
        'maximos' => 'decimal:6',
        'minimos' => 'decimal:6',
        'cantidad_pendiente_servir' => 'decimal:6',
        'cantidad_pendiente_recibir' => 'decimal:6',
        'fecha_ultima_entrada' => 'datetime',
        'fecha_ultima_salida' => 'datetime',
        'fecha_ultimo_inventario' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ErpWarehouse::class, 'cod_almacen', 'cod_almacen');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ErpProduct::class, 'cod_articulo', 'cod_articulo');
    }
}
