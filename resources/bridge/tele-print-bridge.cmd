<# : Tele Cafe print bridge - double-click to start. Keep this window open.
@echo off
title Tele Cafe print bridge
powershell -NoProfile -ExecutionPolicy Bypass -Command "iex ((Get-Content -LiteralPath '%~f0') -join [Environment]::NewLine)"
pause
goto :eof
#>
$Api = '__API__'
$H = @{ Authorization = 'Bearer __TOKEN__'; Accept = 'application/json' }
Add-Type -TypeDefinition @'
using System; using System.Runtime.InteropServices;
public class TeleRawPrint {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public class DOCINFO { public string pDocName; public string pOutputFile; public string pDataType; }
    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)] static extern bool OpenPrinter(string name, out IntPtr h, IntPtr d);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool ClosePrinter(IntPtr h);
    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)] static extern int StartDocPrinter(IntPtr h, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFO di);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)] static extern bool WritePrinter(IntPtr h, byte[] b, int n, out int w);
    public static void Send(string printer, byte[] data) {
        IntPtr h;
        if (!OpenPrinter(printer, out h, IntPtr.Zero)) throw new Exception("no printer called '" + printer + "' on this PC");
        try {
            DOCINFO di = new DOCINFO(); di.pDocName = "Tele POS"; di.pDataType = "RAW";
            if (StartDocPrinter(h, 1, di) == 0) throw new Exception("Windows would not start the print job");
            StartPagePrinter(h); int w; WritePrinter(h, data, data.Length, out w); EndPagePrinter(h); EndDocPrinter(h);
            if (w != data.Length) throw new Exception("printer took only part of the slip");
        } finally { ClosePrinter(h); }
    }
}
'@
Add-Type -AssemblyName System.Drawing
Write-Host 'Tele Cafe print bridge is running. Leave this window open (you can minimise it).' -ForegroundColor Green
Write-Host 'Printers on this PC (type the USB one exactly like this on the Printers page):'
[System.Drawing.Printing.PrinterSettings]::InstalledPrinters | ForEach-Object { Write-Host "   $_" -ForegroundColor Cyan }
function Resolve-TelePrinter([string]$want) {
    $names = @([System.Drawing.Printing.PrinterSettings]::InstalledPrinters)
    $m = @($names | Where-Object { $_ -ieq $want }); if ($m.Count -ge 1) { return $m[0] }
    $m = @($names | Where-Object { $_ -like "*$want*" -or $want -like "*$_*" }); if ($m.Count -eq 1) { return $m[0] }
    throw "no printer called '$want' on this PC. Printers here: " + ($names -join ' | ')
}
while ($true) {
    try {
        $r = Invoke-RestMethod -Uri "$Api/jobs/pending" -Headers $H -TimeoutSec 20
        foreach ($j in $r.jobs) {
            if (-not $j.ip -and -not $j.windows_name) { continue }
            $status = 'sent'; $err = $null
            try {
                $bytes = [Convert]::FromBase64String($j.payload_b64)
                if ($j.conn -eq 'usb') {
                    [TeleRawPrint]::Send((Resolve-TelePrinter $j.windows_name), $bytes)
                } else {
                    $c = New-Object System.Net.Sockets.TcpClient
                    $wait = $c.BeginConnect($j.ip, [int]$j.port, $null, $null)
                    if (-not $wait.AsyncWaitHandle.WaitOne(4000)) { $c.Close(); throw "printer $($j.ip) did not answer" }
                    $c.EndConnect($wait); $s = $c.GetStream(); $s.Write($bytes, 0, $bytes.Length); $s.Flush(); $c.Close()
                }
                Write-Host "$(Get-Date -Format HH:mm:ss)  printed #$($j.id) on $($j.printer_name)"
            } catch {
                $status = 'failed_attempt'; $err = "$($_.Exception.Message)"
                Write-Host "$(Get-Date -Format HH:mm:ss)  #$($j.id) on $($j.printer_name) FAILED: $err" -ForegroundColor Yellow
            }
            $body = @{ status = $status; error = $err } | ConvertTo-Json
            Invoke-RestMethod -Method Patch -Uri "$Api/jobs/$($j.id)" -Headers $H -ContentType 'application/json' -Body $body -TimeoutSec 20 | Out-Null
        }
    } catch {
        Write-Host "$(Get-Date -Format HH:mm:ss)  cannot reach the POS: $($_.Exception.Message)" -ForegroundColor Red
        Start-Sleep -Seconds 5
    }
    Start-Sleep -Seconds 2
}
