# Plan de Implementación (Actualizado): Publicación del Panel de Estadísticas en Netlify + Supabase

Este documento detalla la auditoría de la aplicación actual y define la estrategia técnica y el plan de migración para convertir la aplicación en una solución web serverless de solo lectura en Next.js alojada en Netlify, utilizando Supabase (PostgreSQL + Auth + RLS) como base de datos de reporting.

---

## A. Estado Actual (Auditoría Técnica)

### 1. Arquitectura Encontrada y Versiones
*   **Backend actual**: Laravel 13.7.0 corriendo en PHP 8.4.24 (bajo WampServer local, Apache/2.4.51 en el puerto `8080`).
*   **Base de datos ERP**: SQL Server (`INTEGRAL`) alojada en la red local (`192.168.1.215` / `192.168.200.105`). La conexión se realiza a través de `pdo_sqlsrv`.
*   **Base de datos Local**: MySQL 8 (`ribera_estadisticas` en puerto `3306/3307/3308`) configurada como réplica/espejo mediante migraciones locales, actualmente con **0 registros** en todas sus tablas.
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
*   *Nota*: El archivo `.env.local` configura el puerto de MySQL en local como `3308`.

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

### 1. Mutabilidad y Detección de Cambios
Las ventas de periodos pasados no son inmutables; se producen anulaciones, rectificaciones o correcciones administrativas en el ERP. La auditoría de estructura ha revelado que el ERP dispone del campo de auditoría **`fecha_modificacion (datetime)`** tanto en `hist_ventas_cabecera` como en `hist_ventas_linea`, lo que permite una sincronización robusta:

*   **Sincronización Incremental Primaria**:
    El sincronizador local guardará la marca de tiempo del último proceso de sincronización exitoso. En cada ejecución posterior, consultará en SQL Server únicamente aquellos registros modificados recientemente:
    `WHERE fecha_modificacion >= ? OR fecha_hora_alta >= ?`
    (utilizando la marca de tiempo de la última sincronización menos un margen de seguridad de 5 minutos para evitar pérdidas de registros por milisegundos).
*   **Ventana Móvil de Seguridad**:
    Diariamente en la ejecución nocturna, el sincronizador realizará una carga por reemplazo/UPSERT de los últimos 30 días para recalcular cualquier inconsistencia o cambio que pudiera haber escapado del flujo incremental estándar.
*   **Validación de Integridad**:
    Una vez al mes, se compararán las sumas de control mensuales de facturación entre el ERP y Supabase para el histórico completo. Si se detecta discrepancia, se forzará la resincronización del mes afectado.

### 2. Evitar el uso de TRUNCATE
Para evitar que un fallo de red o del proceso de importación deje las tablas de estadísticas vacías en Supabase, se implementarán los siguientes mecanismos:
*   **Cargas por UPSERT**: La importación de catálogos y listados extensos (clientes, proveedores, stock) se realizará mediante `UPSERT` directo utilizando las llaves naturales del ERP, evitando vaciar la tabla de destino antes del proceso.
*   **Staging para Tablas de Consolidado**: Para los KPIs calculados y agregados pequeños, los datos nuevos se subirán a tablas temporales de paso (ej. `temp_stats_kpis`). Una vez verificada la carga sin errores de red, se realizará el intercambio de datos en producción.

### 3. Transaccionalidad Real en Supabase
Para garantizar la consistencia atómica y evitar estados intermedios inconsistentes (por ejemplo, si el frontend lee a Supabase mientras un lote grande se está cargando), se utilizará la estrategia de **Staging y Activación por ID de Lote (Batch ID)**:
1.  Cada ejecución del sincronizador local generará un `batch_id` único (UUIDv4).
2.  Todos los registros sincronizados se insertarán en Supabase con este `batch_id` asociado. Los datos de la sincronización anterior siguen activos bajo su propio `batch_id`.
3.  Al completarse exitosamente la carga del lote completo sin errores de red, el sincronizador actualizará la tabla transaccional `sync_status` configurando el nuevo `batch_id` como el **activo** y registrando la fecha/hora de actualización.
4.  **En Next.js**: Todas las consultas del panel filtrarán dinámicamente los datos por el `batch_id` activo en `sync_status`. De esta manera, el cambio de dataset en el panel es instantáneo y atómico.
5.  Un procedimiento nocturno (o el propio script al finalizar) eliminará de Supabase las filas con `batch_id` obsoletos de ejecuciones pasadas.

