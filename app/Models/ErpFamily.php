<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpFamily extends Model
{
    protected $table = 'erp_families';

    protected $fillable = [
        'cod_familia',
        'descripcion',
        'codigo_contable_compras',
        'codigo_contable_ventas',
        'rowguid',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(ErpProduct::class, 'cod_familia', 'cod_familia');
    }

    public function subfamilies(): HasMany
    {
        return $this->hasMany(ErpSubfamily::class, 'cod_familia', 'cod_familia');
    }
}
