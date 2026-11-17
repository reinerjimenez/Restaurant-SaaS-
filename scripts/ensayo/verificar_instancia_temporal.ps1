param([Parameter(Mandatory)][string]$Manifiesto)
$ErrorActionPreference = 'Stop'
$m = Get-Content -LiteralPath $Manifiesto -Raw | ConvertFrom-Json
$hostActual = [Environment]::MachineName
$proc = Get-Process -Id ([int]$m.mariadb_pid) -ErrorAction Stop
$listeners = @(Get-NetTCPConnection -State Listen -LocalPort ([int]$m.puerto) -ErrorAction Stop)
if ($hostActual -ne $m.hostname -or
    [IO.Path]::GetFullPath($proc.Path) -ne [IO.Path]::GetFullPath($m.mariadb_exe) -or
    $listeners.Count -ne 1 -or $listeners[0].LocalAddress -ne '127.0.0.1' -or
    [int]$listeners[0].OwningProcess -ne [int]$m.mariadb_pid) {
    throw 'El proceso o listener no coincide con la instancia temporal.'
}
[pscustomobject]@{ pid=$proc.Id; puerto=[int]$m.puerto; ejecutable=$proc.Path } | ConvertTo-Json -Compress