---

## C. Modelo Supabase Propuesto (Base de Datos de Reporting)

El esquema de Supabase está diseñado específicamente para reporting, utilizando claves naturales heredadas del ERP para garantizar idempotencia:

### Tablas de Supabase

```sql
-- Estado de sincronizaciones y control de Batch ID
CREATE TABLE sync_status (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    started_at TIMESTAMP WITH TIME ZONE NOT NULL,
    completed_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(50) NOT NULL, -- 'running', 'success', 'failed'
    records_processed INT DEFAULT 0,
    active_batch_id UUID,
    error_message TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- KPIs consolidados por periodo
CREATE TABLE stats_kpis (
    period_key VARCHAR(50) PRIMARY KEY, -- 'hoy', 'quincena', 'year_actual'
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    total_orders INT DEFAULT 0,
    avg_ticket NUMERIC(15, 6) DEFAULT 0.0,
    pending_amount NUMERIC(15, 6) DEFAULT 0.0,
    unique_clients INT DEFAULT 0,
    total_cost NUMERIC(15, 6) DEFAULT 0.0,
    gross_profit NUMERIC(15, 6) DEFAULT 0.0,
    batch_id UUID NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Comparativa mensual de ventas histórica (2012 - Año Anterior)
CREATE TABLE stats_sales_monthly (
    year INT,
    month INT,
    revenue NUMERIC(15, 6) DEFAULT 0.0,
    total_cost NUMERIC(15, 6) DEFAULT 0.0,
    gross_profit NUMERIC(15, 6) DEFAULT 0.0,
    orders_count INT DEFAULT 0,
    PRIMARY KEY (year, month)
);

-- Rendimiento por almacén
CREATE TABLE stats_warehouses (
    cod_almacen VARCHAR(50),
    period_key VARCHAR(50),
    orders_count INT DEFAULT 0,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    batch_id UUID NOT NULL,
    PRIMARY KEY (cod_almacen, period_key)
);

-- Rendimiento por comercial
CREATE TABLE stats_sellers (
    cod_vendedor VARCHAR(50),
    nombre_vendedor VARCHAR(255),
    period_key VARCHAR(50),
    orders_count INT DEFAULT 0,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    batch_id UUID NOT NULL,
    PRIMARY KEY (cod_vendedor, period_key)
);

-- Cabeceras de venta (Año Actual + Año Anterior)
CREATE TABLE sales_headers (
    cod_venta VARCHAR(50),
    tipo_venta INT,
    cod_empresa VARCHAR(50),
    cod_caja VARCHAR(50),
    cod_almacen VARCHAR(50),
    cod_cliente VARCHAR(50),
    razon_social VARCHAR(255),
    fecha_venta TIMESTAMP WITH TIME ZONE,
    cod_forma_liquidacion VARCHAR(50),
    cod_vendedor VARCHAR(50),
    nombre_vendedor VARCHAR(255),
    total_amount NUMERIC(15, 6) DEFAULT 0.0,
    pending_amount NUMERIC(15, 6) DEFAULT 0.0,
    anulada BOOLEAN DEFAULT FALSE,
    batch_id UUID NOT NULL,
    PRIMARY KEY (cod_venta, tipo_venta, cod_empresa, cod_caja)
);

-- Líneas de venta (Año Actual + Año Anterior)
CREATE TABLE sales_lines (
    cod_venta VARCHAR(50),
    tipo_venta INT,
    cod_empresa VARCHAR(50),
    cod_caja VARCHAR(50),
    linea INT,
    cod_articulo VARCHAR(50),
    descripcion VARCHAR(255),
    cantidad NUMERIC(15, 6) DEFAULT 0.0,
    precio NUMERIC(15, 6) DEFAULT 0.0,
    precio_coste NUMERIC(15, 6) DEFAULT 0.0,
    total_amount NUMERIC(15, 6) DEFAULT 0.0,
    batch_id UUID NOT NULL,
    PRIMARY KEY (cod_venta, tipo_venta, cod_empresa, cod_caja, linea)
);

-- Existencias y catálogos de productos
CREATE TABLE products_stock (
    cod_articulo VARCHAR(50) PRIMARY KEY,
    descripcion VARCHAR(255),
    marca VARCHAR(100),
    cod_familia VARCHAR(50),
    cod_subfamilia VARCHAR(50),
    stock_total NUMERIC(15, 6) DEFAULT 0.0,
    stock_minimo NUMERIC(15, 6) DEFAULT 0.0,
    precio_coste NUMERIC(15, 6) DEFAULT 0.0,
    precio_venta NUMERIC(15, 6) DEFAULT 0.0,
    batch_id UUID NOT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Directorios de Clientes
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
    batch_id UUID NOT NULL
);

-- Directorios de Proveedores
CREATE TABLE suppliers_reporting (
    cod_proveedor VARCHAR(50) PRIMARY KEY,
    razon_social VARCHAR(255),
    cif VARCHAR(50),
    poblacion VARCHAR(100),
    provincia VARCHAR(100),
    telefono VARCHAR(50),
    e_mail VARCHAR(255),
    credito_otorgado NUMERIC(15, 6) DEFAULT 0.0,
    batch_id UUID NOT NULL
);
```

