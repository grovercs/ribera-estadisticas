# Plan de Implementación (Versión Final): Publicación del Panel de Estadísticas en Netlify + Supabase

Este documento detalla la auditoría de la aplicación actual y define la estrategia técnica y el plan de migración para convertir la aplicación en una solución web serverless de solo lectura en Next.js alojada en Netlify, utilizando Supabase (PostgreSQL + Auth + RLS) como base de datos de reporting.

---

## A. Estado Actual (Auditoría Técnica)

### 1. Arquitectura Encontrada y Versiones
*   **Backend actual**: Laravel 13.7.0 corriendo en PHP 8.4.24 (bajo WampServer local, Apache/2.4.51 en el puerto `8080`).
*   **Base de datos ERP**: SQL Server (`INTEGRAL`) alojada en la red local (`192.168.1.215` / `192.168.200.105`). La conexión se realiza a través de `pdo_sqlsrv`.
*   **Base de datos Local**: MySQL 8 (`ribera_estadisticas` en puerto `3308`) configurada como réplica/espejo mediante migraciones locales, actualmente con **0 registros** en todas sus tablas.
*   **Frontend actual**: Laravel Blade templates, Tailwind CSS y gráficos con Chart.js.
*   **Dependencias principales** (`composer.json`):
    *   `laravel/framework`: `^13.0`
    *   `maatwebsite/excel`: `^3.1` (para importaciones Excel y exportaciones de informes)
    *   `markrogoyski/math-php`: `^2.13` (cálculos estadísticos y matemáticos)

### 2. Configuración y Conexiones (.env y .env.local)
Las variables de entorno que controlan la conexión al ERP y la URL local son:
*   `APP_URL=http://localhost:8080/ribera-estadisticas/public/index.php`
*   `ERP_DB_HOST=192.168.1.215\\INTEGRAL`
*   `ERP_DB_DATABASE=INTEGRAL`
*   `ERP_DB_USERNAME=vc`
*   `ERP_DB_PASSWORD=********`
*   `ERP_DB_ENCRYPT=no`
*   `ERP_DB_TRUST_SERVER_CERTIFICATE=true`

### 3. Resolución de la Inconsistencia de MySQL Local
Se ha auditado por qué las pantallas de **Clientes** y **Proveedores** funcionan en local si la base de datos MySQL local tiene 0 registros en todas sus tablas:
*   Las pantallas funcionan sin arrojar errores de base de datos debido a que las migraciones del sistema (`database/migrations`) han creado la estructura física de las tablas y las columnas (ej. `erp_clients`, `erp_suppliers`) en la base de datos MySQL local `ribera_estadisticas`.
*   Al estar las tablas vacías, Laravel simplemente devuelve colecciones vacías (devolviendo 0 filas en la interfaz) pero sin fallos de ejecución.
*   **Fuente Real de Verdad**: Los comandos de importación local de Laravel (`app:import-erp-*`) no se han ejecutado todavía en este entorno de desarrollo local. Estos comandos leen de las tablas nativas de SQL Server (`clientes` y `proveedores` del ERP) y realizan el volcado local. En consecuencia, la fuente real de verdad es SQL Server, y el sincronizador local debe alimentarse directamente de este.

### 4. Volumen Real de Ventas en el ERP (SQL Server)
Se han extraído de forma directa los conteos reales de registros por año de la base de datos SQL Server del ERP:

| Año | Cabeceras (`hist_ventas_cabecera`) | Líneas (`hist_ventas_linea`) |
| :--- | :--- | :--- |
| **2026 (Parcial)** | 55,749 | 157,526 |
| **2025** | 94,212 | 266,647 |
| **2024** | 91,140 | 257,334 |
| **2023** | 92,250 | 258,783 |
| **2022** | 93,534 | 261,627 |
| **2021** | 93,278 | 258,809 |
| **2020** | 87,414 | 246,696 |
| **2019** | 100,760 | 287,766 |
| **2018** | 100,330 | 285,709 |
| **2017** | 93,537 | 259,252 |
| **2016** | 96,447 | 261,389 |
| **2015** | 88,080 | 227,392 |
| **2014** | 84,521 | 216,360 |
| **2013** | 83,298 | 213,205 |
| **2012** | 85,321 | 215,305 |

