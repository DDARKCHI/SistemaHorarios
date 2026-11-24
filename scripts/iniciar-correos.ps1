$ErrorActionPreference = 'Stop'
$proyecto = Split-Path -Parent $PSScriptRoot
$artisan = Join-Path $proyecto 'artisan'
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php)) {
    $php = (Get-Command php -ErrorAction Stop).Source
}
function Iniciar-ProcesoProsec {
    param(
        [string] $Comando,
        [string] $Descripcion,
        [string] $Registro,
        [string[]] $Opciones = @(),
        [string] $Cola = ''
    )

    $procesos = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
        $_.CommandLine -like "*$artisan*" -and $_.CommandLine -like "*$Comando*" -and
        (-not $Cola -or $_.CommandLine -like "*--queue=$Cola*")
    }
    if ($procesos) {
        Write-Output "$Descripcion. Proceso activo."
        return
    }

    $arranque = @{
        FilePath = $php
        ArgumentList = @(('"' + $artisan + '"'), $Comando) + $Opciones
        WorkingDirectory = $proyecto
        WindowStyle = 'Hidden'
        PassThru = $true
        RedirectStandardOutput = Join-Path $proyecto "storage\logs\$Registro.log"
        RedirectStandardError = Join-Path $proyecto "storage\logs\$Registro-error.log"
    }
    $proceso = Start-Process @arranque
    Write-Output "$Descripcion. Proceso $($proceso.Id) activo."
}

$colaDisponibilidad = @{
    Comando = 'queue:work'
    Descripcion = 'Correo de disponibilidad'
    Registro = 'accesos-docentes-worker'
    Cola = 'accesos-docentes'
    Opciones = @('database', '--queue=accesos-docentes', '--sleep=2', '--tries=1', '--timeout=60')
}
Iniciar-ProcesoProsec @colaDisponibilidad
Iniciar-ProcesoProsec -Comando 'schedule:work' -Descripcion 'Recordatorios de disponibilidad' -Registro 'disponibilidad-scheduler'
