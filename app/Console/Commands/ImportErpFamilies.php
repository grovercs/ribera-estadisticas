<?php

namespace App\Console\Commands;

use App\Models\ErpFamily;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-families')]
#[Description('Importa familias desde el ERP')]
class ImportErpFamilies extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpFamily::class;
    }

    protected function getErpTable(): string
    {
        return 'familias';
    }

    protected function getMapping(): array
    {
        return [
            'cod_familia' => 'cod_familia',
            'descripcion' => 'descripcion',
            'codigo_contable_compras' => 'codigo_contable_compras',
            'codigo_contable_ventas' => 'codigo_contable_ventas',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_familia'];
    }
}
