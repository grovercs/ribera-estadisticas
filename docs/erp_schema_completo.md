# Schema ERP - Control Integral .Net Advanced (Completo)

## Tablas maestras identificadas

### 1. `clientes` — Maestro de clientes
**PK:** `cod_cliente` (int)

**Campos clave para BI:**
- `cod_cliente` — Código único
- `nombre_comercial` — Nombre comercial (40)
- `razon_social` — Razón social (40)
- `cif` — CIF/NIF (15)
- `direccion1`, `direccion2` — Dirección (40)
- `CP` — Código postal (8)
- `poblacion` — Población (40)
- `provincia` — Provincia (40)
- `cod_pais` — País (2)
- `cod_idioma` — Idioma (2)
- `cod_divisa` — Divisa (3)
- `cod_tipo_cliente` — Tipo de cliente (2)
- `telefono`, `fax`, `e_mail` — Contacto
- `cod_forma_liquidacion` — Forma de pago (4)
- `dia_pago1`, `dia_pago2`, `dia_pago3` — Días de pago
- `limite_credito` — Límite de crédito (numeric 19,6)
- `moroso` — ¿Moroso? (1)
- `cod_banco`, `direccion_banco1`, `cp_banco`, `poblacion_banco`, `ccc`, `iban`, `swift` — Datos bancarios
- `dto_pronto_pago` — Dto. pronto pago
- `plazo_entrega` — Plazo de entrega
- `cod_vendedor` — Código del vendedor asignado
- `comision` — Comisión del vendedor
- `fecha_alta`, `fecha_baja` — Fechas de alta/baja
- `cod_zona` — Zona geográfica
- `cod_estado_cliente` — Estado del cliente
- `cod_ruta` — Ruta de reparto
- `tipo_operacion_iva` — Tipo operación IVA
- `ventas_credito_contado` — Crédito o contado
- `iva_incluido_ventas` — IVA incluido en ventas
- `latitud`, `longitud` — Coordenadas GPS
- `rowguid` — GUID de replicación

**Observación:** Tabla muy denormalizada con campos DIR3 para Facturae.

---

### 2. `articulos` — Maestro de artículos
**PK:** `cod_articulo` (varchar 15)

**Campos clave para BI:**
- `cod_articulo` — Código del artículo
- `marca` — Marca (15)
- `cod_barras` — Código de barras (15)
- `cod_familia` — Familia (4)
- `cod_subfamilia` — Subfamilia (4)
- `cod_impuesto` — Tipo de impuesto
- `unidades_venta` — Unidades de venta
- `cod_unidad` — Unidad (3)
- `cod_proveedor_activo` — Proveedor activo
- `fecha_alta`, `fecha_baja` — Fechas
- `precio_coste` — Precio de coste
- `incremento`, `incremento_minimo` — Incrementos sobre coste
- `cargo` — Cargo adicional
- `precio_venta_base` — PVB
- `precio_venta_publico` — PVP
- `precio_medio_ponderado` — PMP
- `descuento_maximo` — Dto. máximo
- `cod_gama` — Gama (4)
- `peso_web`, `alto_web`, `ancho_web`, `profundo_web` — Dimensiones
- `tipo_articulo` — Tipo de artículo (10)
- `rowguid` — GUID

---

### 3. `familias` — Familias de productos
**PK:** `cod_familia` (varchar 4)

- `cod_familia` — Código (4)
- `descripcion` — Descripción (40)
- `codigo_contable_compras` — Cuenta contable compras
- `codigo_contable_ventas` — Cuenta contable ventas

---

### 4. `subfamilias` — Subfamilias
**PK:** (`cod_familia`, `cod_subfamilia`)

- `cod_familia` — Código familia (4)
- `cod_subfamilia` — Código subfamilia (4)
- `descripcion` — Descripción (40)
- `incremento` — Incremento por defecto

---

### 5. `almacenes` — Almacenes / Puntos de venta
**PK:** `cod_almacen` (smallint)

