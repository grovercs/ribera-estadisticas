<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpWarehouse extends Model
{
    protected $table = 'erp_warehouses';

    protected $fillable = [
        'cod_almacen',
        'nombre',
        'direccion1',
        'direccion2',
        'cp',
        'poblacion',
        'provincia',
        'cod_pais',
        'telefono',
        'fax',
        'email',
        'persona_contacto',
        'fecha_ultimo_inventario',
        'rowguid',
    ];

    protected $casts = [
        'fecha_ultimo_inventario' => 'datetime',
    ];

    public function stocks(): HasMany
    {
        return $this->hasMany(ErpStock::class, 'cod_almacen', 'cod_almacen');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ErpStockMovement::class, 'cod_almacen', 'cod_almacen');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ErpSale::class, 'cod_almacen', 'cod_almacen');
    }
}
