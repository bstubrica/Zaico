<#
.SYNOPSIS
    Configura y pone en marcha Zaico (Laravel 13) en Windows.

.DESCRIPTION
    Automatiza toda la puesta en marcha en un equipo nuevo:
      1. Verifica requisitos (PHP 8.3+, Composer, Node.js) y los localiza
         automáticamente si no están en el PATH.
      2. composer install.
      3. Crea .env desde .env.example y genera APP_KEY.
      4. Configura la base de datos (PostgreSQL por defecto, o SQLite sin
         instalacion extra con -Database sqlite).
      5. Ejecuta migraciones + seeders (idempotente).
      6. Crea el enlace de storage para evidencias.
      7. Instala dependencias de Node y compila los assets (Vite).
      8. Con -Optimize, ejecuta `php artisan optimize` (cache de config,
         rutas y vistas) para el servidor de produccion.

    Es idempotente: puede ejecutarse las veces que haga falta sin romper nada.

.PARAMETER Database
    Motor de base de datos local. `pgsql` (por defecto) requiere un servidor
    PostgreSQL en ejecución; `sqlite` no requiere instalar nada.

.PARAMETER PgHost
    Host de PostgreSQL (por defecto 127.0.0.1).

.PARAMETER PgPort
    Puerto de PostgreSQL (por defecto 5432).

.PARAMETER PgDatabase
    Nombre de la base de datos (por defecto Zaico).

.PARAMETER PgUsername
    Usuario de PostgreSQL (por defecto postgres).

.PARAMETER PgPassword
    Contraseña de PostgreSQL.

.PARAMETER Fresh
    Recrea la base de datos desde cero (migrate:fresh --seed). Destruye datos.

.PARAMETER SkipBuild
    Omite npm install / npm run build (si los assets ya están compilados).

.PARAMETER Serve
    Levanta `php artisan serve` al terminar (bloquea la consola).

.PARAMETER Optimize
    Ejecuta `php artisan optimize` al terminar (cache de config, rutas y
    vistas). Recomendado en el servidor de produccion; en desarrollo se omite
    para no cachear cambios de .env, rutas o vistas.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\setup.ps1

.EXAMPLE
    .\setup.ps1 -Database sqlite -SkipBuild

.EXAMPLE
    .\setup.ps1 -PgPassword "mi_clave" -Fresh -Serve

.EXAMPLE
    .\setup.ps1 -Optimize
#>

[CmdletBinding()]
param(
    [ValidateSet('sqlite', 'pgsql')]
    [string]$Database = 'pgsql',

    [string]$PgHost = '127.0.0.1',
    [int]$PgPort = 5432,
    [string]$PgDatabase = 'Zaico',
    [string]$PgUsername = 'postgres',
    [string]$PgPassword = '',

    [switch]$Fresh,
    [switch]$SkipBuild,
    [switch]$Serve,
    [switch]$Optimize
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Write-Step {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Write-Ok {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "    OK  $Message" -ForegroundColor Green
}

function Write-Warn {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "    !   $Message" -ForegroundColor Yellow
}

function Write-Fail {
    param([Parameter(Mandatory)][string]$Message)
    Write-Host "    X   $Message" -ForegroundColor Red
}

function Invoke-Native {
    param(
        [Parameter(Mandatory)][string]$File,
        [string[]]$Arguments = @(),
        [Parameter(Mandatory)][string]$ErrorMessage
    )

    & $File @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$ErrorMessage (codigo de salida $LASTEXITCODE)."
    }
}

function Get-SalidaNativa {
    param(
        [Parameter(Mandatory)][string]$File,
        [string[]]$Arguments = @()
    )

    # En PowerShell 5.1, con EAP=Stop el stderr redirigido de un comando
    # nativo (2>&1) se convierte en excepcion; se baja el EAP solo durante
    # la captura para recoger stdout y stderr como texto plano. Devuelve
    # tambien el codigo de salida porque dentro de una funcion no se
    # propaga $LASTEXITCODE al ambito que llama.
    $previo = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $texto = (& $File @Arguments 2>&1 | Out-String)
        return [pscustomobject]@{ Texto = $texto; Codigo = $LASTEXITCODE }
    } finally {
        $ErrorActionPreference = $previo
    }
}

