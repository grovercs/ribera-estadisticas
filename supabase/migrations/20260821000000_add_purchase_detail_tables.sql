-- Detalle sincronizado de albaranes de compra (ERP hist_compras_*).
-- Esta migración no sustituye purchases_albaranes_summary ni sus RPCs.

CREATE TABLE IF NOT EXISTS public.purchase_headers (
    cod_compra INTEGER NOT NULL,
    tipo_compra SMALLINT NOT NULL,
    cod_empresa SMALLINT NOT NULL,
    cod_proveedor INTEGER NOT NULL,
    cod_almacen SMALLINT,
    nombre_comercial VARCHAR(40),
    razon_social VARCHAR(40),
    fecha_compra TIMESTAMP WITHOUT TIME ZONE,
    importe NUMERIC(19, 6),
    source_modified_at TIMESTAMP WITH TIME ZONE NULL,
    synced_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    PRIMARY KEY (cod_compra, tipo_compra, cod_empresa, cod_proveedor)
);

CREATE INDEX IF NOT EXISTS idx_purchase_headers_fecha_almacen
    ON public.purchase_headers (fecha_compra, cod_almacen);

CREATE INDEX IF NOT EXISTS idx_purchase_headers_proveedor
    ON public.purchase_headers (cod_proveedor);

CREATE TABLE IF NOT EXISTS public.purchase_lines (
    cod_compra INTEGER NOT NULL,
    tipo_compra SMALLINT NOT NULL,
    cod_empresa SMALLINT NOT NULL,
    cod_proveedor INTEGER NOT NULL,
    linea SMALLINT NOT NULL,
    cod_articulo VARCHAR(15),
    referencia_proveedor VARCHAR(15),
    descripcion VARCHAR(255),
    cantidad NUMERIC(19, 6),
    tarifa NUMERIC(19, 6),
    precio_coste NUMERIC(19, 6),
    dto1 NUMERIC(9, 3),
    dto2 NUMERIC(9, 3),
    dto3 NUMERIC(9, 3),
    dto4 NUMERIC(9, 3),
    importe NUMERIC(19, 6),
    cod_almacen SMALLINT,
    source_modified_at TIMESTAMP WITH TIME ZONE NULL,
    synced_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    PRIMARY KEY (cod_compra, tipo_compra, cod_empresa, cod_proveedor, linea)
);

CREATE INDEX IF NOT EXISTS idx_purchase_lines_documento
    ON public.purchase_lines (cod_compra, tipo_compra, cod_empresa, cod_proveedor);

ALTER TABLE public.purchase_headers ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.purchase_lines ENABLE ROW LEVEL SECURITY;

CREATE POLICY select_purchase_headers ON public.purchase_headers
    FOR SELECT TO authenticated USING (true);

CREATE POLICY select_purchase_lines ON public.purchase_lines
    FOR SELECT TO authenticated USING (true);

GRANT SELECT ON public.purchase_headers TO authenticated;
GRANT SELECT ON public.purchase_lines TO authenticated;
