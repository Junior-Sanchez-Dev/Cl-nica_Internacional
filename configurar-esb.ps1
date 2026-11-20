$ErrorActionPreference='Stop'
# Instalacion local gratuita de Apache Synapse. No instala un registro UDDI.
$projectDirectory=$PSScriptRoot
$esbDirectory=Join-Path $env:LOCALAPPDATA 'ClinicaInternacional\esb'
$synapseDirectory=Join-Path $esbDirectory 'runtime\synapse-3.0.2'
New-Item -ItemType Directory -Force $esbDirectory | Out-Null
if(!(Test-Path "$synapseDirectory\repository\conf\axis2.xml")) {
    $url='https://downloads.apache.org/synapse/3.0.2/synapse-distribution-3.0.2-bin.zip'
    Write-Host 'Descargando Apache Synapse desde Apache...'
    Invoke-WebRequest -UseBasicParsing $url -OutFile "$esbDirectory\synapse.zip"
    $checksum=(Invoke-WebRequest -UseBasicParsing ($url+'.sha1')).Content.Trim()
    if((Get-FileHash "$esbDirectory\synapse.zip" -Algorithm SHA1).Hash -ine $checksum){throw 'El archivo de Synapse no coincide con la suma publicada.'}
    Expand-Archive -LiteralPath "$esbDirectory\synapse.zip" -DestinationPath "$esbDirectory\runtime" -Force
}
if(!(Get-ChildItem "$esbDirectory\java8" -Recurse -Filter java.exe -ErrorAction SilentlyContinue)) {
    Write-Host 'Preparando Java compatible solo para el ESB...'
    $assets=Invoke-RestMethod 'https://api.adoptium.net/v3/assets/latest/8/hotspot?architecture=x64&image_type=jre&os=windows&vendor=eclipse'
    $package=$assets[0].binary.package
    Invoke-WebRequest -UseBasicParsing $package.link -OutFile "$esbDirectory\java8.zip"
    if((Get-FileHash "$esbDirectory\java8.zip" -Algorithm SHA256).Hash -ine $package.checksum){throw 'El archivo de Java no coincide con la suma publicada.'}
    Expand-Archive -LiteralPath "$esbDirectory\java8.zip" -DestinationPath "$esbDirectory\java8" -Force
}
$configPath=Join-Path (Split-Path $projectDirectory -Parent) 'esb-config.json'
if(!(Test-Path -LiteralPath $configPath)) {
    $bytes=New-Object byte[] 32
    $generator=[System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {$generator.GetBytes($bytes)} finally {$generator.Dispose()}
    $initialConfig=@{token=([BitConverter]::ToString($bytes)).Replace('-','').ToLowerInvariant();enabled=$true} | ConvertTo-Json
    [System.IO.File]::WriteAllText($configPath,$initialConfig,(New-Object System.Text.UTF8Encoding($false)))
}
$config=Get-Content -Raw -LiteralPath $configPath | ConvertFrom-Json
if($config.token -notmatch '^[a-f0-9]{64}$'){throw 'La configuracion interna no es valida.'}
$config.enabled=$true
[System.IO.File]::WriteAllText($configPath,($config | ConvertTo-Json),(New-Object System.Text.UTF8Encoding($false)))
$template=Get-Content -Raw "$projectDirectory\esb\clinica.xml"
$template.Replace('__ESB_TOKEN__',$config.token) | Set-Content -Encoding UTF8 "$synapseDirectory\repository\conf\clinica.xml"
[xml]$axis=Get-Content -Raw "$synapseDirectory\repository\conf\axis2.xml"
foreach($receiver in @($axis.axisconfig.transportReceiver)) {
    if($receiver.name -ne 'http'){[void]$receiver.ParentNode.RemoveChild($receiver)}
    else {
        $bind=$receiver.SelectSingleNode("parameter[@name='bind-address']")
        if(!$bind){$bind=$axis.CreateElement('parameter');$bind.SetAttribute('name','bind-address');[void]$receiver.AppendChild($bind)}
        $bind.InnerText='127.0.0.1'
    }
}
$axis.Save("$synapseDirectory\repository\conf\axis2.xml")
$properties=Get-Content -Raw "$synapseDirectory\repository\conf\synapse.properties"
$properties=[regex]::Replace($properties,'(?m)^synapse\.jmx\.jndiPort=.*$','synapse.jmx.jndiPort=-1')
$properties | Set-Content -Encoding UTF8 "$synapseDirectory\repository\conf\synapse.properties"
New-Item -ItemType Directory -Force "$synapseDirectory\work\temp\synapse" | Out-Null
Write-Host 'ESB configurado. Inicie con .\iniciar-esb.ps1'
