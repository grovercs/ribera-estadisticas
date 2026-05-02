<?php

namespace App\Console\Commands;

use App\Models\ErpSubfamily;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-subfamilies')]
#[Description('Importa subfamilias desde el ERP')]
class ImportErpSubfamilies extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpSubfamily::class;
    }

    protected function getErpTable(): string
    {
        return 'subfamilias';
    }

    protected function getMapping(): array
    {
        return [
            'cod_familia' => 'cod_familia',
            'cod_subfamilia' => 'cod_subfamilia',
            'descripcion' => 'descripcion',
            'incremento' => 'incremento',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_familia', 'cod_subfamilia'];
    }
}
