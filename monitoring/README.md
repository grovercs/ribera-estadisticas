# Avisos por ausencia de señal de Direccion1

Estado: código preparado para despliegue y prueba real. No se activa por añadir
los archivos. El monitor permanece deshabilitado salvo que
`RIBERA_MONITOR_ENABLED` sea exactamente `true`.

## Qué comprueba

La tarea existente `Ribera Sync - Process Manual Requests` ejecuta un pequeño
emisor al terminar cada ciclo. No requiere abrir el móvil. Netlify recibe un
POST autenticado y guarda **su propia hora** en un almacén persistente de
Netlify Blobs, separado del snapshot y de Supabase. Una función programada cada
minuto consulta ese almacén con consistencia fuerte.

Tras cinco minutos sin señal, envía un correo de incidencia. Envía un segundo
correo al recibir de nuevo la señal. La comprobación por minuto añade hasta un
minuto al umbral: la detección nominal será entre cinco y seis minutos, más el
tiempo de entrega del correo. Un fallo explícito del proveedor de correo se
reintenta en la siguiente comprobación. El identificador de cada envío es
estable para reducir duplicados ante reintentos.

La primera comprobación habilitada inicia un margen de cinco minutos aunque
nunca se haya recibido señal; no puede permanecer sin configurar indefinidamente
mostrándose sano. Durante ese margen, sin señal, el estado es `arming`.

Esta fase detecta la ausencia de comunicación: apagado, bloqueo, corte de
Internet, tarea detenida o conexión con el receptor fallida. No permite
distinguir por sí sola esas causas. **No verifica Integral, las fórmulas ni la
publicación del snapshot.** Un ciclo que falla al publicar también puede emitir
su señal de ejecución. Esa monitorización de resultado es una fase separada.

## Componentes

- `frontend/netlify/functions/direccion1-heartbeat.mjs`: receptor POST; requiere
  clave Bearer aleatoria de al menos 32 caracteres; no expone estado por GET.
- `frontend/netlify/functions/direccion1-monitor.mjs`: comprobación programada;
  no es un endpoint HTTP público ejecutable por URL.
- `monitoring/heartbeat.mjs`: lógica, estado de incidencias y envío por Resend.
- `scripts/send-direccion1-heartbeat.ps1`: emisor con tiempo máximo de ocho
  segundos. No imprime claves ni contenido de la petición.
- `scripts/install-direccion1-heartbeat.ps1`: instala el emisor en el lanzador
  **ya validado**, preservando el comando y código de salida de sincronización.

## Configuración en Netlify

Publicar desde el repositorio tras revisar el cambio. La base de compilación
continúa siendo `frontend`; el directorio de funciones se resuelve dentro de esa
base. Las dos funciones se empaquetan con esbuild. `@netlify/blobs` se añade a
las dependencias y al lockfile; no se modifica código Next.js ni Laravel.

Configurar estas variables **solo en el contexto Production, con ámbito de
Functions** (o ámbito general si el plan no permite distinguirlo). No ponerlas
en `netlify.toml`, en código, ni con prefijo `NEXT_PUBLIC_`:

| Variable | Valor |
| --- | --- |
| `RIBERA_MONITOR_ENABLED` | `false` durante instalación; `true` al activar |
| `RIBERA_HEARTBEAT_SECRET` | Clave aleatoria de al menos 32 caracteres, compartida solo con Direccion1 |
| `RIBERA_ALERT_TO` | `grovercs@gmail.com` |
| `RIBERA_ALERT_FROM` | Remitente autorizado en Resend |
| `RIBERA_RESEND_API_KEY` | Clave de envío de Resend; introducirla directamente en Netlify |

El destinatario no necesita facilitar su contraseña. El envío requiere una
cuenta de Resend y un remitente autorizado. Su dominio de prueba solo admite
destinatarios permitidos por Resend; para uso normal configurar un dominio
verificado. Comprobar los límites de la cuenta. Las invocaciones y el
almacenamiento también consumen los recursos del plan de Netlify.

