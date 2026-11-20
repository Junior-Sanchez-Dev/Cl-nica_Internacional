param([switch]$DesdePortapapeles)
$ErrorActionPreference = 'Stop'
$ruta = Join-Path (Split-Path $PSScriptRoot -Parent) 'factiliza-config.json'
$ptr = [IntPtr]::Zero
try {
    if ($DesdePortapapeles) {
        $valor = Get-Clipboard -Raw
    } else {
        Write-Host 'Entrada oculta. Si pegar no funciona, usa -DesdePortapapeles despues de copiar el token.'
        $seguro = Read-Host 'Token' -AsSecureString
        $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seguro)
        $valor = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    }
    if ([string]::IsNullOrWhiteSpace($valor)) { throw 'Token vacio; no se guardo.' }
    $valor = $valor.Trim() -replace '^(?i:Bearer)\s+', ''
    if ($valor.Length -lt 8 -or $valor.Length -gt 8192 -or $valor -match '[\s\p{Cc}]' -or $valor -match '^[*•●]+$') {
        throw 'La entrada no parece un token completo. Copia el token con el boton Copiar de Factiliza y usa -DesdePortapapeles. No se modifico el archivo.'
    }
    $json = @{token=$valor} | ConvertTo-Json
    [IO.File]::WriteAllText($ruta,$json,[Text.UTF8Encoding]::new($false))
    Write-Host 'Token guardado fuera del directorio web. No se realizo ninguna consulta ni compra.'
} finally {
    if ($ptr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
    $valor = $null
    $json = $null
}