*   **Total histórico acumulado (15 años)**: ~1,370,000 cabeceras y ~3,800,000 líneas.
*   **Crecimiento anual**: ~92,000 cabeceras y ~260,000 líneas por año.
*   **Decisión de histórico para Supabase**: 
    Para garantizar que la base de datos en Supabase permanezca dentro de los límites del nivel gratuito (500 MB) y optimizar la velocidad del panel en Netlify, sincronizaremos las transacciones detalladas de **ventas y líneas únicamente del Año Actual + Año Anterior** (~150,000 cabeceras y ~420,000 líneas en total). Para los años previos (2012-2024), se sincronizarán exclusivamente los **totales mensuales consolidados** en la tabla `stats_sales_monthly` (12 registros por año, 156 registros en total), manteniendo la paridad de los gráficos comparativos históricos.

---

## B. Estrategia de Sincronización y Control de Cambios

Para asegurar la robustez del sistema, se clasifican los conjuntos de datos en dos estrategias bien diferenciadas.

### 1. Clasificación por Estrategia de Carga

#### A. Datasets SNAPSHOT (Carga Completa y Reemplazo)
Se aplica a tablas de sumario, KPIs y catálogos dinámicos que se recalculan por completo. Para evitar que una caída de red o de proceso deje las tablas vacías o incompletas en Supabase, **el lote antiguo y el nuevo coexistirán en la base de datos**.

*   **Tablas clasificadas**:
    *   `stats_kpis`
    *   `stats_warehouses`
    *   `stats_sellers`
    *   `products_stock`
*   **Estrategia de Clave Primaria**: La columna `batch_id` se incorpora como parte de la **Clave Primaria Compuesta** en estas tablas, permitiendo que convivan múltiples lotes simultáneamente.
*   **Estrategia de Actualización**:
    1.  El sincronizador local genera un `batch_id` (UUIDv4) al iniciar la tarea.
    2.  Inserta los nuevos registros en Supabase bajo el nuevo `batch_id`.
    3.  Al finalizar de forma exitosa, realiza una **activación atómica** en Supabase.
*   **Activación y Limpieza Atómica**:
    La activación del nuevo lote y la limpieza de los antiguos se realiza mediante una transacción SQL atómica en Supabase:
    ```sql
    BEGIN;
      -- 1. Actualizar el estado de sincronización y apuntar al nuevo lote activo
      UPDATE sync_state 
      SET active_batch_id = 'NUEVO_UUID', last_success_at = NOW(), last_run_status = 'success'
      WHERE dataset = 'dataset_name';
      
      -- 2. Eliminar inmediatamente los registros del lote anterior
      DELETE FROM tabla_destino WHERE batch_id != 'NUEVO_UUID';
    COMMIT;
    ```
    *   **Beneficio**: El panel de Next.js lee de forma transparente los datos del lote activo. Nunca experimenta estados vacíos ni inconsistencias en tiempo de ejecución.

#### B. Datasets INCREMENTALES (Actualización por Modificación y UPSERT)
Se aplica a tablas transaccionales y directorios extensos para evitar duplicar millones de filas en cada ejecución.

*   **Tablas clasificadas**:
    *   `sales_headers`
    *   `sales_lines`
    *   `clients_reporting`
    *   `suppliers_reporting`
    *   `stats_sales_monthly`
