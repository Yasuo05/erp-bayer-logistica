param([string]$BaseUrl = 'http://127.0.0.1:8097')
$ErrorActionPreference = 'Stop'
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$resultPath = Join-Path $project '.runtime\resultado-paginas.json'
$checks = New-Object 'Collections.Generic.List[object]'
$sessionCookies = New-Object Net.CookieContainer
$anonymousCookies = New-Object Net.CookieContainer
$guardPassed = $false
$loggedIn = $false
$csrfToken = ''
$failureMessage = $null
$cleanupMessage = $null

function Assert-PageCheck([string]$Name, [bool]$Condition, [string]$Detail = '') {
    $entry = [ordered]@{ caso = $Name; ok = $Condition }
    if ($Detail -ne '') { $entry.detalle = $Detail }
    $checks.Add([pscustomobject]$entry)
    if (-not $Condition) { throw "Falló: $Name. $Detail" }
}

function Normalize-LocalPath([string]$Path) {
    return [IO.Path]::GetFullPath($Path).TrimEnd('\','/')
}

function Read-IniValue([string]$Text, [string]$Key) {
    $match = [regex]::Match($Text, ('(?m)^\s*' + [regex]::Escape($Key) + '\s*=\s*([^\r\n]*)'))
    if (-not $match.Success) { return '' }
    return $match.Groups[1].Value.Trim().Trim('"', "'")
}

# Sólo navegación HTTP y los POST de autenticación/cambio de sede. No crea ni modifica operaciones.
# HttpWebRequest permite inspeccionar 302/400/403/404 también en Windows PowerShell 5.1.
function Read-PageHttp([string]$Relative, [string]$Method = 'GET', [hashtable]$FormData = $null, [switch]$Anonymous) {
    if (-not $guardPassed) { throw 'La guardia local debe aprobarse antes de cualquier solicitud HTTP.' }
    if (-not $Relative.StartsWith('/') -or $Relative.StartsWith('//')) { throw 'Sólo se permiten rutas internas del entorno QA.' }
    if ($Method -notin @('GET','POST')) { throw 'Método de prueba no permitido.' }
    if ($Method -eq 'POST' -and $Relative -notin @('/login.php','/cambiar_sede.php')) { throw 'Estas pruebas no envían operaciones de negocio.' }
    $request = [Net.HttpWebRequest][Net.WebRequest]::Create($BaseUrl + $Relative)
    $request.Method = $Method
    $request.Proxy = $null
    $request.AllowAutoRedirect = $false
    $request.Timeout = 15000
    $request.ReadWriteTimeout = 15000
    $request.CookieContainer = if ($Anonymous) { $anonymousCookies } else { $sessionCookies }
    $request.UserAgent = 'ERP-Logistica-Pruebas-Paginas/1.0'
    if ($Method -eq 'POST') {
        $parts = foreach ($key in $FormData.Keys) {
            [Uri]::EscapeDataString([string]$key) + '=' + [Uri]::EscapeDataString([string]$FormData[$key])
        }
        $bytes = [Text.Encoding]::UTF8.GetBytes(($parts -join '&'))
        $request.ContentType = 'application/x-www-form-urlencoded; charset=utf-8'
        $request.ContentLength = $bytes.Length
        $stream = $request.GetRequestStream()
        try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
    }
    $response = $null
    try { $response = [Net.HttpWebResponse]$request.GetResponse() }
    catch [Net.WebException] {
        if ($null -eq $_.Exception.Response) { throw }
        $response = [Net.HttpWebResponse]$_.Exception.Response
    }
    try {
        $reader = [IO.StreamReader]::new($response.GetResponseStream(), [Text.Encoding]::UTF8, $true)
        try { $body = $reader.ReadToEnd() } finally { $reader.Dispose() }
        return [pscustomobject]@{
            status = [int]$response.StatusCode
            location = [string]$response.Headers['Location']
            content = $body
            path = $Relative
        }
    } finally { if ($null -ne $response) { $response.Dispose() } }
}

function Html-Text([string]$Html) {
    return [regex]::Replace([Net.WebUtility]::HtmlDecode([regex]::Replace($Html, '<[^>]*>', ' ')), '\s+', ' ').Trim()
}

function Resolve-PageLink([string]$Current, [string]$Href) {
    if ([string]::IsNullOrWhiteSpace($Href)) { throw 'Enlace vacío en la navegación.' }
    $uri = [Uri]::new([Uri]::new($BaseUrl + $Current), [Net.WebUtility]::HtmlDecode($Href))
    if ($uri.GetLeftPart([UriPartial]::Authority) -ne $BaseUrl -or $uri.Fragment -ne '') { throw "El enlace no representa una página local real: $Href" }
    return $uri
}

function Get-PageConfig([string]$Html) {
    $pattern = '<script\b(?=[^>]*\bid\s*=\s*["'']g7-page-config["''])[^>]*>([\s\S]*?)</script>'
    $configMatches = [regex]::Matches($Html, $pattern, [Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($configMatches.Count -ne 1) { throw 'La página debe exponer exactamente una configuración de navegación G7.' }
    return ($configMatches[0].Groups[1].Value | ConvertFrom-Json)
}

function Change-TestBranch([string]$Branch) {
    $changed = Read-PageHttp '/cambiar_sede.php' 'POST' @{ csrf = $csrfToken; sede = $Branch; volver = '/modulos/logistica/envios.php' }
    Assert-PageCheck ('Cambio de sede para la prueba: ' + $(if ($Branch -eq '') { 'Todas' } else { $Branch })) ($changed.status -in @(302,303))
}

try {
    if ($BaseUrl -ne 'http://127.0.0.1:8097') { throw 'Estas pruebas sólo permiten http://127.0.0.1:8097.' }
    $envPath = Join-Path $project '.env'
    $markerPath = Join-Path $project '.runtime\base-importada.json'
    if (-not (Test-Path -LiteralPath $envPath) -or -not (Test-Path -LiteralPath $markerPath)) { throw 'Faltan .env o el marcador de importación del entorno QA aislado.' }
    $envText = [IO.File]::ReadAllText($envPath)
    if ((Read-IniValue $envText 'DB_HOST') -ne '127.0.0.1' -or (Read-IniValue $envText 'DB_PORT') -ne '43327' -or (Read-IniValue $envText 'DB_NAME') -ne 'bayer_pucchun_erp_v2') {
        throw '.env no corresponde al host, puerto y base QA autorizados. No se realizaron solicitudes.'
    }
    $marker = [IO.File]::ReadAllText($markerPath) | ConvertFrom-Json
    $expectedData = Normalize-LocalPath (Join-Path $project '.runtime\mariadb')
    if ([string]::IsNullOrWhiteSpace([string]$marker.datadir) -or (Normalize-LocalPath ([string]$marker.datadir)) -ne $expectedData -or -not (Test-Path -LiteralPath $expectedData -PathType Container)) {
        throw 'El marcador no corresponde al datadir aislado de este proyecto. No se realizaron solicitudes.'
    }
    $guardPassed = $true
    Assert-PageCheck 'Guardia local: URL, .env y marcador con datadir exacto' $true

    $public = Read-PageHttp '/modulos/logistica/envios.php' 'GET' $null -Anonymous
    $isLoginRedirect = $public.status -in @(301,302,303,307,308) -and $public.location -match '(?:^|/)login\.php(?:\?|$)'
    $isLoginPage = $public.status -eq 200 -and $public.content -match 'name=["'']email["'']' -and $public.content -match 'name=["'']clave["'']'
    Assert-PageCheck 'Sin sesión se exige ingreso antes de mostrar Envíos' ($isLoginRedirect -or $isLoginPage)
    Assert-PageCheck 'La respuesta anónima no contiene configuración del módulo' ($public.content -notmatch 'id=["'']g7-page-config["'']')

    $loginForm = Read-PageHttp '/login.php'
    Assert-PageCheck 'Formulario de acceso real disponible' ($loginForm.status -eq 200 -and $loginForm.content -match 'name=["'']clave["'']')
    $login = Read-PageHttp '/login.php' 'POST' @{ email = 'admin@pucchun.com'; clave = 'admin123' }
    Assert-PageCheck 'Ingreso real con el administrador de ejemplo' ($login.status -in @(302,303) -and $login.location -notmatch 'login\.php')
    $loggedIn = $true
    $entryPage = Read-PageHttp '/modulos/logistica/envios.php'
    $hasLogout = $entryPage.content -match '<a\b[^>]*href=["''][^"'']*/logout\.php(?:\?[^"'']*)?["'']'
    $entryConfig = Get-PageConfig $entryPage.content
    Assert-PageCheck 'Sesión autenticada muestra el marco del ERP' ($entryPage.status -eq 200 -and $hasLogout -and $entryConfig.page -eq 'despachos')
    $csrfMatch = [regex]::Match($entryPage.content, '<meta\b[^>]*name=["'']g7-csrf["''][^>]*content=["'']([^"'']+)["'']', [Text.RegularExpressions.RegexOptions]::IgnoreCase)
    $csrfToken = $csrfMatch.Groups[1].Value
    Assert-PageCheck 'La página expone CSRF de la sesión para cambiar sede' ($csrfToken.Length -ge 16)

    Change-TestBranch ''
    $existing = Read-PageHttp ('/modulos/logistica/api.php?route=' + [Uri]::EscapeDataString('/api/envios'))
    $existingPayload = $existing.content | ConvertFrom-Json
    Assert-PageCheck 'Los envíos de ejemplo se consultan sin crear operaciones' ($existing.status -eq 200 -and $existingPayload.ok -and @($existingPayload.data).Count -ge 14)
    $knownShipment = @($existingPayload.data | Where-Object { [long]$_.id -eq 14 })
    Assert-PageCheck 'El expediente de prueba 14 pertenece a sede Mala' ($knownShipment.Count -eq 1 -and [int]$knownShipment[0].sucursal_id -eq 2)
    $maximum = [long](($existingPayload.data | Measure-Object -Property id -Maximum).Maximum)
    $unknownId = $maximum + 1
    Assert-PageCheck 'El identificador ausente se calcula a partir de datos reales' (@($existingPayload.data | Where-Object { [long]$_.id -eq $unknownId }).Count -eq 0)

    Change-TestBranch '2'
    $pages = @(
        @{ path='envios.php'; page='despachos'; title='Envíos y entregas' },
        @{ path='envio.php?id=14'; page='despachos'; title='DP-2026-0014'; shipment=14 },
        @{ path='panel.php'; page='inicio'; title='Panel de control' },
        @{ path='stock.php'; page='stock'; title='Stock por sedes' },
        @{ path='kardex.php?producto=3'; page='kardex'; title='Movimientos (Kardex)'; product=3 },
        @{ path='inventario.php'; page='conteos'; title='Inventario físico / Ajustes' },
        @{ path='compras.php'; page='compras'; title='Órdenes de compra' },
        @{ path='recepciones.php'; page='recepciones'; title='Recepción de mercadería' },
        @{ path='traslados.php'; page='traslados'; title='Traslados entre sedes' },
        @{ path='cuentas_por_pagar.php'; page='pagos'; title='Cuentas por pagar' },
        @{ path='auditoria.php'; page='auditoria'; title='Auditoría de operaciones' }
    )
    $routeTargets = [ordered]@{
        despachos='envios.php'; expediente='envio.php'; inicio='panel.php'; stock='stock.php'; kardex='kardex.php'; conteos='inventario.php'; compras='compras.php'; recepciones='recepciones.php'; traslados='traslados.php'; pagos='cuentas_por_pagar.php'; auditoria='auditoria.php'
    }
    $firstPageHtml = ''
    foreach ($page in $pages) {
        $relative = '/modulos/logistica/' + $page.path
        $response = Read-PageHttp $relative
        Assert-PageCheck ('Página real disponible: ' + $page.path) ($response.status -eq 200) ('HTTP ' + $response.status)
        $headings = [regex]::Matches($response.content, '<h1\b[^>]*>([\s\S]*?)</h1>', [Text.RegularExpressions.RegexOptions]::IgnoreCase)
        Assert-PageCheck ('Un único título principal servido por PHP: ' + $page.path) ($headings.Count -eq 1)
        $heading = Html-Text $headings[0].Groups[1].Value
        $correctTitle = if ($page.ContainsKey('shipment')) { $heading.Contains($page.title) } else { $heading -eq $page.title }
        Assert-PageCheck ('Título correcto antes de ejecutar JavaScript: ' + $page.path) $correctTitle $heading
        $documentTitle = [regex]::Match($response.content, '<title[^>]*>([\s\S]*?)</title>', [Text.RegularExpressions.RegexOptions]::IgnoreCase)
        Assert-PageCheck ('Título de navegador identifica la página: ' + $page.path) ($documentTitle.Success -and (Html-Text $documentTitle.Groups[1].Value).Contains($page.title))
        Assert-PageCheck ('No hay selector interno de todas las pantallas: ' + $page.path) ($response.content -notmatch '\bid\s*=\s*["'']sidebar["'']' -and $response.content -notmatch '\bclass\s*=\s*["''][^"'']*\bg7-tab\b')
        $config = Get-PageConfig $response.content
        Assert-PageCheck ('La URL determina el contenido inicial: ' + $page.path) ($config.page -eq $page.page)
        $expectedShipment = if ($page.ContainsKey('shipment')) { [int]$page.shipment } else { 0 }
        Assert-PageCheck ('El expediente sólo se selecciona en su URL propia: ' + $page.path) ([long]$config.shipmentId -eq $expectedShipment)
        if ($page.ContainsKey('product')) { Assert-PageCheck 'Kardex conserva el producto de la URL' ([int]$config.productId -eq [int]$page.product) }
        $allRoutesValid = $true
        foreach ($key in $routeTargets.Keys) {
            $property = $config.routes.PSObject.Properties[$key]
            if ($null -eq $property) { $allRoutesValid = $false; break }
            $target = Resolve-PageLink $relative ([string]$property.Value)
            if ($target.AbsolutePath -ne ('/modulos/logistica/' + $routeTargets[$key])) { $allRoutesValid = $false; break }
        }
        Assert-PageCheck ('Las acciones pueden navegar a páginas reales sin fragmentos: ' + $page.path) $allRoutesValid
        if ($firstPageHtml -eq '') { $firstPageHtml = $response.content }
    }

    $groups = [ordered]@{
        'Compras'=@('compras.php','recepciones.php','cuentas_por_pagar.php')
        'Inventarios'=@('stock.php','kardex.php','inventario.php')
        'Logística'=@('panel.php','envios.php','traslados.php','auditoria.php')
    }
    $groupMatches = [regex]::Matches($firstPageHtml, '<div\b[^>]*class=["'']dropdown-header["''][^>]*>(?<name>[^<]*)</div>(?<links>[\s\S]*?)</div>', [Text.RegularExpressions.RegexOptions]::IgnoreCase)
    foreach ($name in $groups.Keys) {
        $matchingGroups = @($groupMatches | Where-Object { (Html-Text $_.Groups['name'].Value) -eq $name })
        Assert-PageCheck ('El menú separa el grupo ' + $name) ($matchingGroups.Count -eq 1)
        $links = [regex]::Matches($matchingGroups[0].Groups['links'].Value, '<a\b[^>]*href=["'']([^"'']+)["'']', [Text.RegularExpressions.RegexOptions]::IgnoreCase)
        $paths = @($links | ForEach-Object { (Resolve-PageLink '/modulos/logistica/envios.php' $_.Groups[1].Value).AbsolutePath })
        $expectedPaths = @($groups[$name] | ForEach-Object { '/modulos/logistica/' + $_ })
        $differences = @(Compare-Object -ReferenceObject $expectedPaths -DifferenceObject $paths)
        Assert-PageCheck ('El grupo ' + $name + ' ofrece sus enlaces reales independientes') ($paths.Count -eq $expectedPaths.Count -and $differences.Count -eq 0)
    }

    $historical = Read-PageHttp '/modulos/logistica/index.php'
    $redirect = $null
    if ($historical.location -ne '') { $redirect = Resolve-PageLink '/modulos/logistica/index.php' $historical.location }
    Assert-PageCheck 'La antigua entrada dirige a la página de Envíos' ($historical.status -in @(301,302,303,307,308) -and $null -ne $redirect -and $redirect.AbsolutePath -eq '/modulos/logistica/envios.php')

    $notFound = Read-PageHttp ('/modulos/logistica/envio.php?id=' + $unknownId)
    Assert-PageCheck 'Un expediente inexistente devuelve 404' ($notFound.status -eq 404) ('id calculado: ' + $unknownId)
    foreach ($invalid in @('envio.php?id=abc','envio.php?id=-1','envio.php')) {
        $invalidPage = Read-PageHttp ('/modulos/logistica/' + $invalid)
        Assert-PageCheck ('Identificador de expediente inválido devuelve 400: ' + $invalid) ($invalidPage.status -eq 400)
    }
    Change-TestBranch '1'
    $foreign = Read-PageHttp '/modulos/logistica/envio.php?id=14'
    Assert-PageCheck 'La URL de un expediente de otra sede devuelve 403' ($foreign.status -eq 403)
    Assert-PageCheck 'El rechazo de sede no entrega la configuración del expediente' ($foreign.content -notmatch 'id=["'']g7-page-config["'']')
} catch {
    $failureMessage = $_.Exception.Message
} finally {
    if ($guardPassed -and $loggedIn) {
        try {
            if ($csrfToken -ne '') { $null = Read-PageHttp '/cambiar_sede.php' 'POST' @{ csrf=$csrfToken; sede=''; volver='/modulos/logistica/envios.php' } }
            $null = Read-PageHttp '/logout.php'
            $cleanupMessage = 'Sede de la sesión QA restaurada y sesión cerrada.'
        } catch { $cleanupMessage = 'No se pudo completar la limpieza de la sesión QA: ' + $_.Exception.Message }
    }
}

$passed = @($checks | Where-Object { $_.ok }).Count
$report = [ordered]@{
    ok = ($null -eq $failureMessage)
    suite = 'Páginas independientes de Compras, Inventarios y Logística'
    fecha = [DateTimeOffset]::Now.ToString('o')
    pasadas = $passed
    fallos = @($checks | Where-Object { -not $_.ok }).Count
    casos = @($checks.ToArray())
    limpieza = $cleanupMessage
    alcance = 'HTTP real sin ejecutar JavaScript; sólo GET y POST de acceso/cambio de sede. No se crean operaciones comerciales ni logísticas.'
}
if ($null -ne $failureMessage) { $report.error = $failureMessage }
if ($guardPassed) { [IO.File]::WriteAllText($resultPath, ($report | ConvertTo-Json -Depth 8), [Text.UTF8Encoding]::new($false)) }
[pscustomobject]@{ ok=$report.ok; pasadas=$report.pasadas; fallos=$report.fallos; evidencia=$(if ($guardPassed) { $resultPath } else { $null }); error=$failureMessage } | ConvertTo-Json
if (-not $report.ok) { exit 1 }
