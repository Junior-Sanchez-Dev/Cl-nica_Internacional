$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
& C:\xampp\php\php.exe providers/preparar.php
& C:\xampp\php\php.exe -S 127.0.0.1:8090 -t providers providers/router.php