- `cod_almacen` — Código
- `nombre` — Nombre (40)
- `direccion1`, `direccion2`, `cp`, `poblacion`, `provincia`, `cod_pais`
- `telefono`, `fax`, `email`
- `persona_contacto` — Contacto
- `fecha_ultimo_inventario`
- `rowguid`

---

### 6. `vendedores` — Comerciales
**PK:** `cod_vendedor` (int)

- `cod_vendedor` — Código
- `nombre` — Nombre (40)
- `direccion1`, `cp`, `poblacion`, `provincia`
- `telefono`, `fax`, `e_mail`
- `seguridad_social` — SS (15)
- `mutua` — Mutua (40)
- `comision_general` — Comisión general
- `aniversario` — Fecha aniversario
- `observaciones` — Observaciones (text)

---

## Tablas de transacciones

### 7. `ventas_cabecera` — Cabecera de ventas (tickets, albaranes)
**PK:** (`cod_venta`, `tipo_venta`, `cod_empresa`, `cod_caja`)

**Campos clave:**
- `cod_venta` — Número de venta (int)
- `tipo_venta` — Tipo: 0=ticket, 1=albarán, etc. (smallint)
- `cod_empresa` — Empresa (smallint)
- `cod_caja` — Caja (smallint)
- `cod_almacen` — Almacén
- `cod_cliente` — Cliente
- `cod_seccion` — Sección
- `nombre_seccion` — Nombre sección
- `cif` — CIF (denormalizado)
- `nombre_comercial`, `razon_social` — Datos cliente (denormalizados)
- `cod_impuesto` — Impuesto por defecto
- `direccion1`, `cp`, `poblacion`, `provincia` — Dirección entrega
- `cod_pais`, `nombre_pais`
- `cod_divisa`, `cod_divisa_activa`, `cambio`
- `impuestos_incluidos` — ¿IVA incluido?
- `fecha_venta` — Fecha de la venta
- `hora_venta` — Hora
- `telefono`, `fax`, `e_mail`
- `cod_forma_liquidacion` — Forma de pago
- `dia_pago1`, `dia_pago2`, `dia_pago3`
- `cod_banco`, `direccion_banco1`, `cp_banco`, `poblacion_banco`, `ccc`
- `cod_vendedor` — Vendedor
- `nombre_vendedor` — Nombre vendedor (denormalizado)
- `su_pedido` — Número de pedido del cliente
- `importe_cobrado` — Importe cobrado
- `importe_divisa_cobrado`
- `importe_pendiente` — Pendiente de cobro
- `facturado` — ¿Facturado? (S/N)
- `en_facturacion` — ¿En proceso de facturación?
- `importe` — Importe base
- `importe_impuestos` — Impuestos
- `importe_divisa`, `importe_divisa_impuestos`
- `estado_venta` — Estado numérico
- `anulada` — ¿Anulada? (S/N)
- `cod_factura_asignada` — Factura asociada
- `tipo_factura_asignada`
- `cod_devolucion` — Devolución origen
- `fecha_entrega` — Fecha entrega
- `tipo_factura_rectificada`, `cod_factura_rectificada`
- `cod_arqueo` — Arqueo TPV
- `peso_total` — Peso total
- `reparto` — ¿Es reparto?
- `cod_ruta` — Ruta
- `cod_transportista`
- `plazo_entrega`
- `portes_pagados`
- `bulbos`
- `cif_recogido_por`
- `cod_turno`
- `cod_promocion`
- `puntos_fidelizacion`
- `dto_pronto_pago`
- `cargo_financiacion`
- `importe_financiacion`
- `version` — Versión del registro
- `historico` — ¿En histórico? (S/N)
- `rowguid`

**Importante:** `tipo_venta` determina el tipo de documento. Los valores típicos:
- 0 = Ticket TPV
- 1 = Albarán
- 2 = Pedido
- (hay que confirmar con datos reales)

---

### 8. `ventas_linea` — Líneas de venta
**PK:** (`cod_venta`, `tipo_venta`, `cod_empresa`, `cod_caja`, `linea`)

