-- ====================================================================
-- RIBERA ESTADÍSTICAS - ESQUEMA DE BASE DE DATOS DE REPORTING (SUPABASE)
-- ====================================================================
--
-- ⚠️  ADVERTENCIA: ESTE SCRIPT ES DESTRUCTIVO.
--
-- Este archivo es una referencia de bootstrap / esquema completo para
-- nuevos entornos. Contiene DROP TABLE ... CASCADE al inicio.
--
-- NO ejecutar en producción con datos existentes: borrará TODAS las
-- tablas del esquema de reporting y sus dependencias.
--
-- Para entornos productivos, usar las migraciones idempotentes en
-- database/migrations/ en lugar de este archivo.
-- ====================================================================

-- Habilitar extensión pgcrypto para UUIDs
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- --------------------------------------------------------------------
-- 1. LIMPIEZA DE TABLAS PREVIAS (DESTRUCTIVO - solo para bootstrap)
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS receivables CASCADE;
DROP TABLE IF EXISTS suppliers_reporting CASCADE;
DROP TABLE IF EXISTS clients_reporting CASCADE;
DROP TABLE IF EXISTS sales_lines CASCADE;
DROP TABLE IF EXISTS sales_headers CASCADE;
DROP TABLE IF EXISTS stats_sales_monthly CASCADE;
DROP TABLE IF EXISTS products_stock CASCADE;
DROP TABLE IF EXISTS stats_sellers CASCADE;
DROP TABLE IF EXISTS stats_warehouses CASCADE;
DROP TABLE IF EXISTS stats_kpis CASCADE;
DROP TABLE IF EXISTS sync_state CASCADE;
DROP TABLE IF EXISTS sync_runs CASCADE;
DROP TABLE IF EXISTS sync_requests CASCADE;

-- --------------------------------------------------------------------
-- 2. CREACIÓN DE TABLAS
-- --------------------------------------------------------------------

-- Historial de ejecuciones de sincronización
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

-- Solicitudes de sincronización manual y automática (lock lógico + auditoría)
CREATE TABLE sync_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    dataset VARCHAR(100) NOT NULL DEFAULT 'sales',
    status VARCHAR(50) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','running','success','failed')),
    requested_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    source VARCHAR(50) NOT NULL
        CHECK (source IN ('manual','auto')),
    requested_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    started_at TIMESTAMP WITH TIME ZONE,
    finished_at TIMESTAMP WITH TIME ZONE,
    error_message TEXT,
    updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW()
);

-- KPIs Consolidados (SNAPSHOT - batch_id primero en PK)
CREATE TABLE stats_kpis (
    batch_id UUID NOT NULL,
    period_key VARCHAR(50) NOT NULL, -- 'hoy', 'quincena', 'year_actual'
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    total_orders INT DEFAULT 0,
    avg_ticket NUMERIC(15, 6) DEFAULT 0.0,
    pending_amount NUMERIC(15, 6) DEFAULT 0.0,
    unique_clients INT DEFAULT 0,
    total_cost NUMERIC(15, 6) DEFAULT 0.0,
    gross_profit NUMERIC(15, 6) DEFAULT 0.0,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (batch_id, period_key)
);

-- Ventas por Almacén (SNAPSHOT - batch_id primero en PK)
CREATE TABLE stats_warehouses (
    batch_id UUID NOT NULL,
    period_key VARCHAR(50) NOT NULL,
    cod_almacen VARCHAR(50) NOT NULL,
    orders_count INT DEFAULT 0,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    PRIMARY KEY (batch_id, period_key, cod_almacen)
);

-- Ventas por Vendedor (SNAPSHOT - batch_id primero en PK)
CREATE TABLE stats_sellers (
    batch_id UUID NOT NULL,
    period_key VARCHAR(50) NOT NULL,
    cod_vendedor VARCHAR(50) NOT NULL,
    nombre_vendedor VARCHAR(255),
    orders_count INT DEFAULT 0,
    total_sales NUMERIC(15, 6) DEFAULT 0.0,
    PRIMARY KEY (batch_id, period_key, cod_vendedor)
);

-- Productos y Existencias (SNAPSHOT - batch_id primero en PK)
CREATE TABLE products_stock (
    batch_id UUID NOT NULL,
    cod_articulo VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255),
    marca VARCHAR(100),
    cod_familia VARCHAR(50),
    cod_subfamilia VARCHAR(50),
    stock_total NUMERIC(15, 6) DEFAULT 0.0,
    stock_minimo NUMERIC(15, 6) DEFAULT 0.0,
    precio_coste NUMERIC(15, 6) DEFAULT 0.0,
    precio_venta NUMERIC(15, 6) DEFAULT 0.0,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (batch_id, cod_articulo)
);