*   **Estrategia de Clave Primaria**: Se utiliza la clave natural del ERP (sin `batch_id`).
*   **Estrategia de Actualización**:
    *   Se realiza un `UPSERT` directo basado en la clave única natural.
    *   Se utiliza el campo de auditoría **`fecha_modificacion (datetime)`** de SQL Server para consultar únicamente los registros modificados recientemente (`WHERE fecha_modificacion >= ultima_sync_exitosa - 5 minutos`).
    *   **Campos de auditoría incluidos**:
        *   `source_modified_at`: Fecha y hora de modificación del registro en el ERP (`fecha_modificacion` en SQL Server).
        *   `synced_at`: Marca de tiempo en la que se guardó en Supabase.
    *   **Ventana móvil**: Diariamente en la ejecución nocturna, se realiza un `UPSERT` de control sobre los registros de los últimos 30 días para asegurar que no se pierda ningún cambio retroactivo.

---

## C. Control e Historial de Sincronización

Separamos conceptualmente el historial de procesos de la tabla de estado actual para permitir ejecuciones independientes con distintas frecuencias.

### 1. Historial de Ejecuciones (`sync_runs`)
Almacena el log histórico de todas las sincronizaciones iniciadas y terminadas.
*   `id` (UUID, PK)
*   `dataset` (VARCHAR) (e.g., 'kpis', 'stock', 'sales')
*   `started_at` (TIMESTAMP)
*   `completed_at` (TIMESTAMP)
*   `status` (VARCHAR) ('running', 'success', 'failed')
*   `records_processed` (INT)
*   `error_message` (TEXT)

### 2. Estado de Sincronización actual (`sync_state`)
Mantiene el puntero al lote activo actual de cada dataset y la fecha de última actualización exitosa.
*   `dataset` (VARCHAR, PK) (e.g., 'kpis', 'warehouses', 'sellers', 'stock', 'sales', 'clients', 'suppliers', 'monthly_history')
*   `active_batch_id` (UUID, nullable) (Aplica a tablas SNAPSHOT; null para INCREMENTAL)
*   `last_success_at` (TIMESTAMP)
*   `last_run_status` (VARCHAR)
*   `last_error_message` (TEXT)

---

## D. Esquema SQL Completo (Supabase)