**Campos clave:**
- `cod_venta`, `tipo_venta`, `cod_empresa`, `cod_caja` — FK a cabecera
- `linea` — Número de línea
- `cod_articulo` — Artículo (15)
- `descripcion` — Descripción (255)
- `descripcion_abreviada` — Descripción corta (15)
- `cantidad` — Cantidad
- `dto1`, `dto2` — Descuentos
- `precio` — Precio unitario
- `precio_coste` — Precio de coste
- `cargo` — Cargo
- `precio_porcentaje` — Precio con porcentaje
- `precio_recargo` — Recargo
- `precio_impuestos` — Precio con impuestos
- `importe` — Importe línea (cantidad × precio neto)
- `importe_porcentaje`
- `importe_recargo`
- `importe_impuestos`
- `precio_divisa`, `precio_divisa_porcentaje`, `precio_divisa_recargo`, `precio_divisa_impuestos`
- `importe_divisa`, `importe_divisa_porcentaje`, `importe_divisa_recargo`, `importe_divisa_impuestos`
- `cod_impuesto` — Tipo de impuesto
- `porcentaje` — % IVA
- `recargo` — % Recargo
- `inventariable` — ¿Inventariable? (S/N)
- `tipo_precio` — Tipo de precio aplicado
- `cod_unidad` — Unidad (3)
- `kit` — ¿Es kit? (S/N)
- `referencia_cliente` — Referencia del cliente
- `unidades_venta` — Unidades de venta
- `precio_venta_base` — PVB del artículo
- `precio_venta_publico` — PVP del artículo
- `estado_venta` — Estado de la línea
- `cod_barras` — Código de barras
- `descuento_maximo` — Dto. máximo aplicable
- `cod_almacen` — Almacén
- `fecha_entrega` — Fecha de entrega
- `observacion` — Observación
- `peso_unitario`, `peso_total` — Pesos
- `precio_modificado`, `dto1_modificado`, `dto2_modificado` — Flags de modificación
- `cod_tarifa` — Tarifa aplicada
- `version` — Versión del registro
- `rowguid`

---

### 9. `facturas_ventas_cabecera` — Cabecera de facturas de venta
**PK:** (`cod_factura`, `tipo_factura`, `cod_empresa`, `cod_caja`)

**Campos clave:**
- `cod_factura` — Número de factura
- `tipo_factura` — Tipo de factura (smallint)
- `cod_empresa` — Empresa
- `cod_caja` — Caja
- `cod_cliente` — Cliente
- `cod_seccion` — Sección/almacén
- `nombre_seccion` — Denormalizado
- `cif`, `razon_social`, `nombre_comercial` — Datos cliente (denormalizados)
- `direccion1`, `direccion2`, `cp`, `poblacion`, `provincia`
- `cod_idioma`, `cod_pais`
- `cod_divisa`, `cambio`, `cod_divisa_activa`
- `telefono`, `fax`, `e_mail`
- `cod_forma_liquidacion` — Forma de pago
- `dia_pago1`, `dia_pago2`, `dia_pago3`
- `cod_banco`, `nombre_banco`, `direccion_banco1`, `cp_banco`, `poblacion_banco`, `ccc`, `iban`, `swift`
- `impuestos_incluidos` — ¿IVA incluido?
- `importe` — Importe base
- `importe_impuestos` — Impuestos
- `importe_divisa`, `importe_divisa_impuestos`
- `importe_cobrado` — Cobrado
- `importe_divisa_cobrado`
- `factura_oficial` — ¿Es factura oficial?
- `cod_almacen` — Almacén
- `cargo_financiacion`, `importe_financiacion`, `importe_divisa_financiacion`
- `tipo_factura_rectificada`, `cod_factura_rectificada`, `fecha_factura_rectificada`
- `retencion_irpf`, `importe_retencion_irpf`, `importe_divisa_retencion_irpf`
- `criterio_de_caja`
- `cod_arqueo` — Arqueo
- `hasp` — Referencia HASP
- `cod_primera_venta`, `cod_ultima_venta` — Primera/última venta asociada
- `tipo_operacion_iva`
- `cod_proveedor_otorgado`
- `cod_forma_liquidacion_facturae`
- `referencia_contrato_facturae`
- `dir3_organo_proponente` y dirección — Órgano proponente
- `rowguid`