No introducir claves de API ni la clave de la señal en conversaciones,
capturas, parámetros de línea de comandos ni control de versiones.

## Instalación en Direccion1

1. Confirmar que el despliegue de producción contiene ambas funciones y que
   `direccion1-monitor` aparece como Scheduled. Mantener los avisos desactivados.
2. Copiar los dos scripts PowerShell a la carpeta `scripts` del proyecto de
   Direccion1. No reemplazar su `.env` ni su lanzador validado por el lanzador
   antiguo del repositorio que apunta a WAMP.
3. Abrir PowerShell como administrador en Direccion1 y ejecutar:

   ```powershell
   & 'C:\Proyectos\antigravity\ribera-estadisticas\scripts\install-direccion1-heartbeat.ps1' `
       -HeartbeatUrl 'https://TU-SITIO.netlify.app/.netlify/functions/direccion1-heartbeat'
   ```

   Introducir la misma clave de Netlify en el prompt oculto. No se imprime.
   Se prueba el receptor antes de modificar el lanzador. El secreto local se
   almacena fuera del repositorio, en
   `%ProgramData%\VielhaComputer\Ribera\heartbeat.json`, con acceso para SYSTEM
   y administradores. El lanzador previo se respalda en la misma carpeta.
   Si una ejecución está activa, el instalador aborta y vuelve a habilitar la
   tarea; esperar a que termine y repetir.
4. Esperar dos ciclos. Verificar en Netlify Blobs que
   `ribera-availability / direccion1/heartbeat` avanza sin pulsar el móvil.
   Consultar `storage\logs\heartbeat.log` si no avanza. No dar por válida solo
   la señal inicial enviada por el instalador con la cuenta del administrador:
   debe comprobarse una señal nueva de la tarea que corre con SYSTEM.
5. Activar `RIBERA_MONITOR_ENABLED=true`, aplicar las variables y desplegar
   según las indicaciones de Netlify. Ejecutar Run now en el monitor para
   verificar configuración y ver `healthy` en sus registros.

## Prueba real de aviso y recuperación

No apagar el ordenador del cliente ni detener el publicador. Para simular la
ausencia de señal manteniendo la sincronización operativa, renombrar
temporalmente `heartbeat.json` a `heartbeat.json.testpause` en ProgramData.
El emisor no podrá enviar señales, pero su fallo no cambia el código de salida
de la sincronización. Esperar seis minutos y confirmar la recepción del correo
de incidencia en el buzón indicado. Restaurar inmediatamente el nombre original
y confirmar el correo de recuperación tras los siguientes ciclos.

Comprobar además que no se repite el correo durante la misma incidencia y que
un reinicio de Direccion1 recupera las señales. La prueba de correo y la
ejecución como SYSTEM deben validarse en producción; las pruebas locales no
demuestran entrega real. No activar un plan de pago para estas pruebas.

## Reversión

Desactivar `RIBERA_MONITOR_ENABLED` en Netlify y aplicar el cambio. En Direccion1,
restaurar el `.bat.backup` de ProgramData sobre el lanzador solo si no ha sufrido
otras modificaciones posteriores; si las hay, retirar únicamente la línea que
llama a `send-direccion1-heartbeat.ps1`. Mantener la tarea de sincronización
habilitada. El fallo o reversión del monitor no debe detener la publicación.

## Validación de desarrollo

```sh
node --test monitoring/heartbeat.test.mjs
node --check frontend/netlify/functions/direccion1-heartbeat.mjs
node --check frontend/netlify/functions/direccion1-monitor.mjs
```

Las pruebas simulan almacenamiento, reloj y correo. No envían correos reales.
Incluyen autenticación, reloj del receptor, primer arranque sin señales,
umbral, supresión de duplicados, recuperación, reintento del correo y modo
deshabilitado. Los scripts de Windows requieren prueba real en Direccion1.
