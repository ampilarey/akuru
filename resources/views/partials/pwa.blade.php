@php
    // PW1 (STATUS §5uz): the build this page was served with. The script
    // below compares it with the build on the server and offers a refresh.
    $akuruBuild = is_file(public_path('build/manifest.json')) ? hash_file('sha256', public_path('build/manifest.json')) : '';
@endphp
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#7C2D37">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Akuru">
<meta name="app-build" content="{{ $akuruBuild }}">
<link rel="apple-touch-icon" href="/images/apple-touch-icon.png?v=4">
<script>
    (function () {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).then(function () {
                    return navigator.serviceWorker.ready;
                }).then(function (registration) {
                    // Clear the files the last deploy retired.
                    if (registration.active) registration.active.postMessage({ type: 'prune' });
                }).catch(function () {});
            });
        }

        // A new version is out: say so, and let the person choose when to
        // refresh — a reload would lose a half-written form.
        var served = @json($akuruBuild);
        var words = @json(['ready' => __('common.update_ready'), 'refresh' => __('common.update_refresh'), 'later' => __('common.update_later')]);
        var shown = false;
        var dismissed = false;

        function hex(buffer) {
            return Array.prototype.map.call(new Uint8Array(buffer), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        }

        function show() {
            if (shown || dismissed || !document.body) return;
            shown = true;
            var bar = document.createElement('div');
            bar.setAttribute('role', 'status');
            bar.setAttribute('data-testid', 'update-bar');
            bar.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:2147483000;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px 12px;padding:10px 16px;background:#1f2937;color:#fff;font-size:15px;line-height:1.4;box-shadow:0 2px 8px rgba(0,0,0,.25)';
            var text = document.createElement('span');
            text.textContent = words.ready;
            var refresh = document.createElement('button');
            refresh.type = 'button';
            refresh.setAttribute('data-testid', 'update-refresh');
            refresh.textContent = words.refresh;
            refresh.style.cssText = 'min-height:44px;padding:0 18px;border:0;border-radius:6px;background:#fff;color:#111827;font:inherit;font-weight:600;cursor:pointer';
            refresh.addEventListener('click', function () { window.location.reload(); });
            var later = document.createElement('button');
            later.type = 'button';
            later.setAttribute('data-testid', 'update-later');
            later.textContent = words.later;
            later.style.cssText = 'min-height:44px;padding:0 12px;border:0;background:transparent;color:#fff;font:inherit;text-decoration:underline;cursor:pointer';
            later.addEventListener('click', function () { dismissed = true; shown = false; bar.remove(); });
            bar.appendChild(text);
            bar.appendChild(refresh);
            bar.appendChild(later);
            document.body.appendChild(bar);
        }

        function check() {
            if (!served || !window.fetch || !window.crypto || !window.crypto.subtle) return Promise.resolve(false);
            return fetch('/build/manifest.json', { cache: 'no-store', credentials: 'omit' }).then(function (response) {
                return response.ok ? response.arrayBuffer() : null;
            }).then(function (body) {
                return body ? window.crypto.subtle.digest('SHA-256', body) : null;
            }).then(function (digest) {
                var changed = !!digest && hex(digest) !== served;
                if (changed) show();
                return changed;
            }).catch(function () { return false; });
        }

        window.akuruCheckForUpdate = check;
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') check(); });
        window.addEventListener('online', check);
        setInterval(function () { if (document.visibilityState === 'visible') check(); }, 15 * 60 * 1000);
    })();
</script>
