<?php

namespace App\Console\Commands;

use App\Models\ErpWarehouse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-warehouses')]
#[Description('Importa almacenes desde el ERP')]
class ImportErpWarehouses extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpWarehouse::class;
    }

    protected function getErpTable(): string
    {
        return 'almacenes';
    }

    protected function getMapping(): array
    {
        return [
            'cod_almacen' => 'cod_almacen',
            'nombre' => 'nombre',
            'direccion1' => 'direccion1',
            'direccion2' => 'direccion2',
            'cp' => 'cp',
            'poblacion' => 'poblacion',
            'provincia' => 'provincia',
            'cod_pais' => 'cod_pais',
            'telefono' => 'telefono',
            'fax' => 'fax',
            'email' => 'email',
            'persona_contacto' => 'persona_contacto',
            'fecha_ultimo_inventario' => 'fecha_ultimo_inventario',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_almacen'];
    }
}
