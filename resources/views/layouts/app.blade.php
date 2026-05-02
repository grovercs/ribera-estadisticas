<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Ribera Estadísticas')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fc; color: #191c1e; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid #e1e2e6;
            box-shadow: 0 4px 20px rgba(45, 46, 46, 0.05);
        }
        .nav-active {
            background: #ffffff;
            color: #206393;
            border-left: 4px solid #206393;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
    @stack('styles')
</head>
<body class="flex h-screen overflow-hidden antialiased">

    <!-- Sidebar -->
    <aside class="flex flex-col fixed left-0 top-0 h-full z-50 bg-[#f8f9fc] border-r border-[#e1e2e6] w-64 hidden md:flex text-sm font-semibold">
        <!-- Header -->
        <div class="flex items-center gap-3 p-6 border-b border-[#e1e2e6]">
            <div class="w-10 h-10 rounded bg-[#181919] flex items-center justify-center text-white">
                <span class="material-symbols-outlined">domain</span>
            </div>
            <div>
                <h1 class="text-lg font-bold text-[#191c1e] tracking-tight leading-tight">Ribera</h1>
                <p class="text-xs text-[#747878] font-normal">Estadísticas</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 py-3 px-4 rounded-r-lg transition-all duration-100 {{ request()->routeIs('dashboard') ? 'nav-active' : 'text-[#747878] hover:text-[#191c1e] hover:bg-[#f2f3f7]' }}">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('sales') }}" class="flex items-center gap-3 py-3 px-4 rounded-r-lg transition-all duration-100 {{ request()->routeIs('sales') ? 'nav-active' : 'text-[#747878] hover:text-[#191c1e] hover:bg-[#f2f3f7]' }}">
                <span class="material-symbols-outlined">analytics</span>
                <span>Ventas</span>
            </a>
            <a href="{{ route('stock') }}" class="flex items-center gap-3 py-3 px-4 rounded-r-lg transition-all duration-100 {{ request()->routeIs('stock') ? 'nav-active' : 'text-[#747878] hover:text-[#191c1e] hover:bg-[#f2f3f7]' }}">
                <span class="material-symbols-outlined">inventory_2</span>
                <span>Stock</span>
            </a>
            <a href="{{ route('clients') }}" class="flex items-center gap-3 py-3 px-4 rounded-r-lg transition-all duration-100 {{ request()->routeIs('clients') ? 'nav-active' : 'text-[#747878] hover:text-[#191c1e] hover:bg-[#f2f3f7]' }}">
                <span class="material-symbols-outlined">people</span>
                <span>Clientes</span>
            </a>
            <a href="{{ route('reports.comparison') }}" class="flex items-center gap-3 py-3 px-4 rounded-r-lg transition-all duration-100 {{ request()->routeIs('reports.comparison') ? 'nav-active' : 'text-[#747878] hover:text-[#191c1e] hover:bg-[#f2f3f7]' }}">
                <span class="material-symbols-outlined">query_stats</span>
                <span>Comparativa Histórica</span>
            </a>
        </nav>

        <!-- Footer -->
        <div class="p-4 border-t border-[#e1e2e6] space-y-1">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 text-[#747878] hover:text-[#191c1e] hover:bg-[#f2f3f7] py-2 px-4 rounded-lg transition-colors text-left">
                    <span class="material-symbols-outlined">logout</span>
                    <span>Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col md:ml-64 relative min-w-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center w-full h-16 px-6 bg-white border-b border-[#e1e2e6] shadow-sm z-40 flex-shrink-0">
            <div class="flex items-center flex-1 gap-6">
                <h2 class="text-xl font-black tracking-tight text-[#191c1e] md:hidden">Ribera</h2>
                <div class="hidden md:flex items-center max-w-md w-full bg-[#f8f9fc] border border-[#e1e2e6] rounded-full px-4 py-1.5">
                    <span class="material-symbols-outlined text-[#747878] text-[20px] mr-2">search</span>
                    <input class="bg-transparent border-none outline-none w-full text-sm text-[#191c1e] placeholder-[#747878] p-0" placeholder="Buscar..." type="text">
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right hidden md:block">
                    <p class="text-sm font-semibold text-[#191c1e]">{{ Auth::user()?->name ?? 'Usuario' }}</p>
                    <p class="text-xs text-[#747878]">{{ Auth::user()?->email }}</p>
                </div>
                <div class="h-8 w-8 rounded-full bg-[#206393] text-white flex items-center justify-center font-bold text-sm">
                    {{ substr(Auth::user()?->name ?? 'U', 0, 1) }}
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-6 bg-[#f8f9fc] relative z-0">
            <div class="max-w-[1440px] mx-auto space-y-6">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