function Find-Executable {
    param(
        [Parameter(Mandatory)][string]$Name,
        [string[]]$Globs = @()
    )

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $found = @()
    foreach ($glob in $Globs) {
        $found += Get-ChildItem -Path $glob -ErrorAction SilentlyContinue
    }

    if ($found.Count -gt 0) {
        return ($found | Sort-Object FullName -Descending | Select-Object -First 1).FullName
    }

    return $null
}

function Set-EnvVar {
    param(
        [Parameter(Mandatory)][string]$Path,
        [Parameter(Mandatory)][string]$Key,
        [string]$Value = '',
        [switch]$Disable
    )

    $content = [System.IO.File]::ReadAllText($Path)
    $line = if ($Disable) { "# $Key=" } else { "$Key=$Value" }
    $pattern = '(?m)^[ \t]*#?[ \t]*' + [regex]::Escape($Key) + '=.*$'
    $regex = [System.Text.RegularExpressions.Regex]::new($pattern)

    if ($regex.IsMatch($content)) {
        $content = $regex.Replace($content, { param($match) $line }, 1)
    } else {
        $content = $content.TrimEnd() + "`r`n" + $line + "`r`n"
    }

    [System.IO.File]::WriteAllText($Path, $content)
}

function Install-PhpDependencies {
    param([Parameter(Mandatory)][string]$Php, [string]$Composer)

    if ($Composer -like '*.phar') {
        Invoke-Native -File $Php -Arguments @($Composer, 'install', '--no-interaction') -ErrorMessage 'Fallo `composer install`'
    } else {
        Invoke-Native -File $Composer -Arguments @('install', '--no-interaction') -ErrorMessage 'Fallo `composer install`'
    }
}

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location -LiteralPath $projectRoot

Write-Host "Zaico - configuracion automatica para Windows" -ForegroundColor White
Write-Host "Directorio: $projectRoot"

if (-not (Test-Path -LiteralPath 'artisan')) {
    Write-Fail 'No se encontro artisan. Ejecuta el script desde la raiz del proyecto.'
    exit 1
}

# --- 1. Requisitos -----------------------------------------------------------

Write-Step 'Verificando requisitos'

$php = Find-Executable -Name 'php' -Globs @(
    'C:\laragon\bin\php\php-8.*\php.exe',
    'C:\laragon\bin\php\*\php.exe',
    'C:\xampp\php\php.exe',
    'C:\wamp64\bin\php\php*\php.exe',
    'C:\tools\php*\php.exe',
    'C:\Program Files\PHP\*\php.exe'
)
if (-not $php) {
    Write-Fail 'PHP no encontrado. Instala PHP 8.3+ y agregalo al PATH.'
    exit 1
}

$phpVersion = (& $php -r 'echo PHP_VERSION;').Trim()
if ($phpVersion -notmatch '^(\d+)\.(\d+)') {
    Write-Fail "No se pudo interpretar la version de PHP: $phpVersion"
    exit 1
}
$phpMajor = [int]$Matches[1]
$phpMinor = [int]$Matches[2]
if ($phpMajor -lt 8 -or ($phpMajor -eq 8 -and $phpMinor -lt 3)) {
    Write-Fail "Se requiere PHP 8.3+ y hay $phpVersion."
    exit 1
}
Write-Ok "PHP $phpVersion ($php)"

$requiredExtensions = @('mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl')
if ($Database -eq 'sqlite') {
    $requiredExtensions += 'pdo_sqlite'
} else {
    $requiredExtensions += @('pdo_pgsql', 'pgsql')
}
$loadedExtensions = & $php -m
$missingExtensions = @($requiredExtensions | Where-Object { $loadedExtensions -notcontains $_ })
if ($missingExtensions.Count -gt 0) {
    Write-Fail ('Faltan extensiones de PHP: ' + ($missingExtensions -join ', '))
    exit 1
}
Write-Ok 'Extensiones de PHP requeridas presentes'

