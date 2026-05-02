<?php

namespace App\Console\Commands;

use App\Models\ErpClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('app:import-erp-clients')]
#[Description('Importa clientes desde el ERP')]
class ImportErpClients extends ImportErpCommand
{
    protected function getModelClass(): string
    {
        return ErpClient::class;
    }

    protected function getErpTable(): string
    {
        return 'clientes';
    }

    protected function getMapping(): array
    {
        return [
            'cod_cliente' => 'cod_cliente',
            'nombre_comercial' => 'nombre_comercial',
            'razon_social' => 'razon_social',
            'cif' => 'cif',
            'direccion1' => 'direccion1',
            'direccion2' => 'direccion2',
            'CP' => 'cp',
            'poblacion' => 'poblacion',
            'provincia' => 'provincia',
            'cod_pais' => 'cod_pais',
            'cod_idioma' => 'cod_idioma',
            'cod_divisa' => 'cod_divisa',
            'cod_tipo_cliente' => 'cod_tipo_cliente',
            'telefono' => 'telefono',
            'fax' => 'fax',
            'e_mail' => 'e_mail',
            'cod_forma_liquidacion' => 'cod_forma_liquidacion',
            'dia_pago1' => 'dia_pago1',
            'dia_pago2' => 'dia_pago2',
            'dia_pago3' => 'dia_pago3',
            'limite_credito' => 'limite_credito',
            'moroso' => 'moroso',
            'cod_banco' => 'cod_banco',
            'direccion_banco1' => 'direccion_banco1',
            'cp_banco' => 'cp_banco',
            'poblacion_banco' => 'poblacion_banco',
            'ccc' => 'ccc',
            'iban' => 'iban',
            'swift' => 'swift',
            'dto_pronto_pago' => 'dto_pronto_pago',
            'plazo_entrega' => 'plazo_entrega',
            'cod_vendedor' => 'cod_vendedor',
            'comision' => 'comision',
            'fecha_alta' => 'fecha_alta',
            'fecha_baja' => 'fecha_baja',
            'cod_zona' => 'cod_zona',
            'cod_estado_cliente' => 'cod_estado_cliente',
            'cod_ruta' => 'cod_ruta',
            'tipo_operacion_iva' => 'tipo_operacion_iva',
            'ventas_credito_contado' => 'ventas_credito_contado',
            'iva_incluido_ventas' => 'iva_incluido_ventas',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'rowguid' => 'rowguid',
        ];
    }

    protected function getUniqueBy(): array
    {
        return ['cod_cliente'];
    }
}
