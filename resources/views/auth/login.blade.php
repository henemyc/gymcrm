<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in — GymLead CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=8">
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; display: inline-block; }
        #login-alert { display: none; margin-bottom: 10px; }
        #login-alert.show { display: block; }
    </style>
</head>
<body class="auth-body">
    <section class="auth-visual">
        <div>
            <div class="brand">
                <span class="brand-mark">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V8l8-5 8 5v11"/><path d="M9 19v-6h6v6"/></svg>
                </span>
                <span class="brand-copy">
                    <strong>GymLead CRM</strong>
                    <small>Sales operations</small>
                </span>
            </div>
            <h2>Run gym acquisition like a sales desk, not a spreadsheet.</h2>
            <p>Source businesses from Google Maps, assign ownership, and move every conversation through a clean pipeline.</p>
            <ul class="auth-points">
                <li><i>✓</i> Role-based workspaces for admins and closers</li>
                <li><i>✓</i> Duplicate-safe Maps import with official APIs</li>
                <li><i>✓</i> Bulk assign, status, import and export</li>
            </ul>
        </div>
        <p class="hint" style="color:#8ea0b8">Encrypted API keys · activity log · ownership rules</p>
    </section>
    <section class="auth-card">
        <div class="inner">
            <h1>Welcome back</h1>
            <p class="muted">Sign in to continue to your workspace.</p>
            <form id="login-form" method="POST" action="{{ route('login.attempt') }}" class="stack" style="margin-top:22px">
                @csrf
                <div id="login-alert" class="alert err"></div>
                <label>
                    Work email
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@company.com">
                </label>
                <label>
                    Password
                    <input type="password" name="password" required>
                </label>
                <label class="check">
                    <input type="checkbox" name="remember" value="1"> Keep me signed in
                </label>
                <button class="btn btn-primary" type="submit" id="login-btn" style="height:42px">Sign in</button>
            </form>
        </div>
    </section>
    <script>
    (function () {
        var form = document.getElementById('login-form');
        var btn = document.getElementById('login-btn');
        var alertBox = document.getElementById('login-alert');
        var token = document.querySelector('meta[name="csrf-token"]').content;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            alertBox.classList.remove('show');
            btn.disabled = true;
            btn.innerHTML = '<span class="spin"></span> Signing in…';
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: new FormData(form)
            }).then(function (res) {
                return res.text().then(function (text) {
                    var json = {};
                    try { json = text ? JSON.parse(text) : {}; } catch (err) { json = {}; }
                    if (!res.ok) {
                        var msg = json.message;
                        if (json.errors) {
                            var k = Object.keys(json.errors)[0];
                            msg = json.errors[k][0];
                        }
                        throw new Error(msg || 'Could not sign in.');
                    }
                    window.location.href = json.redirect || '{{ route('dashboard') }}';
                });
            }).catch(function (err) {
                btn.disabled = false;
                btn.textContent = 'Sign in';
                alertBox.textContent = err.message || 'Could not sign in.';
                alertBox.classList.add('show');
            });
        });
    })();
    </script>
</body>
</html>