$composer = Find-Executable -Name 'composer' -Globs @(
    'C:\ProgramData\ComposerSetup\bin\composer.bat',
    'C:\ProgramData\ComposerSetup\bin\composer.phar',
    'C:\laragon\bin\composer\composer.phar'
)
if (-not $composer) {
    Write-Fail 'Composer no encontrado. Instala Composer 2 (https://getcomposer.org).'
    exit 1
}

$composerVersionSalida = if ($composer -like '*.phar') {
    Get-SalidaNativa -File $php -Arguments @($composer, '--version', '--no-ansi')
} else {
    Get-SalidaNativa -File $composer -Arguments @('--version', '--no-ansi')
}
$composerVersionLine = $composerVersionSalida.Texto
if ($composerVersionLine -notmatch 'Composer[^\d]*(\d+)\.(\d+)') {
    Write-Fail 'No se pudo determinar la version de Composer (se requiere Composer 2).'
    exit 1
}
$composerVersionDetectada = "$($Matches[1]).$($Matches[2])"
if ([int]$Matches[1] -lt 2) {
    Write-Fail "Se requiere Composer 2 y hay Composer $composerVersionDetectada."
    exit 1
}
Write-Ok "Composer $composerVersionDetectada ($composer)"

$npm = $null
$node = $null
if (-not $SkipBuild) {
    $node = Find-Executable -Name 'node.exe' -Globs @('C:\Program Files\nodejs\node.exe')
    $npm = Find-Executable -Name 'npm.cmd' -Globs @('C:\Program Files\nodejs\npm.cmd')
    if (-not $node -or -not $npm) {
        Write-Fail 'Node.js/npm no encontrados. Instala Node.js LTS (https://nodejs.org) o usa -SkipBuild.'
        exit 1
    }
    $nodeVersion = (& $node -v).Trim()
    if ($nodeVersion -notmatch '^v(\d+)\.') {
        Write-Fail "No se pudo interpretar la version de Node: $nodeVersion"
        exit 1
    }
    if ([int]$Matches[1] -lt 18) {
        Write-Fail "Se requiere Node.js 18+ y hay $nodeVersion."
        exit 1
    }
    Write-Ok "Node $nodeVersion ($node)"
}

$psql = $null
if ($Database -eq 'pgsql') {
    # La contrasena se pide aqui (si no vino por parametro) para poder probar
    # la conexion ANTES de tocar nada: si el servidor esta caido, aborta con
    # instrucciones en lugar de dejar la instalacion a medias.
    if ([string]::IsNullOrEmpty($PgPassword)) {
        $securePassword = Read-Host 'Contrasena de PostgreSQL' -AsSecureString
        $PgPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(
            [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        )
    }

    $psql = Find-Executable -Name 'psql' -Globs @('C:\Program Files\PostgreSQL\*\bin\psql.exe')
    if ($psql) {
        $env:PGPASSWORD = $PgPassword
        try {
            $testConexion = Get-SalidaNativa -File $psql -Arguments @('-h', $PgHost, '-p', $PgPort, '-U', $PgUsername, '-d', 'postgres', '-tAc', 'SELECT 1')
            $conexionOk = ($testConexion.Codigo -eq 0 -and ($testConexion.Texto.Trim() -eq '1'))
        } finally {
            Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
        }

        if (-not $conexionOk) {
            Write-Fail "No se pudo conectar a PostgreSQL en $PgHost`:$PgPort (usuario $PgUsername)."
            Write-Host '    El servicio puede estar detenido o la autenticacion rechazada.' -ForegroundColor Yellow
            Write-Host '    Soluciones: iniciar el servicio de PostgreSQL (servicios de Windows),' -ForegroundColor Yellow
            Write-Host '    verificar la contrasena, o instalar sin servidor con:' -ForegroundColor Yellow
            Write-Host '        .\setup.ps1 -Database sqlite' -ForegroundColor Gray
            exit 1
        }
        Write-Ok "PostgreSQL accesible en $PgHost`:$PgPort"
    } else {
        Write-Warn 'psql no encontrado: no se puede verificar la conexion. Asegurate de que PostgreSQL este en ejecucion.'
    }
}

# --- 2. Dependencias PHP -----------------------------------------------------

Write-Step 'Instalando dependencias PHP (composer install)'
Install-PhpDependencies -Php $php -Composer $composer
Write-Ok 'Dependencias PHP instaladas'

# --- 3. Entorno --------------------------------------------------------------

Write-Step 'Configurando el entorno (.env)'
if (-not (Test-Path -LiteralPath '.env.example')) {
    Write-Fail 'No existe .env.example. El repositorio parece incompleto.'
    exit 1
}
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item -LiteralPath '.env.example' -Destination '.env'
    Write-Ok '.env creado desde .env.example'
} else {
    Write-Ok '.env ya existe (no se sobrescribe)'
}

