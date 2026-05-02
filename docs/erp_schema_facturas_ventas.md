# Schema ERP - Control Integral .Net Advanced
## Tabla: facturas_ventas (o cabecera)

**Campos identificados:**
- `cod_factura` — Código de factura
- `tipo_factura` — Tipo de documento
- `cod_caja` — Código de caja
- `cod_cliente` — Código de cliente
- `cod_seccion` — Código de sección/almacén
- `nombre_seccion` — Nombre de sección
- `cif` — CIF/NIF del cliente
- `razon_social` — Razón social
- `nombre_comercial` — Nombre comercial
- `direccion1`, `direccion2` — Dirección
- `cp` — Código postal
- `poblacion` — Población
- `provincia` — Provincia
- `cod_idioma` — Idioma
- `cod_pais` — País
- `cod_divisa` — Divisa
- `cambio` — Tipo de cambio
- `cod_divisa_activa` — Divisa activa
- `telefono`, `fax`, `e_mail` — Contacto
- `cod_forma_liquidacion` — Forma de pago
- `dia_pago1`, `dia_pago2`, `dia_pago3` — Días de pago
- `cod_banco`, `nombre_banco` — Datos bancarios
- `direccion_banco1`, `direccion_banco2`
- `cp_banco`, `poblacion_banco`, `provincia_banco`, `cod_pais_banco`, `nombre_pais_banco`
- `ccc`, `iban`, `swift` — IBAN/SWIFT
- `impuestos_incluidos` — ¿IVA incluido?
- `importe` — Importe base
- `importe_impuestos` — Importe impuestos
- `importe_divisa` — Importe en divisa
- `importe_divisa_impuestos` — Impuestos en divisa
- `importe_cobrado` — Cobrado
- `importe_divisa_cobrado` — Cobrado en divisa
- `factura_oficial` — ¿Es factura oficial?
- `cod_almacen` — Almacén
- `cargo_financiacion`, `importe_financiacion`, `importe_divisa_financiacion` — Financiación
- `tipo_factura_rectificada`, `cod_factura_rectificada`, `fecha_factura_rectificada` — Factura rectificada
- `retencion_irpf`, `importe_retencion_irpf`, `importe_divisa_retencion_irpf` — IRPF
- `criterio_de_caja` — Criterio de caja
- `dir3_*` — Campos Facturae (muchos campos DIR3)
- `cod_arqueo` — Arqueo
- `hasp` — Referencia HASP
- `cod_primera_venta`, `cod_ultima_venta` — Primera/última venta
- `tipo_operacion_iva` — Tipo operación IVA
- `cod_proveedor_otorgado` — Proveedor otorgado
- `cod_forma_liquidacion_facturae` — Forma de pago Facturae
- `referencia_contrato_facturae` — Referencia contrato
- `dir3_organo_proponente` y dirección — Órgano proponente
- `rowguid` — GUID

**OBSERVACIÓN IMPORTANTE:**
Esta tabla parece ser una tabla "todo en uno" que denormaliza la cabecera de factura con los datos del cliente. Es típico de ERPs antiguos.

**DATOS QUE FALTAN:**
- No veo campo de fecha principal (`fecha_factura`?)
- No veo campo de vendedor/comercial
- No sabemos si las líneas de detalle están aquí o en otra tabla
- Falta la tabla de artículos
