<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'tax_id',
        'address', 'city', 'type', 'credit_limit',
        'total_spent', 'order_count', 'last_order_date', 'status'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'last_order_date' => 'date',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