$envContent = [System.IO.File]::ReadAllText((Join-Path $projectRoot '.env'))
if ($envContent -notmatch '(?m)^APP_KEY=.+$') {
    Invoke-Native -File $php -Arguments @('artisan', 'key:generate', '--no-interaction') -ErrorMessage 'Fallo `key:generate`'
    Write-Ok 'APP_KEY generada'
} else {
    Write-Ok 'APP_KEY ya configurada'
}

# --- 4. Base de datos --------------------------------------------------------

Write-Step "Configurando base de datos ($Database)"
$envPath = Join-Path $projectRoot '.env'

if ($Database -eq 'sqlite') {
    Set-EnvVar -Path $envPath -Key 'DB_CONNECTION' -Value 'sqlite'
    Set-EnvVar -Path $envPath -Key 'DB_HOST' -Disable
    Set-EnvVar -Path $envPath -Key 'DB_PORT' -Disable
    Set-EnvVar -Path $envPath -Key 'DB_DATABASE' -Disable
    Set-EnvVar -Path $envPath -Key 'DB_USERNAME' -Disable
    Set-EnvVar -Path $envPath -Key 'DB_PASSWORD' -Disable

    $sqliteFile = Join-Path $projectRoot 'database\database.sqlite'
    if (-not (Test-Path -LiteralPath $sqliteFile)) {
        New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
        Write-Ok 'Creado database/database.sqlite'
    } else {
        Write-Ok 'database/database.sqlite ya existe'
    }
} else {
    # $PgPassword se recogio (o valido) en la seccion de requisitos.
    Set-EnvVar -Path $envPath -Key 'DB_CONNECTION' -Value 'pgsql'
    Set-EnvVar -Path $envPath -Key 'DB_HOST' -Value $PgHost
    Set-EnvVar -Path $envPath -Key 'DB_PORT' -Value $PgPort
    Set-EnvVar -Path $envPath -Key 'DB_DATABASE' -Value $PgDatabase
    Set-EnvVar -Path $envPath -Key 'DB_USERNAME' -Value $PgUsername
    Set-EnvVar -Path $envPath -Key 'DB_PASSWORD' -Value $PgPassword
    Write-Ok "PostgreSQL $PgHost`:$PgPort/$PgDatabase (usuario $PgUsername)"

    if ($psql) {
        # La contrasena vive solo en el entorno de este proceso y se limpia al
        # terminar: no queda expuesta para procesos hijos ni en el historial.
        $env:PGPASSWORD = $PgPassword
        try {
            foreach ($database in @($PgDatabase, 'Zaico_test') | Select-Object -Unique) {
                $exists = & $psql -h $PgHost -p $PgPort -U $PgUsername -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='$database'"
                if (($exists -join '').Trim() -ne '1') {
                    Invoke-Native -File $psql -Arguments @('-h', $PgHost, '-p', $PgPort, '-U', $PgUsername, '-d', 'postgres', '-c', "CREATE DATABASE `"$database`"") -ErrorMessage "No se pudo crear la base $database"
                    Write-Ok "Base $database creada"
                } else {
                    Write-Ok "Base $database ya existe"
                }
            }
        } finally {
            Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
        }
    } else {
        Write-Warn "psql no encontrado: asegurate de que las bases $PgDatabase y Zaico_test existan."
    }
}

# --- 5. Base de datos: esquema y datos ---------------------------------------

Write-Step 'Preparando la base de datos (migraciones + seeders)'
if ($Fresh) {
    Invoke-Native -File $php -Arguments @('artisan', 'migrate:fresh', '--seed', '--force') -ErrorMessage 'Fallo `migrate:fresh`'
} else {
    Invoke-Native -File $php -Arguments @('artisan', 'migrate', '--force') -ErrorMessage 'Fallo `migrate`'
    Invoke-Native -File $php -Arguments @('artisan', 'db:seed', '--force') -ErrorMessage 'Fallo `db:seed`'
}
Write-Ok 'Esquema y datos listos'

# --- 6. Enlace de storage ----------------------------------------------------

Write-Step 'Creando enlace de storage (evidencias)'
if (Test-Path -LiteralPath (Join-Path $projectRoot 'public\storage')) {
    Write-Ok 'El enlace de storage ya existe'
} else {
    Invoke-Native -File $php -Arguments @('artisan', 'storage:link') -ErrorMessage 'Fallo `storage:link`'
    Write-Ok 'storage/app/public enlazado'
}

# --- 7. Assets frontend ------------------------------------------------------

if (-not $SkipBuild) {
    Write-Step 'Instalando dependencias de Node (npm install)'
    Invoke-Native -File $npm -Arguments @('install', '--ignore-scripts') -ErrorMessage 'Fallo `npm install`'
    Write-Ok 'Dependencias de Node instaladas'

    Write-Step 'Compilando assets (npm run build)'
    Invoke-Native -File $npm -Arguments @('run', 'build') -ErrorMessage 'Fallo `npm run build`'
    Write-Ok 'Assets compilados'
} else {
    Write-Warn 'Se omitio la compilacion de assets (-SkipBuild)'
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'public\build\manifest.json'))) {
        Write-Warn 'No existe public/build/manifest.json: la aplicacion fallara al cargar. Ejecuta `npm run build` sin -SkipBuild.'
    }
}

# --- 8. Cache de rendimiento (produccion) -------------------------------------

if ($Optimize) {
    Write-Step 'Optimizando la aplicacion (php artisan optimize)'
    Invoke-Native -File $php -Arguments @('artisan', 'optimize', '--no-interaction') -ErrorMessage 'Fallo `php artisan optimize`'
    Write-Ok 'Cache de config, rutas y vistas generada (al retocar .env/rutas: `php artisan optimize:clear`)'
} else {
    Write-Warn 'Cache de optimizacion omitida: usa -Optimize en el servidor de produccion'
}

# --- Resumen -----------------------------------------------------------------

Write-Step 'Configuracion terminada'
Write-Host '    Usuarios de prueba (inicio de sesion por USUARIO):' -ForegroundColor White
Write-Host '      admin     / Admin123!      (Admin)' -ForegroundColor Gray
Write-Host '      soporte   / Soporte123!    (SoporteTecnico)' -ForegroundColor Gray
Write-Host '      auditor   / Auditor123!    (Auditor)' -ForegroundColor Gray
Write-Host ''
Write-Host '    Para arrancar la aplicacion:' -ForegroundColor White
Write-Host '      php artisan serve        ->  http://127.0.0.1:8000' -ForegroundColor Gray
Write-Host '      composer run dev         ->  servidor + Vite + colas + logs' -ForegroundColor Gray
Write-Host ''
Write-Host '    Otras utilidades:' -ForegroundColor White
Write-Host '      .\setup.ps1 -Fresh       ->  recrea la BD y siembra de cero' -ForegroundColor Gray
Write-Host '      .\setup.ps1 -Database sqlite  ->  sin PostgreSQL instalado' -ForegroundColor Gray
Write-Host ''

if ($Serve) {
    Write-Host 'Arrancando php artisan serve (Ctrl+C para detener)...' -ForegroundColor Cyan
    & $php artisan serve
}
