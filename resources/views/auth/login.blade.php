<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - Ribera Estadísticas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&amp;display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-[#f8f9fc]">
    <div class="w-full max-w-md p-8">
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-xl bg-[#181919] flex items-center justify-center text-white mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-[#191c1e]">Ribera Estadísticas</h1>
            <p class="text-sm text-[#747878] mt-1">Sistema de inteligencia de negocio</p>
        </div>

        <div class="glass-card rounded-2xl p-8 bg-white shadow-sm border border-[#e1e2e6]">
            <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-[#191c1e] mb-1">Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full px-4 py-2.5 rounded-lg border border-[#e1e2e6] bg-[#f8f9fc] text-[#191c1e] placeholder-[#747878] focus:outline-none focus:ring-2 focus:ring-[#206393] focus:border-transparent transition-all"
                        placeholder="admin@ribera.com">
                    @error('email')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#191c1e] mb-1">Contraseña</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2.5 rounded-lg border border-[#e1e2e6] bg-[#f8f9fc] text-[#191c1e] placeholder-[#747878] focus:outline-none focus:ring-2 focus:ring-[#206393] focus:border-transparent transition-all"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-[#747878]">
                        <input type="checkbox" name="remember" class="rounded border-[#e1e2e6] text-[#206393] focus:ring-[#206393]">
                        Recordarme
                    </label>
                </div>

                <button type="submit"
                    class="w-full bg-[#181919] hover:bg-[#2d2e2e] text-white font-semibold py-2.5 rounded-lg transition-colors">
                    Iniciar sesión
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-[#747878]">
                <p>Demo: <span class="font-mono text-xs bg-[#f2f3f7] px-2 py-1 rounded">admin@ribera.com / password</span></p>
            </div>
        </div>
    </div>
</body>
</html>
