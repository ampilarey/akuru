{{-- Prayer banner assets — island picker panel, styles, script.
     Ported from Bake&Grill's PrayerBar, recolored to the Akuru palette.
     Include ONCE per page (the banner partial itself may appear twice:
     desktop header slot + mobile header slot). --}}
<div id="hptPanel" class="hpt-panel" role="listbox" aria-label="Select island">
    <div class="hpt-search-row">
        <input type="text" id="hptSearch" class="hpt-search-input" placeholder="Search island or atoll…" autocomplete="off" spellcheck="false">
    </div>
    <div class="hpt-list" id="hptList"></div>
</div>

<style>
    .prayer-banner { background: #F9F4EE; border: 1px solid #E6D9C8; border-radius: 12px; min-height: 44px; overflow: hidden; }
    .prayer-banner-skeleton[hidden], .prayer-banner-unavailable[hidden],
    .prayer-banner-body[hidden], .prayer-banner-panel[hidden] { display: none !important; }
    .prayer-banner-skeleton { height: 44px; display: flex; align-items: center; padding: 0 .75rem; }
    .prayer-banner-skeleton-bar { display: block; height: 14px; width: 55%; border-radius: 999px;
        background: linear-gradient(90deg, #E6D9C8 25%, #fff 50%, #E6D9C8 75%); background-size: 200% 100%;
        animation: ptShimmer 1.2s linear infinite; }
    @keyframes ptShimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }
    .prayer-banner-unavailable { min-height: 44px; display: flex; align-items: center; padding: 0 .75rem; font-size: .8125rem; color: #6b7280; }
    .prayer-banner-summary { display: flex; align-items: stretch; gap: .25rem; min-height: 44px; }
    .prayer-banner-expand { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: space-between;
        gap: .35rem; padding: .4rem .25rem .4rem .75rem; border: none; background: transparent; cursor: pointer;
        font-family: inherit; text-align: left; color: #374151; min-height: 44px; }
    .prayer-banner-summary-left { display: flex; align-items: center; gap: .25rem; min-width: 0; }
    .prayer-banner-island { flex-shrink: 0; align-self: center; margin-right: .4rem; padding: .25rem .55rem;
        border: 1px solid #E6D9C8; border-radius: 999px; background: #fff; font-family: inherit; font-size: .75rem;
        font-weight: 700; color: #7C2D37; cursor: pointer; white-space: nowrap; }
    .prayer-banner-next { font-size: .8125rem; color: #374151; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .prayer-banner-next strong { font-weight: 800; color: #7C2D37; }
    .prayer-banner-time { font-variant-numeric: tabular-nums; }
    .prayer-banner-cd { color: #6b7280; font-variant-numeric: tabular-nums; }
    .prayer-banner-chevron { flex-shrink: 0; color: #6b7280; font-size: .75rem; padding-right: .2rem; }
    .prayer-banner-panel { border-top: 1px solid #E6D9C8; padding: .85rem 1rem 1rem; }
    .prayer-banner-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    @media (min-width: 390px) { .prayer-banner-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 640px) { .prayer-banner-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    .prayer-banner-cell { display: flex; flex-direction: column; gap: .15rem; padding: .55rem .6rem; border-radius: 12px;
        background: #fff; border: 1px solid transparent; }
    .prayer-banner-cell.is-next { background: #FDF6E3; border-color: rgba(201, 162, 39, .45); }
    .prayer-banner-cell-name { font-size: .7rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
    .prayer-banner-cell-time { font-size: .95rem; font-weight: 800; color: #7C2D37; font-variant-numeric: tabular-nums; }
    .prayer-banner-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
    .prayer-banner-action { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .7rem;
        border: 1px solid #E6D9C8; border-radius: 999px; background: #fff; font-family: inherit; font-size: .75rem;
        font-weight: 600; color: #7C2D37; cursor: pointer; }
    .prayer-banner-action.pt-spin svg { animation: ptSpin 1s linear infinite; }
    @keyframes ptSpin { to { transform: rotate(360deg); } }

    /* Desktop header slot: compact strip inside the nav row */
    .header-prayer { min-width: 0; width: min(360px, 32vw); }
    .header-prayer .prayer-banner { border-radius: 999px; }
    .header-prayer .prayer-banner.is-expanded { border-radius: 12px; }
    .header-prayer .prayer-banner-summary, .header-prayer .prayer-banner-expand,
    .header-prayer .prayer-banner-skeleton, .header-prayer .prayer-banner-unavailable { min-height: 38px; }
    .header-prayer .prayer-banner-next { font-size: .78rem; }
    .header-prayer .prayer-banner-island { font-size: .7rem; }
    /* The desktop slot is ~360px wide, so the viewport-width 6-column rule
       truncates times there; keep the grid at 3 columns in that slot. */
    .header-prayer:not(.header-prayer--mobile) .prayer-banner-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }

    /* Mobile/tablet header slot: pill between the logo and the translate button */
    .header-prayer--mobile { flex: 1 1 auto; width: auto; max-width: 420px; margin: 0 .5rem; }
    /* Pill height matches the translate box (30px outer): 28px content + 1px borders */
    .header-prayer--mobile .prayer-banner { min-height: 28px; }
    .header-prayer--mobile .prayer-banner-summary, .header-prayer--mobile .prayer-banner-expand,
    .header-prayer--mobile .prayer-banner-unavailable { min-height: 28px; }
    .header-prayer--mobile .prayer-banner-skeleton { height: 28px; }
    /* Exempt the pill's buttons from the layout's 44px tap-target rule */
    @media (max-width: 768px) {
        .header-prayer--mobile .prayer-banner-expand,
        .header-prayer--mobile .prayer-banner-island { min-height: 28px; min-width: 0; }
    }
    .header-prayer--mobile .prayer-banner-expand { padding-top: .1rem; padding-bottom: .1rem; padding-left: .6rem; }
    .header-prayer--mobile .prayer-banner-next { font-size: .72rem; }
    .header-prayer--mobile .prayer-banner-island { font-size: .65rem; padding: .1rem .4rem; margin-right: .3rem; }
    /* Short island label (≤6 chars) so the pill stays one line on phones */
    .pt-loc-short { display: none; }
    @media (max-width: 520px) {
        .header-prayer--mobile .pt-loc-full { display: none; }
        .header-prayer--mobile .pt-loc-short { display: inline; }
    }
    @media (max-width: 400px) {
        .header-prayer--mobile .prayer-banner-cd { display: none; }
    }
    /* Expanded details drop below the header instead of stretching it:
       fixed full-width sheet under the nav, keeping the page's 1rem padding.
       JS sets its top to the nav's bottom edge each tick. */
    .header-prayer--mobile .prayer-banner.is-expanded { border-radius: 999px; }
    .header-prayer--mobile .prayer-banner-panel {
        position: fixed; left: 0; right: 0; z-index: 80; margin: 0 1rem;
        background: #F9F4EE; border: 1px solid #E6D9C8; border-radius: 0 0 12px 12px;
        box-shadow: 0 14px 30px rgba(61, 18, 25, .18);
    }

    /* Hijri date inside the expanded panel */
    .prayer-banner-hijri { font-size: .75rem; font-weight: 700; color: #7C2D37; margin-bottom: .6rem; }
    .prayer-banner-hijri[hidden] { display: none !important; }

    /* Island picker */
    .hpt-panel { position: fixed; z-index: 90; width: 290px; max-height: 60vh; display: none; flex-direction: column;
        background: #fff; border: 1px solid #E6D9C8; border-radius: 12px; box-shadow: 0 12px 32px rgba(61, 18, 25, .18); overflow: hidden; }
    .hpt-panel.open { display: flex; }
    .hpt-search-row { padding: .5rem; border-bottom: 1px solid #E6D9C8; }
    .hpt-search-input { width: 100%; padding: .45rem .65rem; border: 1px solid #E6D9C8; border-radius: 8px; font-size: .8125rem; }
    .hpt-search-input:focus { outline: 2px solid #C9A227; outline-offset: 1px; }
    .hpt-list { overflow-y: auto; padding: .35rem; }
    .hpt-group-label { padding: .45rem .5rem .2rem; font-size: .68rem; font-weight: 700; letter-spacing: .05em;
        text-transform: uppercase; color: #6b7280; }
    .hpt-option { padding: .45rem .6rem; border-radius: 8px; font-size: .8125rem; color: #374151; cursor: pointer; }
    .hpt-option:hover { background: #F9F4EE; }
    .hpt-option.selected { background: #FDF6E3; color: #7C2D37; font-weight: 700; }
    .hpt-no-results { padding: .75rem; font-size: .8125rem; color: #6b7280; }
</style>

@php
    $ptLabels = [
        'fajr' => __('public.Fajr'),
        'sunrise' => __('public.Sunrise'),
        'dhuhr' => __('public.Dhuhr'),
        'asr' => __('public.Asr'),
        'maghrib' => __('public.Maghrib'),
        'isha' => __('public.Isha'),
    ];
@endphp
<script>
(function () {
    'use strict';

    var PRAYERS = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
    var PRAYER_LABEL = @json($ptLabels);
    var NEXT_IN = @json(__('public.next in'));
    var ATOLL_ABBR = {
        'Haa Alif':'HA','Haa Dhaalu':'HDh','Shaviyani':'Sh','Noonu':'N','Raa':'R',
        'Baa':'B','Lhaviyani':'Lh','Kaafu':'K','Alif Alif':'AA','Alif Dhaalu':'ADh',
        'Vaavu':'V','Meemu':'M','Faafu':'F','Dhaalu':'Dh','Thaa':'Th','Laamu':'L',
        'Gaafu Alif':'GA','Gaafu Dhaalu':'GDh','Gnaviyani':'Gn','Seenu':'S','Malé':'K',
    };
    var API = '/api/v1/prayer-times';
    var MALE_FALLBACK = { id: 102, atollLatin: 'Kaafu', nameLatin: 'Malé' };
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    var timeSkew = {{ now()->timestamp * 1000 }} - Date.now();
    function getMVT() { return new Date(Date.now() + timeSkew + 5 * 3600 * 1000); }
    function parseHHMM(s) { var p = s.split(':'); return +p[0] * 60 + +p[1]; }
    function applyServerDate(response) {
        try {
            var d = response.headers.get('Date');
            if (d) { var s = new Date(d).getTime(); if (!isNaN(s)) timeSkew = s - Date.now(); }
        } catch (e) {}
    }
    function mvtDateStr(offsetDays) {
        var d = getMVT();
        if (offsetDays) d.setUTCDate(d.getUTCDate() + offsetDays);
        return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0') + '-' + String(d.getUTCDate()).padStart(2, '0');
    }
    function fmtCountdown(ms) {
        var t = Math.max(0, Math.floor(ms / 1000)), h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60;
        if (h > 0) return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }
    function makeLabel(atollLatin, nameLatin) {
        var abbr = ATOLL_ABBR[atollLatin] || (atollLatin ? atollLatin.split(' ')[0] : '');
        return (abbr ? abbr + '. ' : '') + (nameLatin || '');
    }
    function makeShortLabel(nameLatin) {
        var n = nameLatin || 'Malé';
        return n.length > 6 ? n.slice(0, 5) + '…' : n;
    }
    function toIslandInfo(raw) {
        if (!raw || typeof raw.id !== 'number' || !isFinite(raw.id)) return null;
        var nameLatin = raw.nameLatin || raw.name_en || raw.name_latin || '';
        var atollLatin = raw.atollLatin || raw.atoll_latin || '';
        if (!nameLatin && raw.id === MALE_FALLBACK.id) return MALE_FALLBACK;
        return { id: raw.id, atollLatin: atollLatin, nameLatin: nameLatin };
    }
    function findMaleIsland(islands) {
        var male = islands.find(function (i) {
            var latin = (i.name_en || i.name_latin || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
            return latin === 'male';
        });
        return male ? (toIslandInfo(male) || MALE_FALLBACK) : MALE_FALLBACK;
    }

    function $$(id) { return document.getElementById(id); }
    function banners() { return Array.prototype.slice.call(document.querySelectorAll('[data-pt-banner]')); }
    function eachBanner(fn) { banners().forEach(fn); }

    var prayers = null;
    var tomorrowPrayers = null;
    var hijri = null;
    var currentIsland = null;
    var allIslands = [];
    var tickTimer = null;
    var dropOpen = false;
    var activeTrigger = null;
    var expanded = false;
    try { expanded = sessionStorage.getItem('akuru_pt_expanded') === '1'; } catch (e) {}

    function computeTick() {
        if (!prayers) return null;
        var mv = getMVT(), nowMin = mv.getUTCHours() * 60 + mv.getUTCMinutes();
        var pName = '', pTime = '', cdStr = '';
        for (var i = 0; i < PRAYERS.length; i++) {
            var key = PRAYERS[i];
            if (!prayers[key]) continue;
            var pMin = parseHHMM(prayers[key]);
            if (pMin > nowMin) {
                var ms = (pMin - nowMin) * 60000 - mv.getUTCSeconds() * 1000;
                pName = PRAYER_LABEL[key]; pTime = prayers[key]; cdStr = fmtCountdown(ms);
                break;
            }
        }
        if (!pName) {
            var tFajr = (tomorrowPrayers && tomorrowPrayers.fajr) ? tomorrowPrayers.fajr : prayers.fajr;
            var fajrMin = parseHHMM(tFajr);
            var msToMidnight = (24 * 60 - nowMin) * 60000 - mv.getUTCSeconds() * 1000;
            pName = PRAYER_LABEL.fajr; pTime = tFajr; cdStr = fmtCountdown(msToMidnight + fajrMin * 60000);
        }
        return { pName: pName, pTime: pTime, cdStr: cdStr };
    }

    function paintGrid(root, nextName) {
        var grid = root.querySelector('[data-pt-grid]');
        if (!grid || !prayers) return;
        grid.innerHTML = '';
        PRAYERS.forEach(function (key) {
            var cell = document.createElement('div');
            cell.className = 'prayer-banner-cell' + (PRAYER_LABEL[key] === nextName ? ' is-next' : '');
            cell.setAttribute('role', 'listitem');
            var name = document.createElement('span');
            name.className = 'prayer-banner-cell-name';
            name.textContent = PRAYER_LABEL[key];
            var time = document.createElement('span');
            time.className = 'prayer-banner-cell-time';
            time.textContent = prayers[key] || '—';
            cell.appendChild(name); cell.appendChild(time);
            grid.appendChild(cell);
        });
    }

    function setHidden(el, hide) {
        if (!el) return;
        if (hide) el.setAttribute('hidden', ''); else el.removeAttribute('hidden');
        el.hidden = hide;
    }

    function setExpandedUI(root, isOpen) {
        root.classList.toggle('is-expanded', isOpen);
        var btn = root.querySelector('[data-pt-expand]');
        var panel = root.querySelector('[data-pt-panel]');
        var chev = root.querySelector('[data-pt-chevron]');
        if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        setHidden(panel, !isOpen);
        if (chev) chev.textContent = isOpen ? '⌃' : '▾';
        if (isOpen) positionMobilePanel(root);
    }

    // The mobile header slot's panel is a fixed sheet below the nav so the
    // header row never grows; pin its top to the nav's bottom edge.
    function positionMobilePanel(root) {
        var panel = root.querySelector('[data-pt-panel]');
        if (!panel || !root.closest) return;
        if (!root.closest('.header-prayer--mobile')) { panel.style.top = ''; return; }
        var nav = root.closest('nav');
        panel.style.top = (nav ? nav.getBoundingClientRect().bottom : 56) + 'px';
    }

    function tick() {
        var info = computeTick();
        if (!info) return;
        var label = currentIsland ? makeLabel(currentIsland.atollLatin, currentIsland.nameLatin) : 'K. Malé';
        var shortLabel = makeShortLabel(currentIsland ? currentIsland.nameLatin : 'Malé');
        eachBanner(function (root) {
            var nameEl = root.querySelector('[data-pt-name]');
            var timeEl = root.querySelector('[data-pt-time]');
            var cdEl = root.querySelector('[data-pt-cd]');
            var locEl = root.querySelector('[data-pt-loc]');
            var locShortEl = root.querySelector('[data-pt-loc-short]');
            var hijriEl = root.querySelector('[data-pt-hijri]');
            if (nameEl) nameEl.textContent = info.pName;
            if (timeEl) timeEl.textContent = ' ' + info.pTime;
            if (cdEl) cdEl.textContent = ' · ' + NEXT_IN + ' ' + info.cdStr;
            if (locEl) locEl.textContent = label;
            if (locShortEl) locShortEl.textContent = shortLabel;
            if (hijriEl) {
                var g = getMVT();
                var gText = g.getUTCDate() + ' ' + MONTHS[g.getUTCMonth()] + ' ' + g.getUTCFullYear();
                var hText = hijri ? (hijri.formatted || (hijri.day + ' ' + hijri.month_name + ' ' + hijri.year + ' AH')) : '';
                hijriEl.textContent = hText ? gText + ' · ' + hText : gText;
                setHidden(hijriEl, false);
            }
            if (expanded) { paintGrid(root, info.pName); positionMobilePanel(root); }
        });
    }

    function showBanner() {
        eachBanner(function (root) {
            root.classList.remove('is-loading');
            setHidden(root.querySelector('[data-pt-skeleton]'), true);
            var un = root.querySelector('[data-pt-unavailable]');
            var body = root.querySelector('[data-pt-body]');
            if (!prayers) { setHidden(un, false); setHidden(body, true); return; }
            setHidden(un, true);
            setHidden(body, false);
            setExpandedUI(root, expanded);
        });
        tick();
        if (!tickTimer) tickTimer = setInterval(tick, 1000);
    }

    function fetchDay(islandId, date) {
        return fetch(API + '?island_id=' + islandId + '&date=' + date)
            .then(function (r) { applyServerDate(r); return r.json(); })
            .then(function (d) {
                return (d && d.available && d.times && d.times.fajr)
                    ? { times: d.times, hijri: d.hijri || null }
                    : null;
            });
    }

    function prefetchTomorrow(islandId) {
        var tom = mvtDateStr(1), tKey = 'akuru_pt_day2_' + tom + '_' + islandId;
        try { var c = localStorage.getItem(tKey); if (c) { var tp = JSON.parse(c); if (tp.times && tp.times.sunrise) { tomorrowPrayers = tp.times; return; } localStorage.removeItem(tKey); } } catch (e) {}
        fetchDay(islandId, tom).then(function (t) {
            if (t) { tomorrowPrayers = t.times; try { localStorage.setItem(tKey, JSON.stringify(t)); } catch (e) {} }
        }).catch(function () {});
    }

    function loadPrayers(islandId, cb) {
        var today = mvtDateStr(), cKey = 'akuru_pt_day2_' + today + '_' + islandId;
        try { var c = localStorage.getItem(cKey); if (c) { var p = JSON.parse(c); if (p.times && p.times.sunrise) { prayers = p.times; hijri = p.hijri || null; cb(); prefetchTomorrow(islandId); return; } localStorage.removeItem(cKey); } } catch (e) {}
        fetchDay(islandId, today).then(function (t) {
            if (t) { prayers = t.times; hijri = t.hijri; try { localStorage.setItem(cKey, JSON.stringify(t)); } catch (e) {} }
            cb(); prefetchTomorrow(islandId);
        }).catch(function () { cb(); });
    }

    function selectIsland(isl) {
        currentIsland = toIslandInfo(isl) || MALE_FALLBACK;
        try { localStorage.setItem('akuru_pt_island', JSON.stringify(currentIsland)); } catch (e) {}
        prayers = null; tomorrowPrayers = null; hijri = null;
        loadPrayers(currentIsland.id, showBanner);
    }

    function buildList(q) {
        var list = $$('hptList'); if (!list) return;
        list.innerHTML = ''; q = (q || '').toLowerCase().trim();
        var groups = {}, order = [];
        allIslands.forEach(function (isl) {
            var a = isl.atoll_latin || '–';
            if (!groups[a]) { groups[a] = []; order.push(a); }
            groups[a].push(isl);
        });
        var any = false;
        order.forEach(function (atoll) {
            var vis = q ? groups[atoll].filter(function (i) {
                return (i.name_en || '').toLowerCase().includes(q)
                    || (i.name_dv || '').includes(q)
                    || atoll.toLowerCase().includes(q);
            }) : groups[atoll];
            if (!vis.length) return;
            any = true;
            var lbl = document.createElement('div');
            lbl.className = 'hpt-group-label';
            lbl.textContent = (ATOLL_ABBR[atoll] || atoll) + '  —  ' + atoll;
            list.appendChild(lbl);
            vis.forEach(function (isl) {
                var opt = document.createElement('div');
                opt.className = 'hpt-option' + (currentIsland && isl.id === currentIsland.id ? ' selected' : '');
                opt.textContent = isl.name_en || isl.name_dv || 'Island';
                opt.addEventListener('click', function (e) { e.stopPropagation(); closeDropdown(); selectIsland(isl); });
                list.appendChild(opt);
            });
        });
        if (!any) { var nr = document.createElement('div'); nr.className = 'hpt-no-results'; nr.textContent = 'No islands found'; list.appendChild(nr); }
    }

    function openDropdown(trigger) {
        var panel = $$('hptPanel'); if (!panel) return;
        var r = trigger.getBoundingClientRect();
        var pw = 290;
        var left = r.left;
        if (left + pw > window.innerWidth - 8) left = window.innerWidth - pw - 8;
        panel.style.top = (r.bottom + 6) + 'px';
        panel.style.left = left + 'px';
        panel.classList.add('open');
        dropOpen = true; activeTrigger = trigger;
        var s = $$('hptSearch'); if (s) { s.value = ''; s.focus(); }
        buildList('');
    }

    function closeDropdown() {
        var panel = $$('hptPanel'); if (panel) panel.classList.remove('open');
        dropOpen = false; activeTrigger = null;
    }

    function loadIslands() {
        return fetch(API + '/islands')
            .then(function (r) { applyServerDate(r); return r.json(); })
            .then(function (d) {
                allIslands = d.islands || [];
                try { localStorage.setItem('akuru_pt_islands', JSON.stringify(allIslands)); } catch (e) {}
                return allIslands;
            });
    }

    function openIslands(trigger) {
        if (dropOpen && activeTrigger === trigger) { closeDropdown(); return; }
        closeDropdown();
        if (allIslands.length) { openDropdown(trigger); return; }
        try {
            var c = localStorage.getItem('akuru_pt_islands');
            if (c) { allIslands = JSON.parse(c); openDropdown(trigger); return; }
        } catch (e) {}
        loadIslands().then(function () { openDropdown(trigger); }).catch(function () {});
    }

    function handleGeo(btn) {
        if (!navigator.geolocation) return;
        btn.classList.add('pt-spin'); btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                fetch(API + '?lat=' + pos.coords.latitude + '&lng=' + pos.coords.longitude)
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        btn.classList.remove('pt-spin'); btn.disabled = false;
                        if (d.island && d.island.id) {
                            try { localStorage.removeItem('akuru_pt_island'); } catch (e) {}
                            selectIsland(d.island);
                        }
                    })
                    .catch(function () { btn.classList.remove('pt-spin'); btn.disabled = false; });
            },
            function () { btn.classList.remove('pt-spin'); btn.disabled = false; },
            { timeout: 8000 }
        );
    }

    function toggleExpanded() {
        expanded = !expanded;
        try { sessionStorage.setItem('akuru_pt_expanded', expanded ? '1' : '0'); } catch (e) {}
        eachBanner(function (root) { setExpandedUI(root, expanded); });
        tick();
    }

    function wireEvents() {
        eachBanner(function (root) {
            var expand = root.querySelector('[data-pt-expand]');
            var island = root.querySelector('[data-pt-island]');
            var change = root.querySelector('[data-pt-change-island]');
            var geo = root.querySelector('[data-pt-geo]');
            if (expand) expand.addEventListener('click', function (e) { e.stopPropagation(); toggleExpanded(); });
            if (island) island.addEventListener('click', function (e) { e.stopPropagation(); openIslands(island); });
            if (change) change.addEventListener('click', function (e) { e.stopPropagation(); openIslands(change); });
            if (geo) geo.addEventListener('click', function (e) { e.stopPropagation(); handleGeo(geo); });
        });
        var s = $$('hptSearch');
        if (s) {
            s.addEventListener('input', function () { buildList(s.value); });
            s.addEventListener('click', function (e) { e.stopPropagation(); });
        }
        var panel = $$('hptPanel');
        if (panel) panel.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', function () { if (dropOpen) closeDropdown(); });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (dropOpen) { closeDropdown(); return; }
            if (expanded) toggleExpanded();
        });
    }

    function init() {
        if (!banners().length) return;
        wireEvents();

        var isl = null;
        try { var s = localStorage.getItem('akuru_pt_island'); if (s) isl = toIslandInfo(JSON.parse(s)); } catch (e) {}
        if (isl) {
            currentIsland = isl;
            loadPrayers(isl.id, showBanner);
            return;
        }

        var didLoad = false;
        function useIsland(found) {
            if (didLoad) return; didLoad = true;
            currentIsland = found;
            try { localStorage.setItem('akuru_pt_island', JSON.stringify(found)); } catch (e) {}
            loadPrayers(found.id, showBanner);
        }
        var fallbackTimer = setTimeout(function () { useIsland(MALE_FALLBACK); }, 3000);
        loadIslands()
            .then(function (list) { clearTimeout(fallbackTimer); useIsland(findMaleIsland(list)); })
            .catch(function () { clearTimeout(fallbackTimer); useIsland(MALE_FALLBACK); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
