$phpDir = Join-Path $PSScriptRoot 'php'
$cacert = Join-Path $phpDir 'cacert.pem'
$ini = Join-Path $phpDir 'php.ini'

Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -OutFile $cacert -UseBasicParsing

if (Test-Path $ini) {
    $content = Get-Content $ini -Raw
    $cacertPath = $cacert -replace '\\', '/'
    if ($content -notmatch 'curl.cainfo') {
        $content += "`ncurl.cainfo = `"$cacertPath`"`n"
    } else {
        $content = $content -replace ';curl.cainfo.*', "curl.cainfo = `"$cacertPath`""
        $content = $content -replace 'curl.cainfo = .*', "curl.cainfo = `"$cacertPath`""
    }
    if ($content -notmatch 'openssl.cafile') {
        $content += "openssl.cafile = `"$cacertPath`"`n"
    } else {
        $content = $content -replace ';openssl.cafile.*', "openssl.cafile = `"$cacertPath`""
        $content = $content -replace 'openssl.cafile = .*', "openssl.cafile = `"$cacertPath`""
    }
    Set-Content $ini $content -Encoding UTF8
}

Write-Host "SSL 证书配置完成"
