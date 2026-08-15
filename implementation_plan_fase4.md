# Plan de Implementación: FASE 4 — FRONTEND NEXT.JS (Dashboard & Auth)

Este plan describe la arquitectura, estructura de archivos y pasos detallados para construir el nuevo frontend en **Next.js 15 (App Router)** y **TypeScript** consumiendo los datos sincronizados en **Supabase Cloud**, reproduciendo con paridad al céntimo el dashboard actual de Laravel.

---

## 1. Decisiones de Arquitectura y Tecnologías

1.  **Framework:** Next.js con **App Router** (`app/` directory) bajo la carpeta `frontend/` para coexistir con Laravel.
2.  **Seguridad y Sesión:** **Supabase Auth** para gestionar usuarios, redirecciones y sesión persistente.
3.  **Estilos:** **Tailwind CSS** para un diseño moderno, y **shadcn/ui** como librería de componentes (Select, Card, Button, Table, etc.).
4.  **Gráficos:** **Chart.js** encapsulado con `react-chartjs-2`.
5.  **Variables de Entorno:**
    *   `NEXT_PUBLIC_SUPABASE_URL`: URL pública de Supabase.
    *   `NEXT_PUBLIC_SUPABASE_ANON_KEY`: API Key anónima pública de Supabase.
    *   *Nota:* No se utilizarán secretos de administración (service keys) ni passwords de base de datos en el cliente.

---

## 2. Estructura de Carpetas Propuesta

El frontend residirá bajo la carpeta `frontend/` en la raíz del proyecto actual:

```text
frontend/
├── src/
│   ├── app/
│   │   ├── (auth)/
│   │   │   └── login/
│   │   │       └── page.tsx        # Página de login Supabase Auth
│   │   │   layout.tsx              # Layout para rutas privadas (verifica sesión, barra lateral, cabecera)
│   │   │   page.tsx                # Redirecciona a /dashboard
│   │   │   dashboard/
│   │   │       └── page.tsx        # Página principal del Dashboard (Client Component para interacción)
│   │   │   globals.css
│   │   ├── components/
│   │   │   ├── ui/                 # Componentes de shadcn/ui (button, card, select, table)
│   │   │   ├── sidebar.tsx         # Menú de navegación lateral
│   │   │   ├── header.tsx          # Cabecera superior con información de usuario y logout
│   │   │   └── charts/             # Componentes Chart.js (SalesLineChart, WarehousePieChart, FamilyBarChart)
│   │   ├── lib/
│   │   │   ├── supabase.ts         # Cliente Supabase JS inicializado con variables de entorno
│   │   │   └── utils.ts            # Utilidades de Tailwind/shadcn (cn)
```

---

## 3. Estrategia de Autenticación y Seguridad

*   Se usará la librería oficial `@supabase/supabase-js`.
*   **Protección de Rutas:** Se implementará un componente Wrapper de Autenticación o un control en el Layout principal (`app/layout.tsx` o un layout de grupo `(protected)/layout.tsx`) que escuche `supabase.auth.onAuthStateChange()`.
*   Si no hay sesión activa, redirigirá inmediatamente a `/login`.
*   Si el usuario está autenticado, accederá a la aplicación. Las políticas de **Row Level Security (RLS)** de Supabase denegarán cualquier lectura si el rol del usuario no tiene permisos sobre el esquema.

---

## 4. Diseño del Dashboard y Paridad de Datos

Para replicar los datos del dashboard de Laravel, Next.js realizará consultas optimizadas a Supabase:
*   **KPI Cards:** `select * from stats_kpis where period_key = 'year_actual'` (batch activo).
*   **Ventas por Almacén:** `select cod_almacen, total_sales, orders_count from stats_warehouses`.
*   **Ventas por Vendedor:** `select cod_vendedor, nombre_vendedor, total, orders from stats_sellers`.
*   **Evolución Mensual (Línea):** `select year, month, revenue, orders_count from stats_sales_monthly`.
*   **Top Clientes:** Query a `clients_reporting` filtrando/ordenando por volumen.
*   **Top Productos:** Query a `products_stock` y agregados de ventas.

---

## 5. Plan de Ejecución Detallado

### FASE 4B: Crear Next.js y Setup Inicial
1.  Ejecutar la creación de la app Next.js en `frontend/` mediante `create-next-app` con TypeScript, Tailwind y Src folder.
2.  Configurar e instalar dependencias necesarias: `@supabase/supabase-js`, `lucide-react`, `chart.js`, `react-chartjs-2`, `clsx`, `tailwind-merge`.
3.  Inicializar shadcn/ui y componentes básicos (Card, Select, Table, Button).
4.  Crear y configurar el archivo `.env.local` en `frontend/`.

### FASE 4C: Implementar Supabase Auth
1.  Crear la página `/login` en Next.js.
2.  Implementar formulario con control de estados (`loading`, `error`, `success`).
3.  Configurar la persistencia de sesión y redirección automática en el layout.

### FASE 4D: Layout Principal
1.  Crear la barra lateral (`sidebar.tsx`) con la navegación coincidente de Laravel.
2.  Crear la barra superior (`header.tsx`) mostrando el nombre del usuario y botón de cerrar sesión.
3.  Consultar `sync_state` de la base de datos para mostrar en la cabecera: *"Última actualización: [fecha/hora]"*.

### FASE 4E: Pantalla del Dashboard
1.  Implementar la página `/dashboard`.
2.  Escribir los componentes de Chart.js con `react-chartjs-2` garantizando la visualización exacta.
3.  Replicar los filtros de período por fecha (Desde año/mes hasta año/mes) y el toggle de stock para productos.
4.  Realizar pruebas de carga e interfaz responsive (móvil, tablet, desktop).

---

## 6. Plan de Verificación y Paridad Económica

1.  **Compilación Exitosa:** Ejecutar `npm run build` en el frontend para certificar cero errores de TypeScript y empaquetado.
2.  **Validación Numérica (Tolerancia 0.00 €):**
    *   Contrastar las cifras de las tarjetas KPI y tablas del dashboard actual de Laravel contra el dashboard Next.js.
    *   **KPIs a conciliar:** Ventas totales, ticket medio, pendiente cobro, cantidad de pedidos y clientes únicos.
    *   **Tablas a conciliar:** Facturación de los almacenes, vendedores y clientes líderes.
3.  **Verificación Responsiva:** Probar visualmente en el navegador la adaptabilidad en dispositivos móviles y portátiles.
