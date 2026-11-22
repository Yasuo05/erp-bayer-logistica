param([string]$BaseUrl = 'http://127.0.0.1:8097')
$ErrorActionPreference = 'Stop'
if ($BaseUrl -ne 'http://127.0.0.1:8097') { throw 'Estas pruebas sólo permiten el entorno local aislado documentado.' }
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$markerPath = Join-Path $project '.runtime\base-importada.json'
if (-not (Test-Path -LiteralPath $markerPath)) { throw 'Falta marcador de la base local aislada.' }
$marker = Get-Content -LiteralPath $markerPath -Raw | ConvertFrom-Json
if ($marker.datadir -ne (Join-Path $project '.runtime\mariadb')) { throw 'Marcador de entorno inválido.' }
$qaEnv = [IO.File]::ReadAllText((Join-Path $project '.env'))
foreach ($setting in @(@('DB_HOST','127.0.0.1'),@('DB_PORT','43327'),@('DB_NAME','bayer_pucchun_erp_v2'))) {
    $value = [regex]::Match($qaEnv,('(?m)^\s*'+$setting[0]+'\s*=\s*([^\r\n]*)')).Groups[1].Value.Trim().Trim('"', "'")
    if ($value -ne $setting[1]) { throw 'La configuración no corresponde a la base QA aislada.' }
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$qaCookies = New-Object Net.CookieContainer
# HttpWebRequest conserva cookies y permite inspeccionar errores y XLSX también en PowerShell 5.1.
function Invoke-QaRequest {
    param([string]$Uri,[string]$Method='GET',$Body=$null,[string]$ContentType='', [hashtable]$Headers=@{},[int]$TimeoutSec=10)
    $target = [Uri]$Uri
    if ($target.GetLeftPart([UriPartial]::Authority) -ne $BaseUrl) { throw 'Sólo se permiten solicitudes al ERP local de pruebas.' }
    $request = [Net.HttpWebRequest][Net.WebRequest]::Create($target)
    $request.Method=$Method.ToUpperInvariant(); $request.Proxy=$null
    $request.CookieContainer=$qaCookies; $request.AllowAutoRedirect=$true
    $request.Timeout=$TimeoutSec*1000; $request.ReadWriteTimeout=$TimeoutSec*1000
    foreach ($key in $Headers.Keys) { if ($key -eq 'Accept') { $request.Accept=$Headers[$key] } else { $request.Headers[$key]=$Headers[$key] } }
    if ($null -ne $Body) {
        if ($Body -is [hashtable]) {
            $parts=foreach ($key in $Body.Keys) { [Uri]::EscapeDataString([string]$key)+'='+[Uri]::EscapeDataString([string]$Body[$key]) }
            $Body=$parts -join '&'; $ContentType='application/x-www-form-urlencoded; charset=utf-8'
        }
        $bytes=[Text.Encoding]::UTF8.GetBytes([string]$Body)
        $request.ContentType=$ContentType; $request.ContentLength=$bytes.Length
        $requestStream=$request.GetRequestStream()
        try { $requestStream.Write($bytes,0,$bytes.Length) } finally { $requestStream.Dispose() }
    }
    $response=$null
    try { $response=[Net.HttpWebResponse]$request.GetResponse() }
    catch [Net.WebException] { if ($null -eq $_.Exception.Response) { throw }; $response=[Net.HttpWebResponse]$_.Exception.Response }
    try {
        $memory=[IO.MemoryStream]::new(); $sourceStream=$response.GetResponseStream()
        try { $sourceStream.CopyTo($memory) } finally { $sourceStream.Dispose() }
        $memory.Position=0
        return [pscustomobject]@{StatusCode=[int]$response.StatusCode;Content=[Text.Encoding]::UTF8.GetString($memory.ToArray());Headers=$response.Headers;RawContentStream=$memory}
    } finally { if ($null -ne $response) { $response.Dispose() } }
}
$results = [Collections.Generic.List[object]]::new()
function Assert-Check([string]$Name,[bool]$Condition) {
    $results.Add([pscustomobject]@{caso=$Name;ok=$Condition})
    if (-not $Condition) { throw "Falló: $Name" }
}
function Read-Api([string]$Route) {
    $response = Invoke-QaRequest -Uri ($BaseUrl+'/modulos/logistica/api.php?route='+[Uri]::EscapeDataString($Route)) -TimeoutSec 10
    return @{status=[int]$response.StatusCode; payload=($response.Content | ConvertFrom-Json); response=$response}
}
function Post-Api([string]$Route,[hashtable]$Payload,[bool]$WithCsrf=$true) {
    $headers = @{'Accept'='application/json'}
    if ($WithCsrf) { $headers['X-CSRF-Token']=$token }
    $response = Invoke-QaRequest -Uri ($BaseUrl+'/modulos/logistica/api.php?route='+[Uri]::EscapeDataString($Route)) -Method Post -ContentType 'application/json' -Body ($Payload | ConvertTo-Json -Compress) -Headers $headers -TimeoutSec 10
    return @{status=[int]$response.StatusCode; payload=($response.Content | ConvertFrom-Json)}
}
try {
    $login = Invoke-QaRequest -Uri ($BaseUrl+'/login.php') -TimeoutSec 10
    Assert-Check 'Formulario de acceso disponible' ($login.StatusCode -eq 200)
    $logged = Invoke-QaRequest -Uri ($BaseUrl+'/login.php') -Method Post -Body @{email='admin@pucchun.com';clave='admin123'} -TimeoutSec 10
    Assert-Check 'Ingreso real con cuenta de ejemplo' ($logged.Content.Contains('Cerrar sesión'))
    $screen = Invoke-QaRequest -Uri ($BaseUrl+'/modulos/logistica/index.php') -TimeoutSec 10
    $token = [regex]::Match($screen.Content,'<meta name="g7-csrf" content="([^"]+)"').Groups[1].Value
    Assert-Check 'Pantalla logística y token CSRF' ($screen.StatusCode -eq 200 -and $token.Length -gt 10)
    $list = Read-Api '/api/envios'
    Assert-Check 'Listado JSON de envíos persistidos' ($list.status -eq 200 -and $list.payload.ok -and $list.payload.data.Count -ge 14)
    $id = [int]$list.payload.data[0].id
    $detail = Read-Api "/api/envios/$id"
    Assert-Check 'Expediente con balance e historial' ($detail.status -eq 200 -and $null -ne $detail.payload.data.balance -and $null -ne $detail.payload.data.historial)
    $version = [int]$detail.payload.data.envio.version
    $noCsrf = Post-Api "/api/envios/$id/configurar" @{version=$version;idempotency_key='http-sin-csrf';destino='No debe guardarse';transporte='No debe guardarse'} $false
    Assert-Check 'POST sin CSRF rechazado' ($noCsrf.status -eq 419)
    $legacy = Post-Api "/api/despachos/$id/salir" @{}
    Assert-Check 'Salida antigua exige los controles del expediente' ($legacy.status -in 400,409)
    $legacyDelivery = Post-Api "/api/despachos/$id/entregar" @{}
    Assert-Check 'Entrega antigua no permite saltar los intentos' ($legacyDelivery.status -eq 409)
    $manual = Post-Api '/api/despachos' @{producto_id=3;almacen_id=1;cliente_id=1;cantidad='1'}
    Assert-Check 'Alta manual sin origen comercial rechazada' ($manual.status -eq 409)
    $unchanged = Read-Api "/api/envios/$id"
    Assert-Check 'Intentos inválidos conservan versión y balance' ([int]$unchanged.payload.data.envio.version -eq $version -and ($unchanged.payload.data.balance | ConvertTo-Json -Compress) -eq ($detail.payload.data.balance | ConvertTo-Json -Compress))
    $null = Invoke-QaRequest -Uri ($BaseUrl+'/cambiar_sede.php') -Method Post -Body @{csrf=$token;sede='1';volver='/modulos/logistica/index.php'} -TimeoutSec 10
    $scoped = Read-Api '/api/envios'
    Assert-Check 'Listado limitado a sede activa Cañete' ($scoped.status -eq 200 -and @($scoped.payload.data | Where-Object {[int]$_.sucursal_id -ne 1}).Count -eq 0)
    $foreign = Read-Api '/api/envios/7'
    Assert-Check 'Expediente de otra sede rechazado' ($foreign.status -eq 403)
    $foreignStock = Read-Api '/api/stock?almacen=2'
    Assert-Check 'Consulta de stock por almacén ajeno rechazada' ($foreignStock.status -eq 403)
    $guide = Invoke-QaRequest -Uri ($BaseUrl+'/modulos/logistica/api.php?route='+[Uri]::EscapeDataString('/guias/7')) -TimeoutSec 10
    Assert-Check 'Impresión de otra sede rechazada' ($guide.StatusCode -eq 403)
    foreach ($route in @('/api/resumen','/api/movimientos','/api/ordenes','/api/recepciones','/api/transferencias','/api/conteos','/api/cuentas-pagar','/api/auditoria')) {
        $response = Read-Api $route
        Assert-Check ("Regresión GET $route") ($response.status -eq 200 -and $response.payload.ok)
    }
    foreach ($kind in @('stock','kardex','ordenes')) {
        $export = Invoke-QaRequest -Uri ($BaseUrl+'/modulos/logistica/api.php?route='+[Uri]::EscapeDataString('/api/exportar?tipo='+$kind)) -TimeoutSec 10
        $mime = [string]::Join('', @($export.Headers['Content-Type']))
        Assert-Check ("Exportación Excel $kind") ($export.StatusCode -eq 200 -and $mime.Contains('spreadsheetml'))
        $archive = [IO.Compression.ZipArchive]::new($export.RawContentStream, [IO.Compression.ZipArchiveMode]::Read, $true)
        try { Assert-Check ("Excel $kind contiene una hoja válida") ($null -ne $archive.GetEntry('xl/worksheets/sheet1.xml')) }
        finally { $archive.Dispose() }
    }
    foreach ($hidden in @('/.env','/.runtime/base-local.sql','/sql/bayer_pucchun_erp_v2.sql','/scripts/migrar-logistica.php')) {
        $response = Invoke-QaRequest -Uri ($BaseUrl+$hidden) -TimeoutSec 10
        Assert-Check ("Servidor protege $hidden") ($response.StatusCode -eq 404)
    }
    $null = Invoke-QaRequest -Uri ($BaseUrl+'/cambiar_sede.php') -Method Post -Body @{csrf=$token;sede='';volver='/modulos/logistica/index.php'} -TimeoutSec 10
    $null = Invoke-QaRequest -Uri ($BaseUrl+'/logout.php') -TimeoutSec 10
    $public = Invoke-QaRequest -Uri ($BaseUrl+'/modulos/logistica/api.php?route='+[Uri]::EscapeDataString('/api/envios')) -TimeoutSec 10
    Assert-Check 'Sin sesión se exige ingreso' ($public.Content.Contains('Iniciar Sesión') -and -not $public.Content.Contains('"balance"'))
    $output = @{ok=$true;pasadas=$results.Count;casos=$results;fecha=(Get-Date -Format o)}
    [IO.File]::WriteAllText((Join-Path $project '.runtime\resultado-http.json'),($output | ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))
    @{ok=$true;pasadas=$results.Count;evidencia='.runtime/resultado-http.json'} | ConvertTo-Json
} catch {
    $failed = @{ok=$false;pasadas=@($results | Where-Object ok).Count;error=$_.Exception.Message;casos=$results;fecha=(Get-Date -Format o)} | ConvertTo-Json -Depth 8
    [IO.File]::WriteAllText((Join-Path $project '.runtime\resultado-http.json'),$failed,[Text.UTF8Encoding]::new($false))
    Write-Output $failed
    exit 1
}
