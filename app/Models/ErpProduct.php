<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpProduct extends Model
{
    protected $table = 'erp_products';

    protected $fillable = [
        'cod_articulo',
        'marca',
        'cod_barras',
        'cod_familia',
        'cod_subfamilia',
        'cod_impuesto',
        'unidades_venta',
        'cod_unidad',
        'cod_proveedor_activo',
        'fecha_alta',
        'fecha_baja',
        'precio_coste',
        'incremento',
        'incremento_minimo',
        'cargo',
        'precio_venta_base',
        'precio_venta_publico',
        'precio_medio_ponderado',
        'descuento_maximo',
        'cod_gama',
        'peso_web',
        'alto_web',
        'ancho_web',
        'profundo_web',
        'tipo_articulo',
        'advertencia',
        'observaciones',
        'imagen',
        'rowguid',
    ];

    protected $casts = [
        'unidades_venta' => 'decimal:6',
        'precio_coste' => 'decimal:6',
        'incremento' => 'decimal:3',
        'incremento_minimo' => 'decimal:3',
        'cargo' => 'decimal:3',
        'precio_venta_base' => 'decimal:6',
        'precio_venta_publico' => 'decimal:6',
        'precio_medio_ponderado' => 'decimal:6',
        'descuento_maximo' => 'decimal:3',
        'peso_web' => 'decimal:6',
        'alto_web' => 'decimal:6',
        'ancho_web' => 'decimal:6',
        'profundo_web' => 'decimal:6',
        'fecha_alta' => 'datetime',
        'fecha_baja' => 'datetime',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(ErpFamily::class, 'cod_familia', 'cod_familia');
    }

    public function subfamily(): BelongsTo
    {
        return $this->belongsTo(ErpSubfamily::class, 'cod_subfamilia', 'cod_subfamilia');
    }

    public function saleLines(): HasMany
    {
        return $this->hasMany(ErpSaleLine::class, 'cod_articulo', 'cod_articulo');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ErpStock::class, 'cod_articulo', 'cod_articulo');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ErpStockMovement::class, 'cod_articulo', 'cod_articulo');
    }
}
