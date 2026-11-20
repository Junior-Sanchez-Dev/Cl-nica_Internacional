$ErrorActionPreference='Stop'
$projectDirectory=$PSScriptRoot
$esbDirectory=Join-Path $env:LOCALAPPDATA 'ClinicaInternacional\esb'
$synapseDirectory=Join-Path $esbDirectory 'runtime\synapse-3.0.2'
$javaDirectory=Join-Path $esbDirectory 'java8'
$javaExecutable=Get-ChildItem $javaDirectory -Filter java.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
if (!$javaExecutable -or !(Test-Path "$synapseDirectory\repository\conf\clinica.xml")) {
    throw 'Falta preparar Apache Synapse y el Java compatible en este equipo.'
}
if (!(Get-NetTCPConnection -State Listen -LocalPort 8090 -ErrorAction SilentlyContinue)) {
    & C:\xampp\php\php.exe "$projectDirectory\providers\preparar.php"
    if($LASTEXITCODE -ne 0){throw 'No se pudo preparar el proveedor academico.'}
    Start-Process -FilePath C:\xampp\php\php.exe -ArgumentList @('-S','127.0.0.1:8090','-t',"$projectDirectory\providers","$projectDirectory\providers\router.php") -WorkingDirectory $projectDirectory -WindowStyle Hidden -RedirectStandardOutput "$esbDirectory\proveedores-stdout.log" -RedirectStandardError "$esbDirectory\proveedores-stderr.log" | Out-Null
}
if (!(Get-NetTCPConnection -State Listen -LocalPort 8086 -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath C:\xampp\php\php.exe -ArgumentList @('-S','127.0.0.1:8086','-t',$projectDirectory,"$projectDirectory\esb\backend-router.php") -WorkingDirectory $projectDirectory -WindowStyle Hidden -RedirectStandardOutput "$esbDirectory\backend-stdout.log" -RedirectStandardError "$esbDirectory\backend-stderr.log" | Out-Null
}
if (!(Get-NetTCPConnection -State Listen -LocalPort 8280 -ErrorAction SilentlyContinue)) {
    $javaArguments=@('-Xms64m','-Xmx256m',"-Djava.endorsed.dirs=$synapseDirectory\lib\endorsed",'-Dorg.apache.xerces.xni.parser.XMLParserConfiguration=org.apache.xerces.parsers.XMLGrammarCachingConfiguration',"-Djava.io.tmpdir=$synapseDirectory\work\temp\synapse",'-cp',"$synapseDirectory\lib\*;$synapseDirectory\lib;$synapseDirectory\repository\conf;$synapseDirectory\lib\patches\*",'org.apache.synapse.SynapseServer',"$synapseDirectory\repository","$synapseDirectory\repository\conf\axis2.xml",$synapseDirectory,"$synapseDirectory\repository\conf\clinica.xml","$synapseDirectory\repository")
    $esbProcess=Start-Process -FilePath $javaExecutable -ArgumentList $javaArguments -WorkingDirectory $synapseDirectory -WindowStyle Hidden -RedirectStandardOutput "$esbDirectory\synapse-stdout.log" -RedirectStandardError "$esbDirectory\synapse-stderr.log" -PassThru
    $esbProcess.Id | Set-Content "$esbDirectory\process.id"
}
Write-Host 'Servicios internos preparados. Comprobando el ESB...'
for($attempt=0;$attempt -lt 15;$attempt++) {
    Start-Sleep -Seconds 1
    try {
        $response=Invoke-WebRequest -UseBasicParsing 'http://127.0.0.1:8280/clinica/sesion' -TimeoutSec 2 -ErrorAction Stop
    } catch {
        if($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 403) {
            Write-Host 'Apache Synapse esta activo y rechaza el acceso sin credencial interna.'
            Write-Host 'Abra el proyecto desde https://localhost:8443'
            return
        }
    }
}
Write-Host 'No se confirmo el inicio del ESB. Registro tecnico:'
Get-Content "$esbDirectory\synapse-stderr.log" -Tail 15 -ErrorAction SilentlyContinue
