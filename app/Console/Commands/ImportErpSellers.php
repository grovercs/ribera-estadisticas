<?php

namespace App\Console\Commands;

use App\Models\ErpSeller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-sellers')]
#[Description('Importa vendedores desde el ERP')]
class ImportErpSellers extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpSeller::class;
    }

    protected function getErpTable(): string
    {
        return 'vendedores';
    }

    protected function getMapping(): array
    {
        return [
            'cod_vendedor' => 'cod_vendedor',
            'nombre' => 'nombre',
            'direccion1' => 'direccion1',
            'cp' => 'cp',
            'poblacion' => 'poblacion',
            'provincia' => 'provincia',
            'telefono' => 'telefono',
            'fax' => 'fax',
            'e_mail' => 'e_mail',
            'seguridad_social' => 'seguridad_social',
            'mutua' => 'mutua',
            'comision_general' => 'comision_general',
            'aniversario' => 'aniversario',
            'observaciones' => 'observaciones',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_vendedor'];
    }
}
