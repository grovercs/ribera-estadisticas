-- Importe neto de línea antes de IVA, procedente de hist_ventas_linea.importe.
-- Nullable para permitir desplegar el esquema antes del backfill histórico.

ALTER TABLE public.sales_lines
    ADD COLUMN IF NOT EXISTS net_amount NUMERIC(15, 6) NULL;
