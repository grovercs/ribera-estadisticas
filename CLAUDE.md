# Claude Code — Instrucciones de proyecto

## Autonomía y permisos
- Una vez que el usuario da una instrucción concreta (ej. "adelante con X"), proceder sin pedir aprobación en cada sub-paso.
- Si una tarea se puede descomponer en pasos internos, ejecutarlos directamente.
- Al completar la tarea asignada, detenerse y preguntar: "¿Con qué seguimos?" o presentar opciones claras.
- Solo pedir confirmación antes de acciones destructivas, irreversibles o que afecten a sistemas compartidos (borrar datos, push forzado, etc.).

## Stack
- Laravel 13 + PHP 8.4 + MySQL 8
- Tailwind CSS + Chart.js
- Conexión ERP: SQL Server via pdo_sqlsrv (host 192.168.200.105, DB INTEGRAL)
