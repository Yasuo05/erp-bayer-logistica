param([string]$Xampp = 'C:\xampp', [int]$DatabasePort = 43327)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'native-process.ps1')
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$runtime = Join-Path $project '.runtime'
$webPid = Join-Path $runtime 'php.pid'
$webState = Join-Path $runtime 'php-process.json'
$webProcess = Get-ErpOwnedProcess -StatePath $webState -FilePath (Join-Path $Xampp 'php\php.exe')
if ($webProcess) {
    Stop-Process -InputObject $webProcess
    $webProcess.WaitForExit()
    Remove-Item -LiteralPath $webState
    if (Test-Path -LiteralPath $webPid) { Remove-Item -LiteralPath $webPid }
}
$mysql = Join-Path $Xampp 'mysql\bin\mysql.exe'
$mysqladmin = Join-Path $Xampp 'mysql\bin\mysqladmin.exe'
$client = @('--no-defaults','--protocol=tcp','--host=127.0.0.1',"--port=$DatabasePort",'--connect-timeout=1','--user=root')
$probe = Invoke-ErpNative -FilePath $mysql -ArgumentList ($client + @('--batch','--skip-column-names','--execute=SELECT @@datadir'))
if ($probe.ExitCode -eq 0) {
    $actual = $probe.StandardOutput.Trim()
    $expected = [IO.Path]::GetFullPath((Join-Path $runtime 'mariadb')).TrimEnd('\','/')
    if ([IO.Path]::GetFullPath(([string]$actual).Trim()).TrimEnd('\','/') -ne $expected) { throw 'La instancia del puerto no pertenece a este proyecto; se conserva.' }
    $shutdown = Invoke-ErpNative -FilePath $mysqladmin -ArgumentList ($client + @('shutdown'))
    if ($shutdown.ExitCode -ne 0) { throw ('No se pudo cerrar MariaDB. ' + $shutdown.StandardError.Trim()) }
}
Write-Output 'Entorno local detenido. Los datos quedan guardados.'
