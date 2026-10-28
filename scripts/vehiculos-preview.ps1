param([int]$Port = 8018)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
$previewDatabase = Join-Path (Get-Location) 'storage/app/vehiculos-preview.sqlite'
$previewEnv = Join-Path (Get-Location) '.env.vehiculos-preview'
if (-not (Test-Path -LiteralPath $previewDatabase)) { New-Item -ItemType File -Path $previewDatabase | Out-Null }
if (-not (Test-Path -LiteralPath $previewEnv)) {
    $previewKey = New-Object byte[] 32
    $previewRng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $previewRng.GetBytes($previewKey)
    $previewRng.Dispose()
    $previewSettings = @"
APP_NAME=SAEP
APP_ENV=vehiculos-preview
APP_KEY=base64:$([Convert]::ToBase64String($previewKey))
APP_DEBUG=true
APP_URL=http://127.0.0.1:$Port
APP_TIMEZONE=America/Santiago
DB_CONNECTION=sqlite
DB_DATABASE=$($previewDatabase.Replace('\','/'))
SESSION_DRIVER=file
SESSION_COOKIE=saep_vehiculos_preview
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=array
VEHICLE_ACTAS_ENABLED=true
VEHICLE_ACTAS_MAIL_ENABLED=false
VEHICLE_ACTAS_CALENDAR_ENABLED=false
VEHICLE_RESERVATION_CALENDAR_ENABLED=false
KIZEO_API_TOKEN=
TEAMS_BODEGA_RESERVAS_WEBHOOK_URL=
"@
    [System.IO.File]::WriteAllText($previewEnv, $previewSettings, [System.Text.UTF8Encoding]::new($false))
}
php artisan migrate --env=vehiculos-preview --force
if ($LASTEXITCODE -ne 0) { throw 'Falló la preparación de la base aislada.' }
php artisan vehiculos:demo-local --env=vehiculos-preview
if ($LASTEXITCODE -ne 0) { throw 'Falló la carga de datos de demostración.' }
php artisan serve --env=vehiculos-preview --no-reload --host=127.0.0.1 --port=$Port
