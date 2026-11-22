param([string]$Xampp = 'C:\xampp', [ValidateRange(1,65535)][int]$WebPort = 8097, [ValidateRange(1,65535)][int]$DatabasePort = 43327, [switch]$SinNavegador)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'native-process.ps1')
if ($WebPort -eq $DatabasePort) { throw 'Los puertos web y de base de datos deben ser distintos.' }
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$runtime = Join-Path $project '.runtime'
$data = Join-Path $runtime 'mariadb'
$php = Join-Path $Xampp 'php\php.exe'
$mysql = Join-Path $Xampp 'mysql\bin\mysql.exe'
$mysqld = Join-Path $Xampp 'mysql\bin\mysqld.exe'
$installer = Join-Path $Xampp 'mysql\bin\mysql_install_db.exe'
foreach ($binary in @($php,$mysql,$mysqld,$installer)) { if (-not (Test-Path -LiteralPath $binary)) { throw "No se encontró $binary. Indica -Xampp con tu instalación." } }
New-Item -ItemType Directory -Path $runtime -Force | Out-Null
$sessions = Join-Path $runtime 'sessions'
New-Item -ItemType Directory -Path $sessions -Force | Out-Null
$phpFlags = @('-d','date.timezone=America/Lima','-d',('session.save_path=' + $sessions))
$zipProbe = Invoke-ErpNative -FilePath $php -ArgumentList @('-r','exit(extension_loaded("zip") ? 0 : 1);')
if ($zipProbe.ExitCode -gt 1) { throw ('No se pudo verificar PHP: ' + $zipProbe.StandardError.Trim()) }
if ($zipProbe.ExitCode -ne 0) {
    $zipExtension = Join-Path $Xampp 'php\ext\php_zip.dll'
    if (-not (Test-Path -LiteralPath $zipExtension)) { throw 'Falta la extensión zip de PHP, necesaria para exportar Excel.' }
    $phpFlags += @('-d',('extension=' + $zipExtension))
}
$envPath = Join-Path $project '.env'
$envText = "APP_URL=http://127.0.0.1:$WebPort`nDB_HOST=127.0.0.1`nDB_PORT=$DatabasePort`nDB_NAME=bayer_pucchun_erp_v2`nDB_USER=root`nDB_PASS=`n"
if (Test-Path -LiteralPath $envPath) {
    $currentEnv = Get-Content -LiteralPath $envPath -Raw
    if ($currentEnv.Trim() -ne $envText.Trim()) { throw 'La configuración .env existente pertenece a otro entorno. Se conserva; usa una copia del proyecto para este inicio aislado.' }
} else { [IO.File]::WriteAllText($envPath, $envText, [Text.UTF8Encoding]::new($false)) }
$datahubEnv = [IO.File]::ReadAllText((Join-Path $project 'modulos\datahub\.env.example')).Replace('APP_URL=http://localhost:8080/modulos/datahub/public', "APP_URL=http://127.0.0.1:$WebPort/modulos/datahub/public").Replace('DB_PORT=3306', "DB_PORT=$DatabasePort").Replace('DB_PASS=cambiar_clave','DB_PASS=')
$facturacionEnv = [IO.File]::ReadAllText((Join-Path $project 'modulos\facturacion\.env.example')).Replace('DB_PORT=3306', "DB_PORT=$DatabasePort").Replace('APP_DEBUG=true','APP_DEBUG=false')
foreach ($moduleEnv in @(@{path='modulos\datahub\.env'; text=$datahubEnv}, @{path='modulos\facturacion\.env'; text=$facturacionEnv})) {
    $modulePath = Join-Path $project $moduleEnv.path
    if (-not (Test-Path -LiteralPath $modulePath)) { [IO.File]::WriteAllText($modulePath, $moduleEnv.text, [Text.UTF8Encoding]::new($false)) }
}
if (-not (Test-Path -LiteralPath (Join-Path $data 'mysql'))) {
    $installed = Invoke-ErpNative -FilePath $installer -ArgumentList @("--datadir=$data", "--port=$DatabasePort", '--silent') -TimeoutMilliseconds 60000
    if ($installed.ExitCode -ne 0) { throw ('No se pudo inicializar MariaDB aislado. ' + $installed.StandardError.Trim()) }
}
$clientArgs = @('--no-defaults','--protocol=tcp','--host=127.0.0.1',"--port=$DatabasePort",'--connect-timeout=1','--user=root','--batch','--skip-column-names')
$expectedData = [IO.Path]::GetFullPath($data).TrimEnd('\','/')
$databaseProbe = Invoke-ErpNative -FilePath $mysql -ArgumentList ($clientArgs + @('--execute=SELECT @@datadir'))
if ($databaseProbe.ExitCode -ne 0) {
    $dbArguments = @('--no-defaults', ('--basedir=' + (Join-Path $Xampp 'mysql')), ('--datadir=' + $data), "--port=$DatabasePort", '--bind-address=127.0.0.1','--skip-name-resolve','--innodb-buffer-pool-size=64M','--innodb-log-file-size=16M','--skip-log-bin','--console')
    $dbProcess = Start-Process -FilePath $mysqld -ArgumentList (ConvertTo-ErpNativeArguments $dbArguments) -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'mariadb.stdout.log') -RedirectStandardError (Join-Path $runtime 'mariadb.stderr.log')
    Save-ErpProcessState -Process $dbProcess -FilePath $mysqld -StatePath (Join-Path $runtime 'mariadb-process.json')
    $dbProcess.Id | Set-Content -LiteralPath (Join-Path $runtime 'mariadb.pid')
    for ($attempt=0; $attempt -lt 40; $attempt++) {
        Start-Sleep -Milliseconds 250
        $databaseProbe = Invoke-ErpNative -FilePath $mysql -ArgumentList ($clientArgs + @('--execute=SELECT @@datadir'))
        if ($databaseProbe.ExitCode -eq 0) { break }
        $dbProcess.Refresh()
        if ($dbProcess.HasExited) { break }
    }
}
if ($databaseProbe.ExitCode -ne 0 -or -not $databaseProbe.StandardOutput.Trim()) { throw 'MariaDB no inició. Revisa .runtime/mariadb.stderr.log.' }
$actualData = $databaseProbe.StandardOutput.Trim()
if ([IO.Path]::GetFullPath(([string]$actualData).Trim()).TrimEnd('\','/') -ne $expectedData) { throw 'El puerto corresponde a otra base. No se importará ni modificará.' }
$importMarker = Join-Path $runtime 'base-importada.json'
if (-not (Test-Path -LiteralPath $importMarker)) {
    $source = Join-Path $project 'sql\bayer_pucchun_erp_v2.sql'
    $sourceHash = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash
    $pendingMarker = Join-Path $runtime 'base-importando.json'
    $tables = Invoke-ErpNative -FilePath $mysql -ArgumentList ($clientArgs + @("--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='bayer_pucchun_erp_v2'"))
    if ($tables.ExitCode -ne 0) { throw ('No se pudo consultar la base aislada: ' + $tables.StandardError.Trim()) }
    if ([int]$tables.StandardOutput.Trim() -gt 0) {
        if (-not (Test-Path -LiteralPath $pendingMarker)) { throw 'La base contiene tablas y no existe el marcador de importación. Se conserva para revisión.' }
        $pending = Get-Content -LiteralPath $pendingMarker -Raw | ConvertFrom-Json
        if ($pending.source_sha256 -ne $sourceHash -or $pending.datadir -ne $expectedData) { throw 'La importación incompleta no coincide con este entorno; se conserva.' }
    }
    @{source_sha256=$sourceHash; datadir=$expectedData} | ConvertTo-Json | Set-Content -LiteralPath $pendingMarker
    # Compatibilidad local MariaDB 10.4; el dump MySQL 8 original se conserva.
    $sql = [IO.File]::ReadAllText($source).Replace('utf8mb4_0900_ai_ci','utf8mb4_unicode_ci')
    $sql = $sql.Replace('regexp_like(`request_hash`,_ascii''^[0-9a-f]+$'')', '(`request_hash` REGEXP BINARY ''^[0-9a-f]+$'')')
    $localSql = Join-Path $runtime 'base-local.sql'
    [IO.File]::WriteAllText($localSql, $sql, [Text.UTF8Encoding]::new($false))
    $import = Start-Process -FilePath $mysql -ArgumentList (ConvertTo-ErpNativeArguments ($clientArgs + @('--default-character-set=utf8mb4'))) -WindowStyle Hidden -RedirectStandardInput $localSql -RedirectStandardOutput (Join-Path $runtime 'import.stdout.log') -RedirectStandardError (Join-Path $runtime 'import.stderr.log') -Wait -PassThru
    if ($import.ExitCode -ne 0) { throw 'Falló la importación local. Revisa .runtime/import.stderr.log. No se reimportará sobre datos existentes.' }
    @{source_sha256=(Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash; imported_at=(Get-Date -Format o); datadir=$expectedData} | ConvertTo-Json | Set-Content -LiteralPath $importMarker
    Remove-Item -LiteralPath $pendingMarker
}
$migration = Invoke-ErpNative -FilePath $php -ArgumentList @((Join-Path $PSScriptRoot 'migrar-logistica.php')) -TimeoutMilliseconds 60000
if ($migration.ExitCode -ne 0) { throw ('No se pudieron aplicar las migraciones. ' + $migration.StandardError.Trim()) }
if ($migration.StandardOutput.Trim()) { Write-Output $migration.StandardOutput.Trim() }
$url = "http://127.0.0.1:$WebPort/modulos/logistica/envios.php"
$webPid = Join-Path $runtime 'php.pid'
$webState = Join-Path $runtime 'php-process.json'
$running = Get-ErpOwnedProcess -StatePath $webState -FilePath $php
if (-not $running) {
    $web = Start-Process -FilePath $php -ArgumentList (ConvertTo-ErpNativeArguments ($phpFlags + @('-S',"127.0.0.1:$WebPort",'-t',$project,(Join-Path $project 'router.php')))) -WorkingDirectory $project -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'php.stdout.log') -RedirectStandardError (Join-Path $runtime 'php.stderr.log')
    Save-ErpProcessState -Process $web -FilePath $php -StatePath $webState
    $web.Id | Set-Content -LiteralPath $webPid
    $running = $web
}
$webReady = $false
for ($attempt=0; $attempt -lt 30; $attempt++) {
    $running.Refresh()
    if ($running.HasExited) { throw 'El servidor PHP no pudo iniciar. Revisa .runtime/php.stderr.log y si el puerto está ocupado.' }
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$WebPort/login.php" -UseBasicParsing -TimeoutSec 2
        $running.Refresh()
        if (-not $running.HasExited -and $response.StatusCode -eq 200 -and $response.Content.Contains('Bayer')) { $webReady=$true; break }
    } catch { }
    Start-Sleep -Milliseconds 250
}
if (-not $webReady) { throw 'El servidor no respondió a tiempo. Revisa .runtime/php.stderr.log.' }
Write-Output "Logística local: $url"
Write-Output 'Usuario de ejemplo del ZIP: admin@pucchun.com / admin123'
Write-Output "Datos aislados: $data"
if (-not $SinNavegador) { Start-Process $url }
