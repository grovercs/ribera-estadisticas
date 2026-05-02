<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpClient extends Model
{
    protected $table = 'erp_clients';

    protected $fillable = [
        'cod_cliente',
        'nombre_comercial',
        'razon_social',
        'cif',
        'direccion1',
        'direccion2',
        'cp',
        'poblacion',
        'provincia',
        'cod_pais',
        'cod_idioma',
        'cod_divisa',
        'cod_tipo_cliente',
        'telefono',
        'fax',
        'e_mail',
        'cod_forma_liquidacion',
        'dia_pago1',
        'dia_pago2',
        'dia_pago3',
        'limite_credito',
        'moroso',
        'cod_banco',
        'direccion_banco1',
        'cp_banco',
        'poblacion_banco',
        'ccc',
        'iban',
        'swift',
        'dto_pronto_pago',
        'plazo_entrega',
        'cod_vendedor',
        'comision',
        'fecha_alta',
        'fecha_baja',
        'cod_zona',
        'cod_estado_cliente',
        'cod_ruta',
        'tipo_operacion_iva',
        'ventas_credito_contado',
        'iva_incluido_ventas',
        'latitud',
        'longitud',
        'rowguid',
    ];

    protected $casts = [
        'limite_credito' => 'decimal:6',
        'dto_pronto_pago' => 'decimal:3',
        'comision' => 'decimal:6',
        'dia_pago1' => 'integer',
        'dia_pago2' => 'integer',
        'dia_pago3' => 'integer',
        'fecha_alta' => 'datetime',
        'fecha_baja' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(ErpSeller::class, 'cod_vendedor', 'cod_vendedor');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ErpSale::class, 'cod_cliente', 'cod_cliente');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ErpStockMovement::class, 'cod_cliente', 'cod_cliente');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ErpInvoice::class, 'cod_cliente', 'cod_cliente');
    }
}
