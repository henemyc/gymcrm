<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ \App\Models\Setting::get('company_name', 'GymLead CRM') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=17">
    <style>
        a.btn-primary,.btn-primary{color:#fff!important}
        #gl-toast-stack,.gl-toast-stack,#flash-stack,.flash-stack{position:fixed!important;top:14px!important;left:50%!important;right:auto!important;bottom:auto!important;transform:translateX(-50%)!important;z-index:2147483647!important;width:max-content!important;max-width:min(340px,calc(100vw - 24px))!important;display:flex!important;flex-direction:column!important;align-items:center!important;gap:8px!important;pointer-events:none!important}
        .gl-toast{pointer-events:auto;width:max-content;max-width:100%;display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:12px;font:650 12.5px/1.3 "Plus Jakarta Sans",system-ui,sans-serif;color:#fff;position:relative;overflow:hidden;transform-origin:50% 0;animation:glToastIn .36s cubic-bezier(.22,1.15,.36,1) both;box-shadow:0 10px 24px rgba(15,23,42,.2)}
        .gl-toast-ok,.toast-ok{background:#067647!important;color:#fff!important}
        .gl-toast-err,.toast-err{background:#b42318!important;color:#fff!important}
        .gl-toast-warn{background:#b54708!important;color:#fff!important}
        .gl-toast.is-out{animation:glToastOut .2s ease-in forwards}
        .gl-toast-ico{width:22px;height:22px;border-radius:7px;flex:0 0 22px;display:grid;place-items:center;background:rgba(255,255,255,.16);animation:glToastIco .32s ease-out both}
        .gl-toast-ico svg{width:13px;height:13px}
        .gl-toast-body{flex:1;min-width:0;font-weight:650}
        .gl-toast-ch{display:inline-block;animation:glToastChar .22s ease-out both}
        .gl-toast-x{width:22px;height:22px;border:0;border-radius:6px;background:rgba(255,255,255,.12);color:#fff;cursor:pointer;font:650 14px/1 inherit;flex:0 0 22px}
        .gl-toast-x:hover{background:rgba(255,255,255,.24)}
        .gl-toast-bar{display:none!important}
        @keyframes glToastIn{0%{opacity:0;transform:translateY(-8px) scale(.98)}72%{opacity:1;transform:translateY(2px) scale(1.015)}100%{opacity:1;transform:translateY(0) scale(1)}}
        @keyframes glToastOut{0%{opacity:1;transform:translateY(0)}100%{opacity:0;transform:translateY(-8px)}}
        @keyframes glToastIco{0%{transform:scale(.86);opacity:.6}100%{transform:scale(1);opacity:1}}
        @keyframes glToastChar{0%{opacity:0;transform:translateY(3px)}100%{opacity:1;transform:translateY(0)}}
        .btn-sm{padding:5px 10px;font-size:12px;height:30px;line-height:1}
        .act-btns{display:inline-flex;gap:6px;align-items:center;white-space:nowrap}
        .col-act{width:1%;white-space:nowrap}
        html,body{margin:0;max-width:100%}
        .app{display:grid!important;grid-template-columns:248px minmax(0,1fr)!important;min-height:100vh;width:100%}
        .sidebar{position:sticky!important;top:0!important;left:auto!important;width:248px!important;max-width:248px;height:100vh;transform:none!important;z-index:28;overflow:auto}
        .workspace{margin-left:0!important;max-width:none!important;min-width:0;overflow:visible!important;display:flex;flex-direction:column}
        .canvas{max-width:none!important;overflow:visible!important}
        .hide-sm{display:inline-flex}
        @media (max-width:860px){
            .app{display:block!important;grid-template-columns:1fr!important}
            .workspace{margin-left:0!important;width:100%}
            .sidebar{position:fixed!important;left:0!important;top:0;transform:translateX(-105%)!important}
            .sidebar.open{transform:none!important}
            .chrome{padding:8px 12px!important;gap:8px;min-height:52px;align-items:center}
            .chrome-title{min-width:0}
            .chrome-title h1{font-size:16px!important;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .chrome-title p{display:none!important}
            .chrome-actions{flex-wrap:nowrap;gap:6px;flex:0 0 auto}
            .chrome-actions .btn{padding:6px 10px;font-size:12px;height:32px}
            .hide-sm{display:none!important}
            .canvas{padding:12px 12px 40px!important}
            .act-btns,.col-act{white-space:normal}
            .col-act{width:auto}
        }
        .sk{background:linear-gradient(90deg,#e8edf3 0%,#f6f8fb 45%,#e8edf3 90%);background-size:200% 100%;animation:glshimmer 1.15s ease-in-out infinite;border-radius:8px;display:block}
        .sk-line{height:12px;width:100%}.sk-line.w80{width:80%}.sk-line.w60{width:60%}.sk-line.w40{width:40%}
        .sk-avatar{width:32px;height:32px;border-radius:50%;flex:0 0 32px}
        .sk-card{height:22px;width:48px;margin-top:8px}
        .sk-row{display:flex;align-items:center;gap:10px}
        @keyframes glshimmer{0%{background-position:100% 0}100%{background-position:-100% 0}}
    </style>
</head>
<body>
@php
    $user = auth()->user();
    $company = \App\Models\Setting::get('company_name', 'GymLead CRM');
    $svg = function ($path) {
        return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$path.'</svg>';
    };
@endphp
<div class="app" id="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('today') }}">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V8l8-5 8 5v11"/><path d="M9 19v-6h6v6"/></svg>
            </span>
            <span class="brand-copy">
                <strong>{{ $company }}</strong>
                <small>Sales CRM</small>
            </span>
        </a>

        <nav class="nav">
            <p class="nav-label">Overview</p>
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                {!! $svg('<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>') !!}
                Dashboard
            </a>
            <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                {!! $svg('<path d="M4 19V9l4 3 4-6 4 4 4-5v14z"/><path d="M4 19h16"/>') !!}
                Reports
            </a>
            <a class="nav-link {{ request()->routeIs('leads.index') || request()->routeIs('leads.show') || request()->routeIs('leads.edit') ? 'active' : '' }}" href="{{ route('leads.index') }}">
                {!! $svg('<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1.2"/><circle cx="4" cy="12" r="1.2"/><circle cx="4" cy="18" r="1.2"/>') !!}
                {{ $user->isAdmin() ? 'Leads' : 'My leads' }}
            </a>
            <a class="nav-link {{ request()->routeIs('leads.create') ? 'active' : '' }}" href="{{ route('leads.create') }}">
                {!! $svg('<circle cx="12" cy="8" r="3.2"/><path d="M5.5 19a6.5 6.5 0 0 1 13 0"/><path d="M19 8v4M21 10h-4"/>') !!}
                Add lead
            </a>

            @if($user->isAdmin())
                <p class="nav-label">Acquisition</p>
                <a class="nav-link {{ request()->routeIs('scraper.*') ? 'active' : '' }}" href="{{ route('scraper.index') }}">
                    {!! $svg('<circle cx="12" cy="12" r="9"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><circle cx="12" cy="12" r="3"/>') !!}
                    Maps scraper
                </a>
                <a class="nav-link {{ request()->routeIs('leads.import*') ? 'active' : '' }}" href="{{ route('leads.import.form') }}">
                    {!! $svg('<path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M5 21h14"/>') !!}
                    Import
                </a>

                <p class="nav-label">Admin</p>
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    {!! $svg('<circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.2"/><path d="M16 19a4.2 4.2 0 0 1 5-4"/>') !!}
                    Team
                </a>
                <a class="nav-link {{ request()->routeIs('activity.*') ? 'active' : '' }}" href="{{ route('activity.index') }}">
                    {!! $svg('<path d="M4 5h16M4 12h10M4 19h16"/><circle cx="18" cy="12" r="2"/>') !!}
                    Activity
                </a>
                <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
                    {!! $svg('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.7 1 1.1 1.5 1.1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>') !!}
                    Settings
                </a>
            @endif
        </nav>

        <div class="sidebar-foot">
            <a href="{{ route('profile.edit') }}" class="who">
                <span class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="who-copy">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ ucfirst($user->role) }}</small>
                </span>
            </a>
            <button class="logout" type="button" id="logout-open" title="Sign out">
                {!! $svg('<path d="M9 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>') !!}
                Sign out
            </button>
            <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">
                @csrf
            </form>
        </div>
    </aside>

    <div class="workspace">
        <header class="chrome">
            <button class="icon-btn menu-btn" type="button" id="menu-btn" aria-label="Open menu">
                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <div class="chrome-title">
                <h1>@yield('heading', 'Dashboard')</h1>
                <p>@yield('subhead', 'Pipeline, ownership, and follow-through.')</p>
            </div>
            <div class="chrome-actions">
                @yield('actions')
            </div>
        </header>
        <div class="canvas">
            @yield('content')
        </div>
    </div>
    <div class="backdrop" id="backdrop"></div>
</div>
<div class="modal" id="logout-modal" style="display:none" onclick="if(event.target===this) this.style.display='none';">
    <div class="modal-card" onclick="event.stopPropagation();" style="max-width:420px">
        <header class="panel-h"><h2>Sign out?</h2></header>
        <p class="muted">You will need to sign in again to access the workspace.</p>
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end">
            <button type="button" class="btn btn-ghost" id="logout-cancel">Stay signed in</button>
            <button type="button" class="btn btn-primary" id="logout-confirm">Sign out</button>
        </div>
    </div>
</div>
<div id="gl-toast-stack" class="gl-toast-stack" aria-live="polite" aria-relevant="additions"></div>
<div id="app-loader" class="app-loader" hidden>
    <div class="loader-card">
        <div class="spinner"></div>
        <p id="app-loader-text">Working…</p>
    </div>
</div>
<script>
    window.GymLead = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        isAdmin: @json($user->isAdmin()),
    };
</script>
<script src="{{ asset('assets/js/app.js') }}?v=14"></script>
<script>
(function () {
    function showToast(msg, type) {
        if (msg == null || msg === '') return;
        var stack = document.getElementById('gl-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'gl-toast-stack';
            stack.className = 'gl-toast-stack';
            document.body.appendChild(stack);
        }
        var kind = type === 'err' ? 'err' : (type === 'warn' ? 'warn' : 'ok');
        var icons = {
            ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
            err: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
            warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>'
        };
        var el = document.createElement('div');
        el.className = 'gl-toast gl-toast-' + kind;
        el.setAttribute('role', 'status');
        el.innerHTML = '<span class="gl-toast-ico">' + icons[kind] + '</span><div class="gl-toast-body"></div><button type="button" class="gl-toast-x" aria-label="Dismiss">×</button>';
        var body = el.querySelector('.gl-toast-body');
        var chars = Array.from(String(msg));
        chars.forEach(function (ch, i) {
            if (i >= 72) return;
            if (ch === ' ') { body.appendChild(document.createTextNode(' ')); return; }
            var s = document.createElement('span');
            s.className = 'gl-toast-ch';
            s.textContent = ch;
            s.style.animationDelay = (18 + i * 9) + 'ms';
            body.appendChild(s);
        });
        if (chars.length > 72) {
            var rest = document.createElement('span');
            rest.textContent = chars.slice(72).join('');
            body.appendChild(rest);
        }
        stack.appendChild(el);
        while (stack.children.length > 4) stack.removeChild(stack.firstChild);
        var life = 4200, hideAt = Date.now() + life, left = life, timer;
        function gone() {
            clearTimeout(timer);
            if (el.classList.contains('is-out')) return;
            el.classList.add('is-out');
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 280);
        }
        function arm(ms) { clearTimeout(timer); timer = setTimeout(gone, ms); }
        arm(life);
        el.addEventListener('mouseenter', function () { left = Math.max(400, hideAt - Date.now()); clearTimeout(timer); el.classList.add('paused'); });
        el.addEventListener('mouseleave', function () { hideAt = Date.now() + left; el.classList.remove('paused'); arm(left); });
        el.querySelector('.gl-toast-x').addEventListener('click', gone);
    }
    window.GymLead = window.GymLead || {};
    window.GymLead.toast = showToast;
    window.alert = function (m) { showToast(String(m || ''), 'warn'); };
    document.querySelectorAll('form').forEach(function (f) { f.setAttribute('novalidate', 'novalidate'); });
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || !form.checkValidity) return;
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            var inv = form.querySelector(':invalid');
            showToast((inv && inv.validationMessage) ? inv.validationMessage : 'Please fill the required fields.', 'warn');
            if (inv && inv.focus) inv.focus();
        }
    }, true);
})();
function closeUserModal() {
    var m = document.getElementById('user-modal');
    if (!m) return;
    m.style.display = 'none';
    m.classList.remove('is-open');
    m.setAttribute('hidden', 'hidden');
}
function openUserModal() {
    var m = document.getElementById('user-modal');
    if (!m) return;
    m.removeAttribute('hidden');
    m.classList.add('is-open');
    m.style.display = 'grid';
}
(function () {
    var open = document.getElementById('logout-open');
    var modal = document.getElementById('logout-modal');
    var cancel = document.getElementById('logout-cancel');
    var confirmBtn = document.getElementById('logout-confirm');
    var form = document.getElementById('logout-form');
    if (!open || !modal || !form) return;
    open.addEventListener('click', function () { modal.style.display = 'grid'; });
    if (cancel) cancel.addEventListener('click', function () { modal.style.display = 'none'; });
    if (confirmBtn) confirmBtn.addEventListener('click', function () {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spin" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle"></span> Signing out…';
        if (window.GymLead && GymLead.loading) GymLead.loading(true, 'Signing out…');
        form.submit();
    });
})();
</script>
@stack('scripts')
</body>
</html>
