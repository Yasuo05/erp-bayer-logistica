# Argumentos y resultados nativos compatibles con Windows PowerShell 5.1 y PowerShell 7.
function ConvertTo-ErpNativeArguments {
    param([string[]]$ArgumentList)
    $encoded = foreach ($argument in $ArgumentList) {
        $value = [string]$argument
        # Reglas de CommandLineToArgvW: duplicar barras antes de comillas y al final.
        '"' + ([regex]::Replace(([regex]::Replace($value, '(\\*)"', '$1$1\"')), '(\\+)$', '$1$1')) + '"'
    }
    return ($encoded -join ' ')
}

function Invoke-ErpNative {
    param([string]$FilePath, [string[]]$ArgumentList, [int]$TimeoutMilliseconds = 15000)
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $FilePath
    $startInfo.Arguments = ConvertTo-ErpNativeArguments $ArgumentList
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $nativeProcess = New-Object System.Diagnostics.Process
    $nativeProcess.StartInfo = $startInfo
    try {
        if (-not $nativeProcess.Start()) { throw "No se pudo ejecutar $FilePath" }
        $stdoutTask = $nativeProcess.StandardOutput.ReadToEndAsync()
        $stderrTask = $nativeProcess.StandardError.ReadToEndAsync()
        if (-not $nativeProcess.WaitForExit($TimeoutMilliseconds)) {
            $nativeProcess.Kill()
            $nativeProcess.WaitForExit()
            throw "El proceso excedió el tiempo de espera: $FilePath"
        }
        return [pscustomobject]@{
            ExitCode = $nativeProcess.ExitCode
            StandardOutput = $stdoutTask.GetAwaiter().GetResult()
            StandardError = $stderrTask.GetAwaiter().GetResult()
        }
    } finally { $nativeProcess.Dispose() }
}

function Save-ErpProcessState {
    param($Process, [string]$FilePath, [string]$StatePath)
    $Process.Refresh()
    @{id=$Process.Id; executable=[IO.Path]::GetFullPath($FilePath); started_utc_ticks=$Process.StartTime.ToUniversalTime().Ticks.ToString()} |
        ConvertTo-Json | Set-Content -LiteralPath $StatePath -Encoding UTF8
}

function Get-ErpOwnedProcess {
    param([string]$StatePath, [string]$FilePath)
    if (-not (Test-Path -LiteralPath $StatePath)) { return $null }
    $state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
    $owned = Get-Process -Id ([int]$state.id) -ErrorAction SilentlyContinue
    if (-not $owned) { return $null }
    if ($owned.StartTime.ToUniversalTime().Ticks.ToString() -ne [string]$state.started_utc_ticks -or
        [IO.Path]::GetFullPath($owned.Path) -ne [IO.Path]::GetFullPath($FilePath) -or
        [IO.Path]::GetFullPath($state.executable) -ne [IO.Path]::GetFullPath($FilePath)) { return $null }
    return $owned
}
