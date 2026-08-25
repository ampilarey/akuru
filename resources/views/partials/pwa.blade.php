<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#7C2D37">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Akuru">
<link rel="apple-touch-icon" href="/images/pwa-192.png">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
    }
</script>