-- Vencimientos de Clientes / Cartera de Cobro (SNAPSHOT - batch_id primero en PK)
CREATE TABLE receivables (
    batch_id UUID NOT NULL,
    cod_almacen VARCHAR(50),
    cod_factura VARCHAR(50) NOT NULL,
    tipo_factura INT NOT NULL,
    cod_empresa VARCHAR(50) NOT NULL,
    numero INT NOT NULL,
    cod_cliente VARCHAR(50),
    razon_social VARCHAR(255),
    cif VARCHAR(50),
    fecha_factura DATE,
    fecha_vencimiento DATE,
    importe NUMERIC(15, 6) DEFAULT 0.0,
    importe_cobrado NUMERIC(15, 6) DEFAULT 0.0,
    importe_pendiente NUMERIC(15, 6) DEFAULT 0.0,
    cod_forma_liquidacion VARCHAR(50),
    cod_remesa VARCHAR(50),
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (batch_id, cod_factura, tipo_factura, cod_empresa, numero)
);

-- Histórico de Ventas Mensuales (INCREMENTAL - PK natural)
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

-- Cabeceras de Venta (INCREMENTAL - PK natural)
CREATE TABLE sales_headers (
    cod_venta VARCHAR(50) NOT NULL,
    tipo_venta INT NOT NULL,
    cod_empresa VARCHAR(50) NOT NULL,
    cod_caja VARCHAR(50) NOT NULL,
    cod_almacen VARCHAR(50),
    cod_cliente VARCHAR(50),
    razon_social VARCHAR(255),
    fecha_venta TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    cod_forma_liquidacion VARCHAR(50),
    cod_vendedor VARCHAR(50),
    nombre_vendedor VARCHAR(255),
    total_amount NUMERIC(15, 6) DEFAULT 0.0,
    net_amount NUMERIC(15, 6),
    pending_amount NUMERIC(15, 6) DEFAULT 0.0,
    anulada BOOLEAN DEFAULT FALSE,
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (cod_venta, tipo_venta, cod_empresa, cod_caja)
);

-- Líneas de Venta (INCREMENTAL - PK natural)
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
    net_amount NUMERIC(15, 6),
    total_amount NUMERIC(15, 6) DEFAULT 0.0,
    source_modified_at TIMESTAMP WITH TIME ZONE,
    synced_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    PRIMARY KEY (cod_venta, tipo_venta, cod_empresa, cod_caja, linea)
);

-- Directorio de Clientes (INCREMENTAL - PK natural)
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

-- Directorio de Proveedores (INCREMENTAL - PK natural)
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

-- Helper: devuelve el batch_id activo para receivables
CREATE OR REPLACE FUNCTION public.get_active_receivables_batch()
RETURNS UUID
LANGUAGE sql
STABLE
SECURITY INVOKER
SET search_path TO 'public'
AS $$
    SELECT active_batch_id
    FROM public.sync_state
    WHERE dataset = 'receivables'
    LIMIT 1;
$$;

-- --------------------------------------------------------------------
-- 3. HABILITACIÓN DE RLS (Row Level Security)
-- --------------------------------------------------------------------
ALTER TABLE sync_runs ENABLE ROW LEVEL SECURITY;
ALTER TABLE sync_state ENABLE ROW LEVEL SECURITY;
ALTER TABLE sync_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE stats_kpis ENABLE ROW LEVEL SECURITY;
ALTER TABLE stats_warehouses ENABLE ROW LEVEL SECURITY;
ALTER TABLE stats_sellers ENABLE ROW LEVEL SECURITY;
ALTER TABLE products_stock ENABLE ROW LEVEL SECURITY;
ALTER TABLE receivables ENABLE ROW LEVEL SECURITY;
ALTER TABLE stats_sales_monthly ENABLE ROW LEVEL SECURITY;
ALTER TABLE sales_headers ENABLE ROW LEVEL SECURITY;
ALTER TABLE sales_lines ENABLE ROW LEVEL SECURITY;
ALTER TABLE clients_reporting ENABLE ROW LEVEL SECURITY;
ALTER TABLE suppliers_reporting ENABLE ROW LEVEL SECURITY;

-- --------------------------------------------------------------------
-- 4. CREACIÓN DE POLÍTICAS RLS (Lectura exclusiva para autenticados)
-- --------------------------------------------------------------------

-- sync_runs
CREATE POLICY select_sync_runs ON sync_runs 
    FOR SELECT TO authenticated USING (true);

-- sync_state
CREATE POLICY select_sync_state ON sync_state
    FOR SELECT TO authenticated USING (true);

-- sync_requests: lectura compartida para saber si hay una activa
CREATE POLICY select_sync_requests ON sync_requests
    FOR SELECT TO authenticated USING (true);

-- sync_requests: solo insert manual y propio desde el frontend
CREATE POLICY insert_sync_requests ON sync_requests
    FOR INSERT TO authenticated
    WITH CHECK (
        requested_by = auth.uid()
        AND source = 'manual'
    );

