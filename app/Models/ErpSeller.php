<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpSeller extends Model
{
    protected $table = 'erp_sellers';

    protected $fillable = [
        'cod_vendedor',
        'nombre',
        'direccion1',
        'cp',
        'poblacion',
        'provincia',
        'telefono',
        'fax',
        'e_mail',
        'seguridad_social',
        'mutua',
        'comision_general',
        'aniversario',
        'observaciones',
        'rowguid',
    ];

    protected $casts = [
        'comision_general' => 'decimal:6',
        'aniversario' => 'datetime',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(ErpClient::class, 'cod_vendedor', 'cod_vendedor');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ErpSale::class, 'cod_vendedor', 'cod_vendedor');
    }
}
