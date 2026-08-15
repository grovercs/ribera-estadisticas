# Plan de Implementación: FASE 4 — FRONTEND NEXT.JS (Dashboard & Auth) - ACTUALIZADO

Este plan describe la arquitectura, estructura de archivos y pasos detallados para construir el nuevo frontend en **Next.js (App Router)** y **TypeScript** consumiendo los datos de **Supabase Cloud** de forma segura y eficiente en el lado del servidor, logrando paridad económica de 0.00 € con Laravel.

---

## 1. Decisiones de Arquitectura y Tecnologías

1.  **Framework:** Next.js con **App Router** (`app/` directory) bajo la subcarpeta `frontend/`.
2.  **Seguridad y Sesión (Servidor y Cookies):**
    *   Se instalará `@supabase/ssr` para configurar la autenticación en el lado del servidor usando cookies persistentes.
    *   La sesión se verificará tanto en el lado del cliente como del servidor.
    *   Un Middleware de Next.js o Server Components protegerán de forma estricta las rutas privadas (redirección a `/login` si no hay sesión).
3.  **Variables de Entorno Públicas:**
    *   `NEXT_PUBLIC_SUPABASE_URL`
    *   `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY` (Publishable Key moderna del proyecto Supabase).
    *   *Nota:* Cero secretos y contraseñas administrativas en el frontend.
4.  **Estrategia de Renderizado (Server Components):**
    *   `/dashboard/page.tsx` será un **Server Component por defecto**. Obtendrá los datos de Supabase vía fetch/SSR y los renderizará estáticamente.
    *   Solo los elementos interactivos (gráficos Chart.js, selectores de períodos de filtros y el botón de alternancia de stock) se delegarán a **Client Components** independientes e hidratados.
5.  **Optimización de Consultas (PostgreSQL RPC):**
    Para evitar descargar cientos de miles de registros a Next.js para calcular acumulados, crearemos **funciones RPC (Remote Procedure Call)** en PostgreSQL de Supabase. PostgreSQL procesará las sumas en milisegundos usando índices y Next.js solo recibirá los 10 registros agregados.

---

## 2. Esquema de Consultas RPC de PostgreSQL en Supabase

Crearemos las siguientes funciones RPC en el esquema `public` de Supabase:

1.  **`get_dashboard_kpis(p_year_from int, p_month_from int, p_year_to int, p_month_to int)`**
    *   Devuelve: `total_sales` (decimal), `total_orders` (int), `avg_ticket` (decimal), `pending_amount` (decimal), `unique_clients` (int).
2.  **`get_dashboard_sales_evolution(p_year_from int, p_month_from int, p_year_to int, p_month_to int)`**
    *   Devuelve meses con ventas del período actual y comparativa del año anterior (`year`, `month`, `total_sales`, `prev_total_sales`).
3.  **`get_dashboard_sales_by_warehouse(p_year_from int, p_month_from int, p_year_to int, p_month_to int)`**
    *   Devuelve: `cod_almacen` (text), `orders_count` (int), `total_sales` (decimal).
4.  **`get_dashboard_top_sellers(p_year_from int, p_month_from int, p_year_to int, p_month_to int)`**
    *   Devuelve: `cod_vendedor` (text), `nombre_vendedor` (text), `orders_count` (int), `total_sales` (decimal).
5.  **`get_dashboard_top_clients(p_year_from int, p_month_from int, p_year_to int, p_month_to int)`**
    *   Devuelve: `cod_cliente` (text), `razon_social` (text), `poblacion` (text), `provincia` (text), `total_spent` (decimal), `vendedor_principal` (text).
6.  **`get_dashboard_top_products(p_year_from int, p_month_from int, p_year_to int, p_month_to int, p_hide_no_stock boolean)`**
    *   Devuelve: `cod_articulo` (text), `descripcion` (text), `total_qty` (decimal), `total_revenue` (decimal), `stock_total` (decimal).
