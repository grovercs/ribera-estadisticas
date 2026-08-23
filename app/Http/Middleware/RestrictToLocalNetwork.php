<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictToLocalNetwork
{
    /**
     * Nodos Tailscale autorizados por defecto.
     *
     * @var string[]
     */
    private const DEFAULT_TAILSCALE_IPS = [
        '100.69.167.99',   // desarrollo
        '100.109.58.111',  // direccion1
        '100.122.223.122', // casavielha
        '100.98.110.10',   // iaserver
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip();

        if (!$this->isAuthorizedIp($clientIp)) {
            \Log::warning('Unauthorized API access attempt to local endpoint', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Valida si la IP del cliente coincide con la whitelist estricta.
     */
    private function isAuthorizedIp(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        // 1. Loopback local
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        // 2. LAN Ribera (192.168.1.0/24)
        if (str_starts_with($ip, '192.168.1.')) {
            return true;
        }

        // 3. LAN Desarrollo (192.168.200.0/24)
        if (str_starts_with($ip, '192.168.200.')) {
            return true;
        }

        // 4. Whitelist estricta de nodos Tailscale conocidos
        if (in_array($ip, self::DEFAULT_TAILSCALE_IPS, true)) {
            return true;
        }

        // 5. IPs adicionales configuradas opcionalmente vía variable de entorno LOCAL_API_ALLOWED_IPS
        $extraIpsEnv = env('LOCAL_API_ALLOWED_IPS');
        if (!empty($extraIpsEnv) && is_string($extraIpsEnv)) {
            $extraIps = array_filter(array_map('trim', explode(',', $extraIpsEnv)));
            if (in_array($ip, $extraIps, true)) {
                return true;
            }
        }

        // Rechazar cualquier otra IP (incluyendo IPs no autorizadas dentro del espacio 100.64.0.0/10)
        return false;
    }
}
