param([Parameter(Mandatory)][string]$Manifiesto)
$ErrorActionPreference = 'Stop'
$m = Get-Content -LiteralPath $Manifiesto -Raw | ConvertFrom-Json
$vm = (Get-CimInstance Win32_ComputerSystemProduct).UUID
$os = (Get-CimInstance Win32_OperatingSystem).Caption
$host_actual = [Environment]::MachineName
$adaptadores = @(Get-NetAdapter | Where-Object Status -eq 'Up')
$rutas = @(Get-NetRoute -DestinationPrefix '0.0.0.0/0','::/0' -ErrorAction SilentlyContinue)
$tuneles = @(Get-Process -Name ssh,sshd,ngrok,cloudflared,tailscale,tailscaled,frpc -ErrorAction SilentlyContinue)
$db = @(Get-NetTCPConnection -State Listen -LocalPort 3306)
$web = @(Get-NetTCPConnection -State Listen | Where-Object LocalPort -in 80,443)
if ($vm -ne $m.vm_uuid -or $host_actual -ne $m.hostname -or $os -ne $m.os_caption -or
    $adaptadores.Count -ne 0 -or $rutas.Count -ne 0 -or $tuneles.Count -ne 0 -or
    $db.Count -ne 1 -or $db[0].LocalAddress -ne '127.0.0.1' -or
    $db[0].OwningProcess -ne [int]$m.mariadb_pid -or
    @($web | Where-Object LocalAddress -notin @('127.0.0.1','::1')).Count -ne 0) {
    throw 'Identidad, red o listeners de la VM no coinciden con el manifiesto.'
}
$proc = Get-CimInstance Win32_Process -Filter "ProcessId=$($db[0].OwningProcess)"
if (-not $proc -or (Split-Path $proc.ExecutablePath -Leaf) -notin @('mysqld.exe','mariadbd.exe') -or
    [IO.Path]::GetFullPath($proc.ExecutablePath) -ne [IO.Path]::GetFullPath($m.mariadb_exe)) {
    throw 'El proceso de MariaDB no coincide.'
}
[pscustomobject]@{ vm_uuid=$vm; hostname=$host_actual; os_caption=$os;
    mariadb_pid=$db[0].OwningProcess; mariadb_exe=$proc.ExecutablePath;
    listener='127.0.0.1:3306'; adapters_up=0; default_routes=0; tunnels_detected=0 } |
    ConvertTo-Json -Compress