---

## D. Inventario de Datos por Pantalla y Consultas

A continuación se detalla la relación lógica entre las pantallas actuales y las consultas del ERP:

*   **Cuadro de Mando General**: Consulta a `hist_ventas_cabecera` filtrando ventas no anuladas del período actual. Agrega ingresos, ticket medio, y clientes únicos. Muestra gráficos comparativos de evolución mensual.
*   **Ventas y Facturación**: Listado paginado de `hist_ventas_cabecera` con filtros por almacén, vendedor, cliente y estado. Modal AJAX que lee `hist_ventas_linea`.
*   **Existencias / Stock**: Inventario paginado de la tabla `articulos` unida a `stocks` calculando valor de inventario y artículos bajo mínimos.
*   **Clientes**: Directorio paginado de `clientes` con ventas acumuladas calculadas mediante la agregación de `hist_ventas_cabecera`.
*   **Financiero y Márgenes**: Análisis de `hist_ventas_linea` cruzado con `hist_ventas_cabecera` para comparar facturación contra precio de coste, excluyendo códigos corruptos o no inventariables.

---

## E. Frontend Next.js (Netlify)

*   **Stack**: Next.js App Router, TypeScript, Tailwind CSS, shadcn/ui.
*   **Páginas**: `/login`, `/dashboard`, `/sales` (listado, líneas y exportación CSV), `/stock`, `/clients`, `/suppliers`, `/financial`, `/store-dashboard`.
*   **Gráficos**: Se utilizará **Chart.js** (wrapper `react-chartjs-2`) para clonar con precisión la estética y visualización de la versión local actual.
*   **Gestión de Estados**: Componentes Skeleton para la fase de loading. En caso de fallas de sincronización (último lote de hace >2 horas), se mostrará una barra de advertencia discreta manteniendo el dashboard navegable con los datos almacenados.

---

## F. Seguridad (Supabase Auth & RLS)

*   **Autenticación**: Obligatoria mediante Supabase Auth. Los usuarios se darán de alta directamente en el panel de Supabase.
*   **Políticas RLS**:
    *   Habilitado en todas las tablas.
    *   `SELECT`: Permitido únicamente para usuarios autenticados (`auth.role() = 'authenticated'`).
    *   `ALL` (Escritura/Modificación): Denegado para todos los usuarios. Solo la cuenta de servicio (`service_role`) utilizada de forma local y segura por el Sincronizador podrá escribir datos.

---

## G. Plan de Implementación por Fases

*   **FASE 0 — Auditoría e Informe**: Análisis de datos, volúmenes de ERP, campos de auditoría de fechas de modificación y resolución de inconsistencias locales. (En curso / Finalizado conceptualmente).
*   **FASE 1 — Supabase**: Creación de proyecto en Supabase, definición de tablas PostgreSQL, e instalación de políticas RLS y configuración de Supabase Auth.
*   **FASE 2 — Sincronizador**: Desarrollo del comando Artisan `ribera:sync-to-supabase` en local utilizando la estrategia de Batch ID y actualizaciones incrementales mediante `fecha_modificacion`.
*   **FASE 3 — Paridad de Datos**: Cargas iniciales completas, automatización e igualación de consultas e importes entre SQL Server, Supabase y las interfaces.
*   **FASE 4 — Frontend**: Desarrollo de la SPA Next.js con Tailwind, shadcn y Chart.js, integrando Supabase Auth en el cliente.
*   **FASE 5 — Netlify Preview**: Despliegue preliminar y pruebas en Netlify.
*   **FASE 6 — Validación final y Producción**: Contraste final de KPIs e inauguración oficial del panel.