-- stats_kpis
CREATE POLICY select_stats_kpis ON stats_kpis 
    FOR SELECT TO authenticated USING (true);

-- stats_warehouses
CREATE POLICY select_stats_warehouses ON stats_warehouses 
    FOR SELECT TO authenticated USING (true);

-- stats_sellers
CREATE POLICY select_stats_sellers ON stats_sellers 
    FOR SELECT TO authenticated USING (true);

-- products_stock
CREATE POLICY select_products_stock ON products_stock
    FOR SELECT TO authenticated USING (true);

-- receivables
CREATE POLICY select_receivables ON receivables
    FOR SELECT TO authenticated USING (true);

-- stats_sales_monthly
CREATE POLICY select_stats_sales_monthly ON stats_sales_monthly
    FOR SELECT TO authenticated USING (true);

-- sales_headers
CREATE POLICY select_sales_headers ON sales_headers 
    FOR SELECT TO authenticated USING (true);

-- sales_lines
CREATE POLICY select_sales_lines ON sales_lines 
    FOR SELECT TO authenticated USING (true);

-- clients_reporting
CREATE POLICY select_clients_reporting ON clients_reporting 
    FOR SELECT TO authenticated USING (true);

-- suppliers_reporting
CREATE POLICY select_suppliers_reporting ON suppliers_reporting 
    FOR SELECT TO authenticated USING (true);

-- Nota: Las operaciones de escritura (INSERT, UPDATE, DELETE) quedan
-- denegadas de forma predeterminada para el rol público e incluso autenticado.
-- Solo las llamadas usando la Secret Key / service_role omiten las RLS y tienen 
-- privilegios completos de escritura.

-- 4.1 OTORGAR PRIVILEGIOS DE SELECT AL ROL AUTHENTICATED
GRANT SELECT ON ALL TABLES IN SCHEMA public TO authenticated;

-- --------------------------------------------------------------------
-- 5. CREACIÓN DE ÍNDICES OPTIMIZADOS
-- --------------------------------------------------------------------

-- Búsquedas y filtros en la pantalla de Ventas
CREATE INDEX idx_sales_headers_fecha ON sales_headers(fecha_venta DESC);
CREATE INDEX idx_sales_headers_cliente ON sales_headers(cod_cliente);
CREATE INDEX idx_sales_headers_vendedor ON sales_headers(cod_vendedor);
CREATE INDEX idx_sales_headers_pendiente ON sales_headers(pending_amount) WHERE pending_amount > 0;

-- Carga rápida de líneas asociadas a un documento (modal)
CREATE INDEX idx_sales_lines_documento ON sales_lines(cod_venta, tipo_venta, cod_empresa, cod_caja);

-- Filtros rápidos en la pantalla de Inventario (incluyen batch_id por ser tabla SNAPSHOT)
CREATE INDEX idx_products_stock_minimo ON products_stock(batch_id, stock_total, stock_minimo) WHERE stock_total < stock_minimo;
CREATE INDEX idx_products_stock_categoria ON products_stock(batch_id, cod_familia, cod_subfamilia);

-- Filtros de cartera de cobro (incluyen batch_id por ser tabla SNAPSHOT)
CREATE INDEX idx_receivables_almacen ON receivables(batch_id, cod_almacen);
CREATE INDEX idx_receivables_forma_liquidacion ON receivables(batch_id, cod_forma_liquidacion);
CREATE INDEX idx_receivables_remesa ON receivables(batch_id, cod_remesa) WHERE cod_remesa IS NULL;
CREATE INDEX idx_receivables_vencimiento ON receivables(batch_id, fecha_vencimiento);
CREATE INDEX idx_receivables_cliente ON receivables(batch_id, cod_cliente);

-- Listados ordenados de Clientes y Proveedores
CREATE INDEX idx_clients_reporting_spent ON clients_reporting(total_spent DESC);
CREATE INDEX idx_suppliers_reporting_name ON suppliers_reporting(razon_social);

-- Índice único parcial: solo una solicitud activa (pending/running) por dataset
CREATE UNIQUE INDEX idx_sync_requests_one_active_per_dataset
    ON sync_requests(dataset)
    WHERE status IN ('pending','running');

-- Índice para búsqueda eficiente de pendientes por el agente local
CREATE INDEX idx_sync_requests_pending_dataset ON sync_requests(dataset, requested_at)
    WHERE status = 'pending';

-- --------------------------------------------------------------------
-- 6. TRIGGERS DE MANTENIMIENTO
-- --------------------------------------------------------------------

-- Actualizar automáticamente updated_at en sync_requests
CREATE OR REPLACE FUNCTION sync_requests_set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER tr_sync_requests_updated_at
    BEFORE UPDATE ON sync_requests
    FOR EACH ROW
    EXECUTE FUNCTION sync_requests_set_updated_at();