7.  **`get_dashboard_top_families(p_year_from int, p_month_from int, p_year_to int, p_month_to int)`**
    *   Devuelve: `cod_familia` (text), `family_name` (text), `total` (decimal).

---

## 3. Estructura de Carpetas del Frontend

```text
frontend/
├── src/
│   ├── app/
│   │   ├── (protected)/
│   │   │   ├── layout.tsx          # Layout protegido: verifica sesión por SSR, renderiza sidebar y header
│   │   │   └── dashboard/
│   │   │       └── page.tsx        # Server Component principal del Dashboard
│   │   ├── login/
│   │   │   └── page.tsx            # Formulario de login interactivo
│   │   ├── layout.tsx              # Root Layout general
│   │   └── globals.css
│   ├── components/
│   │   ├── ui/                     # Componentes shadcn/ui
│   │   ├── dashboard/
│   │   │   ├── filters.tsx         # Client Component para interactuar con el rango de meses
│   │   │   ├── kpi-grid.tsx        # Grid de tarjetas KPI
│   │   │   ├── charts-section.tsx  # Client Component para renderizar los 3 gráficos Chart.js
│   │   │   └── tables-section.tsx  # Client Component para el Top 10 Clientes, Vendedores y Productos
│   │   ├── sidebar.tsx             # Menú de navegación lateral
│   │   └── header.tsx              # Barra superior con logout e indicador de última actualización
│   └── lib/
│       ├── supabase/
│       │   ├── client.ts           # Cliente Supabase del navegador
│       │   └── server.ts           # Cliente Supabase del servidor (con cookies y soporte SSR)
│       └── utils.ts                # cn utility
```

---

## 4. Plan de Ejecución

### Paso 1: Auditoría Visual de la App Laravel Actual
*   Inspeccionaremos las pantallas de login, sidebar, distribución de tarjetas y gráficos para obtener un modelo exacto de interfaz corporativa limpia y profesional.

### Paso 2: Crear el DDL SQL de las funciones RPC
*   Escribiremos un script con las definiciones de las 7 funciones RPC descritas y las aplicaremos en PostgreSQL de Supabase mediante nuestra conexión administrativa existente.

### Paso 3: Setup de Next.js y Supabase SSR
1.  Inicializar la app en `frontend/` mediante `create-next-app` de forma automática.
2.  Documentar la versión de Next.js instalada en `README.md` (o en nuestro reporte).
3.  Instalar dependencias: `@supabase/ssr`, `@supabase/supabase-js`, `lucide-react`, `chart.js`, `react-chartjs-2`.
4.  Crear `client.ts` y `server.ts` bajo `src/lib/supabase/` configurando la persistencia de cookies.
5.  Inicializar componentes básicos de shadcn/ui.

### Paso 4: Autenticación Basada en Cookies
1.  Crear la página `/login` con Supabase Auth.
2.  Implementar la redirección en el layout de Next.js: si no existe sesión en el servidor, redirigir a `/login`. Si existe, permitir el acceso.

### Paso 5: Implementación de Layout y Dashboard
1.  Crear barra lateral (`sidebar.tsx`) y cabecera (`header.tsx`) que lea la fecha de última actualización del `sync_state`.
2.  Construir `/dashboard` como Server Component, obteniendo los datos filtrados en paralelo usando las funciones RPC de Supabase.
3.  Implementar gráficos, tablas y filtros interactivos como Client Components hidratados con los datos del Server Component.

---

## 5. Verificación de Paridad al Céntimo (Tolerancia 0.00 €)

1.  Contrastar las cifras de tarjetas KPI y tablas del dashboard actual de Laravel contra el dashboard Next.js.
2.  **KPIs a conciliar:** Ventas totales, ticket medio, pendiente cobro, cantidad de pedidos y clientes únicos.
3.  **Tablas a conciliar:** Facturación de los almacenes, vendedores y clientes líderes.
4.  Ejecutar `npm run build` en el frontend para asegurar consistencia del compilador estricto de TypeScript.
