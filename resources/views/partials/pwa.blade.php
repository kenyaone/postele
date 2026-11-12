<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#2b1b14">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Tele POS">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<script>
    if ('serviceWorker' in navigator) addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
    addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); window.__installPrompt = e;
        var b = document.getElementById('install-app'); if (b) b.classList.remove('hidden');
    });
    function installApp() {
        var p = window.__installPrompt; if (!p) return;
        p.prompt(); p.userChoice.finally(function () { window.__installPrompt = null; var b = document.getElementById('install-app'); if (b) b.classList.add('hidden'); });
    }
</script>
