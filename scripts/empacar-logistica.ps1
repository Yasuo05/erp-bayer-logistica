param([string]$Destino = '')
$ErrorActionPreference = 'Stop'
$project = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ($Destino -eq '') { $Destino = Join-Path (Split-Path -Parent $project) 'output\ERP_Logistica_Pedro_Alisson_2026-11-17.zip' }
$archivePath = [IO.Path]::GetFullPath($Destino)
if ($archivePath.StartsWith($project+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'Guarda el paquete fuera de la carpeta fuente.' }
if (Test-Path -LiteralPath $archivePath) { throw 'El paquete ya existe. Usa -Destino con otro nombre para conservarlo.' }
New-Item -ItemType Directory -Path (Split-Path -Parent $archivePath) -Force | Out-Null
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$files = Get-ChildItem -LiteralPath $project -File -Recurse -Force | Where-Object {
    $relative = $_.FullName.Substring($project.Length+1).Replace('\','/')
    $relative -notmatch '(^|/)(\.runtime|\.git|vendor|node_modules|output)(/|$)' -and
    (-not $_.Name.StartsWith('.env') -or $_.Name -eq '.env.example') -and
    $_.Extension -notin @('.log','.pfx','.p12','.key')
}
$stream = [IO.File]::Open($archivePath,[IO.FileMode]::CreateNew)
$archive = [IO.Compression.ZipArchive]::new($stream,[IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($project.Length+1).Replace('\','/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive,$file.FullName,('ERP-main/'+$relative),[IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally { $archive.Dispose(); $stream.Dispose() }
$verify = [IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    if (@($verify.Entries | Where-Object { $_.FullName -match '/\.runtime/|/\.env$|/vendor/' }).Count -gt 0) { throw 'El paquete contiene archivos locales excluidos.' }
    if (-not $verify.GetEntry('ERP-main/LEEME_LOGISTICA.md') -or -not $verify.GetEntry('ERP-main/sql/migrations/001_logistica_operativa.sql')) { throw 'Faltan archivos de entrega.' }
    [pscustomobject]@{paquete=$archivePath;archivos=$verify.Entries.Count;bytes=(Get-Item -LiteralPath $archivePath).Length;sha256=(Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash} | ConvertTo-Json
} finally { $verify.Dispose() }
