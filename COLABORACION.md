# Repositorio de Logística

Repositorio independiente y privado, propiedad de la cuenta personal `Yasuo05`:

https://github.com/Yasuo05/erp-bayer-logistica

La base del equipo y su documentación se conservan. La versión inicial incluye la integración de Logística y las 449 comprobaciones verificadas el 22 de noviembre de 2026; el informe completo está en `docs/logistica/PRUEBAS_Y_SITUACIONES_2026-11-22.md`.

## Incorporarse al trabajo

Alisson debe aceptar la invitación enviada por GitHub al correo indicado y después clonar el repositorio:

```powershell
git clone https://github.com/Yasuo05/erp-bayer-logistica.git
cd erp-bayer-logistica
```

Para preparar el entorno local con XAMPP y Windows PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\iniciar-logistica-local.ps1
```

Los archivos `.env`, la base de ejecución `.runtime` y las dependencias generadas no se comparten por Git. Cada computadora conserva su propia configuración. Las instrucciones de inicio y las cuentas de ejemplo están en `LEEME_LOGISTICA.md`.

## Trabajar sin pisar cambios

Antes de una tarea nueva:

```powershell
git switch main
git pull --ff-only
git switch -c alisson/nombre-de-la-tarea
```

Al terminar, revisa `git diff`, ejecuta las pruebas y sube la rama:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\probar-logistica-local.ps1
git add .
git commit -m "Describe el cambio realizado"
git push -u origin HEAD
```

Desde GitHub se abre una solicitud de cambios hacia `main` para que Pedro pueda revisarla. Pedro puede usar ramas con el prefijo `pedro/`.
