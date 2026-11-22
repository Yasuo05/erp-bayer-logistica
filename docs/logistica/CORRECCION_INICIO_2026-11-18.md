# Inicio compatible con Windows PowerShell

Se corrigieron los dos errores reportados en Windows PowerShell 5.1: pérdida de comillas al pasar `extension_loaded("zip")` a PHP y terminación anticipada por `NativeCommandError` cuando MariaDB aún no está encendido.

`scripts/native-process.ps1` pasa los argumentos directamente al proceso, conservando comillas y espacios, y captura por separado salida, errores y código de retorno. El inicio espera respuesta HTTP antes de anunciar disponibilidad. La identidad del servidor PHP se verifica mediante ejecutable, PID y fecha de creación para evitar reutilizar un PID ajeno. Los scripts se guardan en UTF-8 con BOM para que Windows PowerShell interprete correctamente los mensajes.

Verificado el 18 de noviembre de 2026 con `C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe`: inicio con los servidores apagados, inicio repetido, detención, detención repetida y reinicio con respuesta HTTP. Se conservan la base y el expediente de ejemplo ya conciliado. No se modificaron las reglas del negocio.

Desde el terminal PowerShell de VS Code, en la carpeta ERP-main:

```powershell
.\scripts\iniciar-logistica-local.ps1
```

Para detenerlo:

```powershell
.\scripts\detener-logistica-local.ps1
```