**Observación:** No tiene campo `fecha_factura` visible en la primera pasada; hay que verificar si está más adelante en la tabla.

---

### 10. `facturas_ventas` — Relación facturas-ventas
Tabla puente que relaciona facturas con los documentos de venta (albaranes/tickets) que las componen.

---

### 11. `stocks` — Stock actual por almacén
**PK:** (`cod_almacen`, `cod_articulo`)

- `cod_almacen` — Almacén
- `cod_articulo` — Artículo
- `existencias` — Stock actual
- `fecha_ultima_entrada` — Última entrada
- `fecha_ultima_salida` — Última salida
- `maximos` — Stock máximo
- `minimos` — Stock mínimo
- `ubicacion` — Ubicación en almacén
- `fecha_ultimo_inventario`
- `hora_ultimo_inventario`
- `permitir_venta_bajo_stock`
- `cantidad_pendiente_servir` — Pendiente de servir
- `cantidad_pendiente_recibir` — Pendiente de recibir

---

### 12. `movimiento_stock` — Movimientos de stock
**PK:** `cod_movimiento` (uniqueidentifier)

- `cod_articulo` — Artículo
- `cod_empresa` — Empresa
- `cod_documento` — Documento origen
- `tipo_documento` — Tipo documento
- `cod_caja` — Caja
- `operacion` — 'E'ntrada / 'S'alida (varchar 1)
- `cod_cliente` — Cliente (si es venta)
- `cod_proveedor` — Proveedor (si es compra)
- `fecha` — Fecha
- `hora` — Hora
- `cod_almacen` — Almacén
- `cantidad` — Cantidad movida
- `linea` — Línea del documento
- `linea_kit` — Línea del kit
- `rowguid`

---

## Relaciones principales

```
clientes (cod_cliente)
    ↑
    └─ ventas_cabecera (cod_cliente)
           ├─ ventas_linea (cod_venta, tipo_venta, cod_empresa, cod_caja)
           │       └─ articulos (cod_articulo)
           │              ├─ familias (cod_familia)
           │              └─ subfamilias (cod_familia, cod_subfamilia)
           └─ facturas_ventas (cod_venta) → facturas_ventas_cabecera (cod_factura)

vendedores (cod_vendedor)
    ↑
    └─ clientes (cod_vendedor)
    └─ ventas_cabecera (cod_vendedor)

almacenes (cod_almacen)
    ↑
    ├─ ventas_cabecera (cod_almacen)
    ├─ stocks (cod_almacen)
    └─ movimiento_stock (cod_almacen)
```

## Campos de auditoría comunes

Todas las tablas tienen:
- `rowguid` — GUID para replicación merge de SQL Server

## Observaciones críticas para el importador

1. **Claves compuestas:** `ventas_cabecera` y `ventas_linea` usan PK compuesta de 4 campos: (`cod_venta`, `tipo_venta`, `cod_empresa`, `cod_caja`). En Laravel necesitaremos una clave sintética `id` auto-incremental y mantener estos campos como datos.

2. **Denormalización extrema:** Las cabeceras de venta y factura copian literalmente todos los datos del cliente en el momento de la venta. Esto es típico de ERPs antiguos pero facilita los informes históricos.

3. **Divisas:** Hay campos duplicados en divisa local y divisa activa. Para estadísticas usaremos la divisa local (EUR).

4. **IVA incluido:** El campo `impuestos_incluidos` indica si los importes de cabecera incluyen o no IVA. Las líneas tienen separado `importe` (base) e `importe_impuestos`.

5. **Stock:** La tabla `stocks` da el snapshot actual. `movimiento_stock` da el historial completo.

6. **Tipos de documento:** `tipo_venta` en `ventas_cabecera` determina si es ticket (0), albarán (1), pedido, etc. Hay que mapear estos valores con datos reales.
