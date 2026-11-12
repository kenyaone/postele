<# : Tele Cafe print bridge - double-click to start. Keep this window open.
@echo off
title Tele Cafe print bridge
powershell -NoProfile -ExecutionPolicy Bypass -Command "iex ((Get-Content -LiteralPath '%~f0') -join [Environment]::NewLine)"
pause
goto :eof
#>
$Api = '__API__'
$H = @{ Authorization = 'Bearer __TOKEN__'; Accept = 'application/json' }
Write-Host 'Tele Cafe print bridge is running. Leave this window open (you can minimise it).' -ForegroundColor Green
while ($true) {
    try {
        $r = Invoke-RestMethod -Uri "$Api/jobs/pending" -Headers $H -TimeoutSec 20
        foreach ($j in $r.jobs) {
            if (-not $j.ip) { continue }
            $status = 'sent'; $err = $null
            try {
                $bytes = [Convert]::FromBase64String($j.payload_b64)
                $c = New-Object System.Net.Sockets.TcpClient
                $wait = $c.BeginConnect($j.ip, [int]$j.port, $null, $null)
                if (-not $wait.AsyncWaitHandle.WaitOne(4000)) { $c.Close(); throw "printer $($j.ip) did not answer" }
                $c.EndConnect($wait); $s = $c.GetStream(); $s.Write($bytes, 0, $bytes.Length); $s.Flush(); $c.Close()
                Write-Host "$(Get-Date -Format HH:mm:ss)  printed #$($j.id) on $($j.printer_name)"
            } catch {
                $status = 'failed_attempt'; $err = "$_"
                Write-Host "$(Get-Date -Format HH:mm:ss)  #$($j.id) on $($j.printer_name) FAILED: $err" -ForegroundColor Yellow
            }
            $body = @{ status = $status; error = $err } | ConvertTo-Json
            Invoke-RestMethod -Method Patch -Uri "$Api/jobs/$($j.id)" -Headers $H -ContentType 'application/json' -Body $body -TimeoutSec 20 | Out-Null
        }
    } catch {
        Write-Host "$(Get-Date -Format HH:mm:ss)  cannot reach the POS: $_" -ForegroundColor Red
        Start-Sleep -Seconds 5
    }
    Start-Sleep -Seconds 2
}
