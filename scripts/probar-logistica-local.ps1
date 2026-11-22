param([string]$Php = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
. (Join-Path $PSScriptRoot 'native-process.ps1')
if (-not (Test-Path -LiteralPath $Php -PathType Leaf)) { throw 'No se encuentra PHP de XAMPP. Indica su ruta con -Php.' }
$markerPath = Join-Path $project '.runtime\base-importada.json'
if (-not (Test-Path -LiteralPath $markerPath)) { throw 'Inicia primero el entorno con scripts\iniciar-logistica-local.ps1.' }
$marker = [IO.File]::ReadAllText($markerPath) | ConvertFrom-Json
if ($marker.datadir -ne (Join-Path $project '.runtime\mariadb')) { throw 'El marcador no corresponde a la base QA de este proyecto.' }
$windowsPowerShell = Join-Path $env:WINDIR 'System32\WindowsPowerShell\v1.0\powershell.exe'
$checks = New-Object 'Collections.Generic.List[object]'
$failure = $null
$nodeSkipped = $false
function Run-QaSuite([string]$Name,[string]$Executable,[string[]]$Arguments,[string]$Evidence) {
    Write-Host ('Probando: ' + $Name)
    $native = Invoke-ErpNative -FilePath $Executable -ArgumentList $Arguments -TimeoutMilliseconds 60000
    if ($native.ExitCode -ne 0) { throw ($Name + ': ' + $native.StandardOutput + $native.StandardError) }
    if ($Evidence -eq '') {
        $match = [regex]::Match($native.StandardOutput,'(\d+) comprobaciones correctas')
        if (-not $match.Success) { throw ('Falta el resultado de ' + $Name) }
        $count = [int]$match.Groups[1].Value
    } else {
        $result = [IO.File]::ReadAllText((Join-Path $project $Evidence)) | ConvertFrom-Json
        if (-not $result.ok) { throw ($Name + ': el resultado indica un fallo.') }
        if ($Name -in @('Negocio','Sedes') -and -not $result.rollback_final) { throw ($Name + ': falta confirmar el rollback final.') }
        $count = [int]$result.pasadas
    }
    $checks.Add([pscustomobject]@{suite=$Name;ok=$true;pasadas=$count;evidencia=$Evidence})
    Write-Host ('  Correctas: ' + $count)
}
Push-Location -LiteralPath $project
try {
    # Las suites de negocio son secuenciales: sus snapshots y rollback no deben mezclarse.
    $phpOptions = @('-d',('session.save_path=' + (Join-Path $project '.runtime\sessions')),'-d','date.timezone=America/Lima')
    Run-QaSuite 'Negocio' $Php ($phpOptions + @('tests/logistica_integracion.php','--qa')) '.runtime\resultado-integracion.json'
    Run-QaSuite 'Sedes' $Php ($phpOptions + @('tests/logistica_sedes.php','--qa')) '.runtime\resultado-sedes.json'
    Run-QaSuite 'HTTP' $windowsPowerShell @('-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $project 'tests\logistica_http.ps1')) '.runtime\resultado-http.json'
    Run-QaSuite 'Páginas' $windowsPowerShell @('-NoProfile','-ExecutionPolicy','Bypass','-File',(Join-Path $project 'tests\logistica_paginas.ps1')) '.runtime\resultado-paginas.json'
    $nodeCommand = Get-Command node -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($nodeCommand) {
        Run-QaSuite 'Interfaz JavaScript' $nodeCommand.Source @((Join-Path $project 'tests\logistica_ui_decimales.cjs')) ''
    } else {
        $nodeSkipped = $true
        Write-Host 'Interfaz JavaScript omitida: Node.js no está disponible en PATH.'
    }
} catch { $failure = $_.Exception.Message }
finally { Pop-Location }
$summary = [ordered]@{
    ok = ($null -eq $failure)
    completo = ($null -eq $failure -and -not $nodeSkipped)
    fecha = [DateTimeOffset]::Now.ToString('o')
    pasadas = [int](($checks | Measure-Object -Property pasadas -Sum).Sum)
    suites = @($checks.ToArray())
    interfaz_omitida = $nodeSkipped
    alcance = 'Instancia QA local. Negocio y permisos se revierten; puede haber saltos AUTO_INCREMENT y registros de acceso de las sesiones de prueba.'
}
if ($null -ne $failure) { $summary.error = $failure }
$json = $summary | ConvertTo-Json -Depth 8
[IO.File]::WriteAllText((Join-Path $project '.runtime\resultado-completo.json'),$json,[Text.UTF8Encoding]::new($false))
Write-Output $json
if ($null -ne $failure) { exit 1 }
