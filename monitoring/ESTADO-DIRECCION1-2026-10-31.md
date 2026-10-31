# Ribera Estadísticas: estado verificado el 31/10/2026

Este documento corresponde a `grovercs/ribera-estadisticas`, no a Fernaguez ni
a Gestión Logística. Recoge los resultados aportados por el operador en la
sesión de soporte; no implica acceso remoto del autor a los equipos.

## Corrección aplicada y comprobada en Windows

- Direccion1 tiene la tarea `Ribera Sync - Process Manual Requests`, habilitada,
  ejecutada con SYSTEM, cada minuto y al arrancar Windows (retraso de un minuto).
- El lanzador es
  `C:\Proyectos\antigravity\ribera-estadisticas\scripts\sync-manual-worker-direccion1.bat`.
- Utiliza el PHP instalado en Direccion1, obtenido de la configuración de
  `RiberaLaravel` (WinGet PHP 8.4.24 en la intervención). El PHP de WAMP al que
  apuntaba el script antiguo del repositorio no existe en ese equipo.
- La tarea manual equivalente del ordenador de desarrollo quedó deshabilitada.
- El 31/10/2026 a las 12:35 se comprobó una solicitud real publicada por la
  tarea de Direccion1, con cálculo de 1453,67 ms y salida 0.
- Después de reiniciar Direccion1 sin iniciar el publicador manualmente, se
  comprobó otra solicitud: inicio 12:48:00, snapshot generado a las
  `2026-10-31T11:48:20.729712Z` (12:48:20 Europe/Madrid), cálculo de 1697,42 ms
  y salida 0 a las 12:48:21.

La nueva utilidad `scripts/install-direccion1-sync-worker.ps1` reproduce esa
configuración para una instalación futura. Es código nuevo preparado a partir
de los pasos validados, no un instalador ya ejecutado en el equipo. No debe
ejecutarse sobre la instalación actual: se niega a sobrescribir una tarea o
un lanzador existentes. Crea la tarea deshabilitada para permitir un cambio
controlado desde otro equipo sin dos publicadores activos.

La tarea procesa solicitudes manuales de dashboard y ventas, como el comando
original; no se modificó su lógica de negocio. `MultipleInstances IgnoreNew`
evita solapamientos de esta tarea en este equipo; no es un bloqueo distribuido.

## Pendiente, no presentar como resuelto

- `Ribera Sync - Sales Current Month` sigue en desarrollo; no se migró la
  sincronización periódica de ventas en esta intervención.
- El código Laravel sigue devolviendo al móvil el genérico «código 1».
- No se han implementado reintentos del cálculo/publicación del snapshot.
- Los antecedentes de timeout SQL Server y errores intermitentes de Supabase
  no se han eliminado mediante cambios de código.
- La vigilancia por señal y correo está en una propuesta separada del mismo
  repositorio, desactivada por defecto. Falta desplegar, configurar remitente
  y claves en Netlify, instalar el emisor en Direccion1 y probar recepción real.

## Archivos y configuración que deben conservarse

Versionar los instaladores y la documentación. Las claves de heartbeat,
credenciales de correo, `.env`, registros y configuración privada de Windows
permanecen fuera del repositorio. No reemplazar el lanzador operativo por
`scripts/sync-manual-worker.bat` sin adaptarlo: ese archivo histórico conserva
la ruta de WAMP del entorno de desarrollo.