```sql
-- Historial de ejecuciones
CREATE TABLE sync_runs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    dataset VARCHAR(100) NOT NULL,
    started_at TIMESTAMP WITH TIME ZONE NOT NULL,
    completed_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) NOT NULL, -- 'running', 'success', 'failed'
    records_processed INT DEFAULT 0,
    error_message TEXT
);

-- Estado de sincronización actual por dataset
CREATE TABLE sync_state (
    dataset VARCHAR(100) PRIMARY KEY,
    active_batch_id UUID,
    last_success_at TIMESTAMP WITH TIME ZONE,
    last_run_status VARCHAR(50),
    last_error_message TEXT
);

-- === DATASETS SNAPSHOT ===

-- KPIs Consolidados
CREATE TABLE stats_kpis (
    period_key VARCHAR(50) NOT NULL, -- 'hoy', 'quincena', 'year_actual'
    batch_id UUID NOT NULL,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    total_orders INT DEFAULT 0,
    avg_ticket NUMERIC(15, 6) DEFAULT 0.0,
    pending_amount NUMERIC(15, 6) DEFAULT 0.0,
    unique_clients INT DEFAULT 0,
    total_cost NUMERIC(15, 6) DEFAULT 0.0,
    gross_profit NUMERIC(15, 6) DEFAULT 0.0,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (period_key, batch_id)
);

-- Ventas por Almacén
CREATE TABLE stats_warehouses (
    cod_almacen VARCHAR(50) NOT NULL,
    period_key VARCHAR(50) NOT NULL,
    batch_id UUID NOT NULL,
    orders_count INT DEFAULT 0,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    PRIMARY KEY (cod_almacen, period_key, batch_id)
);

-- Ventas por Vendedor
CREATE TABLE stats_sellers (
    cod_vendedor VARCHAR(50) NOT NULL,
    period_key VARCHAR(50) NOT NULL,
    batch_id UUID NOT NULL,
    nombre_vendedor VARCHAR(255),
    orders_count INT DEFAULT 0,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    PRIMARY KEY (cod_vendedor, period_key, batch_id)
);

-- Productos y Existencias
CREATE TABLE products_stock (
    cod_articulo VARCHAR(50) NOT NULL,
    batch_id UUID NOT NULL,
    descripcion VARCHAR(255),
    marca VARCHAR(100),
    cod_familia VARCHAR(50),
    cod_subfamilia VARCHAR(50),
    stock_total NUMERIC(15, 6) DEFAULT 0.0,
    stock_minimo NUMERIC(15, 6) DEFAULT 0.0,
    precio_coste NUMERIC(15, 6) DEFAULT 0.0,
    precio_venta NUMERIC(15, 6) DEFAULT 0.0,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (cod_articulo, batch_id)
);

-- === DATASETS INCREMENTALES ===

-- Histórico de Facturación Mensual (Ventas Consolidadas Años Anteriores)
CREATE TABLE stats_sales_monthly (
    year INT NOT NULL,
    month INT NOT NULL,
    revenue NUMERIC(15, 6) DEFAULT 0.0,
    total_cost NUMERIC(15, 6) DEFAULT 0.0,
    gross_profit NUMERIC(15, 6) DEFAULT 0.0,
    orders_count INT DEFAULT 0,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (year, month)
);

-- Cabeceras de Venta (Detalle del Año Actual + Año Anterior)
CREATE TABLE sales_headers (
    cod_venta VARCHAR(50) NOT NULL,
    tipo_venta INT NOT NULL,
    cod_empresa VARCHAR(50) NOT NULL,
    cod_caja VARCHAR(50) NOT NULL,
    cod_almacen VARCHAR(50),
    cod_cliente VARCHAR(50),
    razon_social VARCHAR(255),
    fecha_venta TIMESTAMP WITH TIME ZONE NOT NULL,
    cod_forma_liquidacion VARCHAR(50),
    cod_vendedor VARCHAR(50),
    nombre_vendedor VARCHAR(255),
    total_amount NUMERIC(15, 6) DEFAULT 0.0,
    pending_amount NUMERIC(15, 6) DEFAULT 0.0,
    anulada BOOLEAN DEFAULT FALSE,
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (cod_venta, tipo_venta, cod_empresa, cod_caja)
);

-- Líneas de Venta (Detalle del Año Actual + Año Anterior)
CREATE TABLE sales_lines (
    cod_venta VARCHAR(50) NOT NULL,
    tipo_venta INT NOT NULL,
    cod_empresa VARCHAR(50) NOT NULL,
    cod_caja VARCHAR(50) NOT NULL,
    linea INT NOT NULL,
    cod_articulo VARCHAR(50),
    descripcion VARCHAR(255),
    cantidad NUMERIC(15, 6) DEFAULT 0.0,
    precio NUMERIC(15, 6) DEFAULT 0.0,
    precio_coste NUMERIC(15, 6) DEFAULT 0.0,
    total_amount NUMERIC(15, 6) DEFAULT 0.0,
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (cod_venta, tipo_venta, cod_empresa, cod_caja, linea)
);

-- Directorio de Clientes
CREATE TABLE clients_reporting (
    cod_cliente VARCHAR(50) PRIMARY KEY,
    razon_social VARCHAR(255),
    cif VARCHAR(50),
    poblacion VARCHAR(100),
    provincia VARCHAR(100),
    telefono VARCHAR(50),
    e_mail VARCHAR(255),
    limite_credito NUMERIC(15, 6) DEFAULT 0.0,
    cod_vendedor VARCHAR(50),
    total_spent NUMERIC(15, 6) DEFAULT 0.0,
    order_count INT DEFAULT 0,
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Directorio de Proveedores
CREATE TABLE suppliers_reporting (
    cod_proveedor VARCHAR(50) PRIMARY KEY,
    razon_social VARCHAR(255),
    cif VARCHAR(50),
    poblacion VARCHAR(100),
    provincia VARCHAR(100),
    telefono VARCHAR(50),
    e_mail VARCHAR(255),
    credito_otorgado NUMERIC(15, 6) DEFAULT 0.0,
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

---

## E. Índices Necesarios para el Frontend

Para asegurar la fluidez inmediata del panel web (Next.js) al consumir los endpoints de la API de Supabase, se configurarán los siguientes índices optimizados:

```sql
-- Búsquedas y filtros en la pantalla de Ventas
CREATE INDEX idx_sales_headers_fecha ON sales_headers(fecha_venta DESC);
CREATE INDEX idx_sales_headers_cliente ON sales_headers(cod_cliente);
CREATE INDEX idx_sales_headers_vendedor ON sales_headers(cod_vendedor);
CREATE INDEX idx_sales_headers_pendiente ON sales_headers(importe_pendiente) WHERE importe_pendiente > 0;

