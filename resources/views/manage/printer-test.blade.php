<!doctype html><html><head><meta charset="utf-8"><title>Printer test</title>
<style>body{font:14px monospace;width:72mm;margin:0 auto;padding:8px;text-align:center}h1{font-size:22px;margin:4px 0}hr{border:0;border-top:1px dashed #000}</style></head>
<body onload="setTimeout(function(){window.print()},300)"><h1>TEST PRINT</h1><div>{{ config('pos.cafe_name', 'Tele Cafe') }}</div><hr><div>{{ now()->format('d M Y H:i:s') }}</div><hr><div>If you can read this,<br>the printer is working.</div></body></html>
