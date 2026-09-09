(function (w) {
    var G = w.GymLead || {};

    G.ready = function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    };

    G.esc = function (s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    G.toast = function (msg, type) {
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
        el.className = 'gl-toast gl-toast-' + kind + ' toast toast-' + (kind === 'warn' ? 'ok' : kind);
        el.setAttribute('role', 'status');
        el.innerHTML = '<span class="gl-toast-ico">' + icons[kind] + '</span><div class="gl-toast-body"></div><button type="button" class="gl-toast-x" aria-label="Dismiss">×</button>';
        var body = el.querySelector('.gl-toast-body');
        var chars = Array.from(String(msg));
        var maxBounce = 72;
        chars.forEach(function (ch, i) {
            if (i >= maxBounce) return;
            if (ch === ' ') { body.appendChild(document.createTextNode(' ')); return; }
            var s = document.createElement('span');
            s.className = 'gl-toast-ch';
            s.textContent = ch;
            s.style.animationDelay = (18 + i * 9) + 'ms';
            body.appendChild(s);
        });
        if (chars.length > maxBounce) {
            var rest = document.createElement('span');
            rest.textContent = chars.slice(maxBounce).join('');
            body.appendChild(rest);
        }
        stack.appendChild(el);
        while (stack.children.length > 4) stack.removeChild(stack.firstChild);
        var life = 4200;
        var hideAt = Date.now() + life;
        var left = life;
        var timer;
        function gone() {
            clearTimeout(timer);
            if (el.classList.contains('is-out')) return;
            el.classList.add('is-out');
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 280);
        }
        function arm(ms) {
            clearTimeout(timer);
            timer = setTimeout(gone, ms);
        }
        arm(life);
        el.addEventListener('mouseenter', function () {
            left = Math.max(400, hideAt - Date.now());
            clearTimeout(timer);
            el.classList.add('paused');
        });
        el.addEventListener('mouseleave', function () {
            hideAt = Date.now() + left;
            el.classList.remove('paused');
            arm(left);
        });
        el.querySelector('.gl-toast-x').addEventListener('click', gone);
    };

    G.formData = function (form) {
        var o = {};
        new FormData(form).forEach(function (v, k) { o[k] = v; });
        return o;
    };

    function headers() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': G.csrf,
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    function parse(res) {
        return res.text().then(function (text) {
            var json = {};
            try { json = text ? JSON.parse(text) : {}; } catch (e) { json = { message: text }; }
            json._status = res.status;
            if (!res.ok) {
                var msg = json.message;
                if (json.errors) {
                    var first = Object.keys(json.errors)[0];
                    msg = json.errors[first][0];
                }
                G.toast(msg || 'Request failed', 'err');
                json.ok = false;
            }
            return json;
        });
    }

    G.get = function (url) {
        return fetch(url, { headers: headers(), credentials: 'same-origin' }).then(parse);
    };

    G.send = function (method, url, body) {
        return fetch(url, {
            method: method,
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(parse);
    };

    G.post = function (url, body) { return G.send('POST', url, body); };

    G.postForm = function (url, formData) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': G.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: formData
        }).then(parse);
    };

    G.downloadPost = function (url, body, filename) {
        return fetch(url, {
            method: 'POST',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(function (res) {
            if (!res.ok) return parse(res);
            return res.blob().then(function (blob) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename || 'export.csv';
                a.click();
            });
        });
    };

    G.pager = function (id, meta, onPage) {
        var el = document.getElementById(id);
        if (!el || !meta) return;
        var page = parseInt(meta.current_page, 10) || 1;
        var last = parseInt(meta.last_page, 10) || 1;
        var total = parseInt(meta.total, 10) || 0;
        var per = parseInt(meta.per_page, 10) || 15;
        var from = total === 0 ? 0 : ((page - 1) * per) + 1;
        var to = Math.min(page * per, total);
        var pages = [];
        for (var i = 1; i <= last; i++) {
            if (i === 1 || i === last || (i >= page - 2 && i <= page + 2)) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '…') {
                pages.push('…');
            }
        }
        var html = '<div class="pager-info">Showing ' + from + '–' + to + ' of ' + total + '</div><div class="pager-btns">';
        html += '<button type="button" data-p="' + Math.max(1, page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>Prev</button>';
        pages.forEach(function (p) {
            if (p === '…') {
                html += '<span class="pager-ellipsis">…</span>';
            } else {
                html += '<button type="button" class="' + (p === page ? 'active' : '') + '" data-p="' + p + '">' + p + '</button>';
            }
        });
        html += '<button type="button" data-p="' + Math.min(last, page + 1) + '"' + (page >= last ? ' disabled' : '') + '>Next</button>';
        html += '</div>';
        el.innerHTML = html;
        el.querySelectorAll('button[data-p]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.disabled) return;
                onPage(parseInt(b.getAttribute('data-p'), 10));
            });
        });
    };

    G.loading = function (on, text) {
        var el = document.getElementById('app-loader');
        if (!el) return;
        var label = document.getElementById('app-loader-text');
        if (label && text) label.textContent = text;
        if (on) {
            el.removeAttribute('hidden');
            el.classList.add('show');
        } else {
            el.setAttribute('hidden', 'hidden');
            el.classList.remove('show');
        }
    };

    G.busy = function (btn, on, label) {
        if (!btn) return;
        if (on) {
            btn.dataset.prev = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner sm"></span> ' + (label || 'Please wait…');
        } else {
            btn.disabled = false;
            if (btn.dataset.prev) btn.innerHTML = btn.dataset.prev;
        }
    };

    G.openModal = function (el) {
        if (!el) return;
        el.hidden = false;
        el.classList.add('is-open');
    };

    G.closeModal = function (el) {
        if (!el) return;
        el.classList.remove('is-open');
        el.hidden = true;
    };

    G.ready(function () {
        var btn = document.getElementById('menu-btn');
        var side = document.getElementById('sidebar');
        var back = document.getElementById('backdrop');
        if (!btn || !side) return;
        function close() {
            side.classList.remove('open');
            if (back) back.classList.remove('show');
        }
        btn.addEventListener('click', function () {
            side.classList.toggle('open');
            if (back) back.classList.toggle('show');
        });
        if (back) back.addEventListener('click', close);
    });

    w.GymLead = G;
})(window);