-- Carga rápida de líneas asociadas a un documento (modal)
CREATE INDEX idx_sales_lines_documento ON sales_lines(cod_venta, tipo_venta, cod_empresa, cod_caja);

-- Filtros rápidos en la pantalla de Inventario
CREATE INDEX idx_products_stock_minimo ON products_stock(stock_total, stock_minimo) WHERE stock_total < stock_minimo;
CREATE INDEX idx_products_stock_categoria ON products_stock(cod_familia, cod_subfamilia);

-- Listados ordenados de Clientes y Proveedores
CREATE INDEX idx_clients_reporting_spent ON clients_reporting(total_spent DESC);
CREATE INDEX idx_suppliers_reporting_name ON suppliers_reporting(razon_social);
```

---

## F. Frontend Next.js (Netlify)

*   **Rutas**: `/login`, `/dashboard`, `/sales` (listado, líneas y exportación CSV), `/stock`, `/clients`, `/suppliers`, `/financial`, `/store-dashboard`.
*   **Gráficos**: Se utilizará **Chart.js** (wrapper `react-chartjs-2`) para clonar con precisión la estética y visualización de la versión local actual.
*   **Gestión de Estados**: Componentes Skeleton para la fase de loading. En caso de fallas de sincronización (último lote de hace >2 horas), se mostrará una barra de advertencia discreta manteniendo el dashboard navegable con los datos almacenados.

---

## G. Seguridad (Supabase Auth & RLS)

*   **Autenticación**: Obligatoria mediante Supabase Auth. Los usuarios se darán de alta directamente en el panel de Supabase.
*   **Políticas RLS**:
    *   Habilitado en todas las tablas.
    *   `SELECT`: Permitido únicamente para usuarios autenticados (`auth.role() = 'authenticated'`).
    *   `ALL` (Escritura/Modificación): Denegado para todos los usuarios. Solo la cuenta de servicio (`service_role`) utilizada de forma local y segura por el Sincronizador podrá escribir datos.

---

## H. Plan de Implementación por Fases

*   **FASE 0 — Auditoría e Informe**: Análisis de datos, volúmenes de ERP, campos de auditoría de fechas de modificación y resolución de inconsistencias locales. (En curso / Finalizado conceptualmente).
*   **FASE 1 — Supabase**: Creación de proyecto en Supabase, definición de tablas PostgreSQL, e instalación de políticas RLS y configuración de Supabase Auth.
*   **FASE 2 — Sincronizador**: Desarrollo del comando Artisan `ribera:sync-to-supabase` en local utilizando la estrategia de Batch ID y actualizaciones incrementales mediante `fecha_modificacion`.
*   **FASE 3 — Paridad de Datos**: Cargas iniciales completas, automatización e igualación de consultas e importes entre SQL Server, Supabase y las interfaces.
*   **FASE 4 — Frontend**: Desarrollo de la SPA Next.js con Tailwind, shadcn y Chart.js, integrando Supabase Auth en el cliente.
*   **FASE 5 — Netlify Preview**: Despliegue preliminar y pruebas en Netlify.
*   **FASE 6 — Validación final y Producción**: Contraste final de KPIs e inauguración oficial del panel.
