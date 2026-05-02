<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpSubfamily extends Model
{
    protected $table = 'erp_subfamilies';

    protected $fillable = [
        'cod_familia',
        'cod_subfamilia',
        'descripcion',
        'incremento',
        'rowguid',
    ];

    protected $casts = [
        'incremento' => 'decimal:3',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(ErpFamily::class, 'cod_familia', 'cod_familia');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ErpProduct::class, 'cod_subfamilia', 'cod_subfamilia');
    }
}
