<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            "SELECT 1 as exists_flag FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $index]
        ) !== null;
    }

    public function up(): void
    {
        // erp_stocks: agregaciones por artículo (alertas de stock, rotación)
        if (! $this->indexExists('erp_stocks', 'erp_stocks_cod_articulo_existencias_index')) {
            DB::statement('ALTER TABLE erp_stocks ADD INDEX erp_stocks_cod_articulo_existencias_index (cod_articulo, existencias)');
        }

        // erp_sales: joins por cliente y rango de fecha (clientes dormidos, ventas por mes)
        if (! $this->indexExists('erp_sales', 'erp_sales_cod_cliente_fecha_venta_index')) {
            DB::statement('ALTER TABLE erp_sales ADD INDEX erp_sales_cod_cliente_fecha_venta_index (cod_cliente, fecha_venta)');
        }
        if (! $this->indexExists('erp_sales', 'erp_sales_fecha_venta_importe_impuestos_index')) {
            DB::statement('ALTER TABLE erp_sales ADD INDEX erp_sales_fecha_venta_importe_impuestos_index (fecha_venta, importe_impuestos)');
        }

        // erp_sale_lines: agregaciones por artículo y fecha (rotación baja, top productos)
        if (! $this->indexExists('erp_sale_lines', 'erp_sale_lines_cod_articulo_created_at_cantidad_index')) {
            DB::statement('ALTER TABLE erp_sale_lines ADD INDEX erp_sale_lines_cod_articulo_created_at_cantidad_index (cod_articulo, created_at, cantidad)');
        }

        // erp_clients: búsquedas y filtros del listado
        if (! $this->indexExists('erp_clients', 'erp_clients_poblacion_provincia_index')) {
            DB::statement('ALTER TABLE erp_clients ADD INDEX erp_clients_poblacion_provincia_index (poblacion, provincia)');
        }

        // erp_products: búsquedas y filtros del catálogo
        if (! $this->indexExists('erp_products', 'erp_products_cod_familia_cod_subfamilia_index')) {
            DB::statement('ALTER TABLE erp_products ADD INDEX erp_products_cod_familia_cod_subfamilia_index (cod_familia, cod_subfamilia)');
        }
        if (! $this->indexExists('erp_products', 'erp_products_marca_index')) {
            DB::statement('ALTER TABLE erp_products ADD INDEX erp_products_marca_index (marca)');
        }
    }

    public function down(): void
    {
        if ($this->indexExists('erp_stocks', 'erp_stocks_cod_articulo_existencias_index')) {
            DB::statement('ALTER TABLE erp_stocks DROP INDEX erp_stocks_cod_articulo_existencias_index');
        }

        if ($this->indexExists('erp_sales', 'erp_sales_cod_cliente_fecha_venta_index')) {
            DB::statement('ALTER TABLE erp_sales DROP INDEX erp_sales_cod_cliente_fecha_venta_index');
        }
        if ($this->indexExists('erp_sales', 'erp_sales_fecha_venta_importe_impuestos_index')) {
            DB::statement('ALTER TABLE erp_sales DROP INDEX erp_sales_fecha_venta_importe_impuestos_index');
        }

        if ($this->indexExists('erp_sale_lines', 'erp_sale_lines_cod_articulo_created_at_cantidad_index')) {
            DB::statement('ALTER TABLE erp_sale_lines DROP INDEX erp_sale_lines_cod_articulo_created_at_cantidad_index');
        }

        if ($this->indexExists('erp_clients', 'erp_clients_poblacion_provincia_index')) {
            DB::statement('ALTER TABLE erp_clients DROP INDEX erp_clients_poblacion_provincia_index');
        }

        if ($this->indexExists('erp_products', 'erp_products_cod_familia_cod_subfamilia_index')) {
            DB::statement('ALTER TABLE erp_products DROP INDEX erp_products_cod_familia_cod_subfamilia_index');
        }
        if ($this->indexExists('erp_products', 'erp_products_marca_index')) {
            DB::statement('ALTER TABLE erp_products DROP INDEX erp_products_marca_index');
        }
    }
};
