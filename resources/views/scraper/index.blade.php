@extends('layouts.app')
@section('title', 'Maps scraper')
@section('heading', 'Maps scraper')
@section('subhead', 'Search a location, pick businesses, assign them to staff.')
@section('actions')
    <a class="btn btn-ghost" href="{{ route('settings.index') }}">API settings</a>
@endsection
@section('content')
<style>
.scrape-wrap { width: 100%; max-width: none; }
.scrape-steps { display: flex; gap: 8px; margin: 0 0 18px; flex-wrap: wrap; }
.scrape-steps .st {
    display: flex; align-items: center; gap: 8px;
    background: #fff; border: 1px solid #e6ebf2; border-radius: 999px;
    padding: 7px 12px 7px 8px; color: #667085; font-size: 13px; font-weight: 600;
}
.scrape-steps .st i {
    width: 22px; height: 22px; border-radius: 50%; background: #eef2f6;
    display: grid; place-items: center; font-style: normal; font-size: 12px; font-weight: 700;
}
.scrape-steps .st.on { background: #eef3ff; border-color: #bfdbfe; color: #1d4ed8; }
.scrape-steps .st.on i { background: #1d4ed8; color: #fff; }
.scrape-steps .st.done { background: #ecfdf3; border-color: #abefc6; color: #067647; }
.scrape-steps .st.done i { background: #067647; color: #fff; }
.scrape-alert { display: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-weight: 600; font-size: 13px; }
.scrape-alert.show { display: block; }
.scrape-alert.ok { background: #ecfdf3; color: #067647; }
.scrape-alert.err { background: #fef3f2; color: #b42318; }
.scrape-alert.info { background: #eff8ff; color: #175cd3; }
.chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
    border: 1px solid #d5dce6; background: #fff; border-radius: 999px;
    padding: 6px 12px; cursor: pointer; font-size: 12px; font-weight: 600; color: #0f172a;
}
.chip.on { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
.scrape-search { display: grid; grid-template-columns: 1.4fr .8fr auto; gap: 10px; align-items: end; }
.scrape-search .grow { grid-column: 1 / -1; position: relative; }
.loc-suggest {
    position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 12;
    background: #fff; border: 1px solid #e6ebf2; border-radius: 10px;
    box-shadow: 0 16px 40px rgba(15,23,42,.12); max-height: 240px; overflow: auto; display: none;
}
.loc-suggest.show { display: block; }
.loc-suggest button {
    display: block; width: 100%; text-align: left; border: 0; background: #fff;
    padding: 10px 12px; cursor: pointer; font-size: 13px;
}
.loc-suggest button:hover { background: #eef3ff; }
.adv { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 12px; }
.kpis { display: flex; gap: 18px; flex-wrap: wrap; margin: 8px 0 12px; }
.kpis div span { display: block; font-size: 11px; color: #667085; font-weight: 600; }
.kpis div strong { font-size: 18px; }
.seg { display: inline-flex; background: #f2f4f7; border-radius: 10px; padding: 3px; gap: 2px; }
.seg button { border: 0; background: transparent; border-radius: 8px; padding: 6px 10px; cursor: pointer; font-weight: 600; font-size: 12px; }
.seg button.on { background: #fff; box-shadow: 0 1px 2px rgba(16,24,40,.06); }
.dock {
    position: sticky; bottom: 12px; z-index: 16;
    display: none; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    background: #0b1220; color: #fff; border-radius: 14px; padding: 12px 16px;
}
.dock.show { display: flex; }
.dock .sub { color: #9fb0c7; font-size: 12px; margin-top: 2px; }
.local-loader {
    display: none; position: fixed; inset: 0; z-index: 95;
    background: rgba(15,23,42,.45); place-items: center;
}
.local-loader.show { display: grid; }
.local-loader .box {
    background: #fff; border-radius: 14px; padding: 22px 28px; min-width: 240px; text-align: center; font-weight: 700;
}
.spin {
    width: 26px; height: 26px; border: 3px solid #dbe4f0; border-top-color: #1d4ed8;
    border-radius: 50%; margin: 0 auto 10px; animation: scspin .7s linear infinite;
}
@keyframes scspin { to { transform: rotate(360deg); } }
.scrape-toast {
    position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
    z-index: 130;
    background: #067647; color: #fff; border: 0;
    border-radius: 10px; padding: 12px 18px; font-weight: 700; font-size: 14px;
    box-shadow: 0 12px 30px rgba(15,23,42,.18); max-width: 520px; text-align: center;
}
.scrape-toast.err { background: #b42318; color: #fff; }
@media (max-width: 800px) {
    .scrape-search, .adv { grid-template-columns: 1fr; }
}
</style>

<div class="scrape-wrap">
    @if(!$hasKey)
        <div class="scrape-alert err show">Add a Google Maps API key in <a href="{{ route('settings.index') }}">Settings</a> before searching.</div>
    @endif

    <div class="scrape-steps">
        <div class="st on" data-step="1"><i>1</i> Search location</div>
        <div class="st" data-step="2"><i>2</i> Review and select</div>
        <div class="st" data-step="3"><i>3</i> Assign to staff</div>
    </div>

    <div id="scrape-alert" class="scrape-alert"></div>

    <section class="panel">
        <header class="panel-h"><h2>Find businesses on Google Maps</h2></header>
        <form id="scrape-form">
            @csrf
            <div class="scrape-search">
                <label class="grow">Location
                    <input type="text" name="location" id="location" placeholder="Start typing a city or area, then pick a suggestion" autocomplete="off" required>
                    <input type="hidden" name="place_id" id="place_id">
                    <div id="suggest" class="loc-suggest"></div>
                </label>
                <label>Keyword
                    <input name="keyword" id="keyword" value="{{ $defaults['keyword'] }}" required>
                </label>
                <button class="btn btn-primary" type="submit" id="search-btn" {{ $hasKey ? '' : 'disabled' }}>Search Maps</button>
            </div>
            <div class="adv">
                <label>Place type
                    <select name="type" id="place-type">
                        @foreach($types as $k => $label)
                            <option value="{{ $k }}" @selected($k === 'gym')>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Mode
                    <select name="mode" id="place-mode">
                        <option value="nearby">Nearby search</option>
                        <option value="text">Text search</option>
                    </select>
                </label>
                <label>Radius
                    <input type="hidden" name="radius" id="radius" value="{{ (int) $defaults['radius'] }}">
                    <div class="chips" id="radius-chips">
                        <button type="button" class="chip" data-m="1000">1 km</button>
                        <button type="button" class="chip" data-m="3000">3 km</button>
                        <button type="button" class="chip" data-m="5000">5 km</button>
                        <button type="button" class="chip" data-m="10000">10 km</button>
                    </div>
                </label>
            </div>
        </form>
    </section>

    <section class="panel" id="results-panel" style="display:none">
        <header class="panel-h">
            <h2>Results <small id="result-meta"></small></h2>
            <div class="seg" id="result-filter">
                <button type="button" class="on" data-f="all">All</button>
                <button type="button" data-f="new">New</button>
                <button type="button" data-f="dup">In CRM</button>
            </div>
        </header>
        <div class="kpis">
            <div><span>Found</span><strong id="kpi-found">0</strong></div>
            <div><span>New</span><strong id="kpi-new">0</strong></div>
            <div><span>Already in CRM</span><strong id="kpi-dup">0</strong></div>
            <div><span>Selected</span><strong id="kpi-sel">0</strong></div>
        </div>
        <div class="filters">
            <input type="search" id="result-q" placeholder="Filter this list">
            <label class="check"><input type="checkbox" id="pick-all"> Select all new</label>
            <button class="btn btn-ghost" type="button" id="more-btn" style="display:none">Load more</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="col-check"></th>
                        <th>Business</th>
                        <th>Address</th>
                        <th>Rating</th>
                        <th>Phone</th>
                        <th>Flag</th>
                    </tr>
                </thead>
                <tbody id="scrape-rows"></tbody>
            </table>
        </div>
        <div class="pager" id="scrape-pager"></div>
    </section>

    <div class="dock" id="scrape-dock">
        <div>
            <strong id="dock-count">0 selected</strong>
            <div class="sub">Duplicates are skipped automatically.</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-ghost" type="button" id="enrich-btn" style="background:#1e293b;color:#fff;border-color:#334155">Load phones</button>
            <button class="btn btn-primary" type="button" id="assign-open">Assign to staff</button>
        </div>
    </div>

    <section class="panel">
        <header class="panel-h"><h2>Recent jobs</h2></header>
        <div id="jobs" class="list"></div>
        <div class="pager" id="jobs-pager"></div>
    </section>
</div>

<div class="local-loader" id="scrape-loader">
    <div class="box">
        <div class="spin"></div>
        <div id="scrape-loader-text">Working…</div>
    </div>
</div>

<div class="modal" id="assign-modal" style="display:none" onclick="if (event.target === this) { this.style.display='none'; }">
    <div class="modal-card" onclick="event.stopPropagation();">
        <header class="panel-h">
            <h2>Assign selected leads</h2>
            <button type="button" class="btn btn-ghost" id="assign-close">Close</button>
        </header>
        <p class="muted" id="assign-summary">0 new places will be saved and assigned.</p>
        <form id="assign-form" class="stack" style="margin-top:12px">
            <label>Staff owner
                <select id="assign-staff" required>
                    <option value="">Select staff</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </label>
            <div style="display:flex;gap:8px">
                <button class="btn btn-primary" type="submit" id="assign-btn">Save and assign</button>
                <button class="btn btn-ghost" type="button" id="assign-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var searchUrl = @json(route('scraper.search'));
    var moreUrl = @json(route('scraper.more'));
    var autoUrl = @json(route('scraper.autocomplete'));
    var enrichUrl = @json(route('scraper.enrich'));
    var assignUrl = @json(route('scraper.assign'));
    var jobsUrl = @json(route('scraper.jobs'));
    var leadsUrl = @json(route('leads.index'));
    var defaultRadius = @json((int) $defaults['radius']);

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function alertBox(msg, type) {
        var el = document.getElementById('scrape-alert');
        el.className = 'scrape-alert show ' + (type || 'info');
        el.textContent = msg;
        if (window.GymLead && typeof GymLead.toast === 'function' && type !== 'info') {
            GymLead.toast(msg, type === 'err' ? 'err' : 'ok');
        }
    }
    function loader(on, text) {
        var el = document.getElementById('scrape-loader');
        document.getElementById('scrape-loader-text').textContent = text || 'Working…';
        el.classList.toggle('show', !!on);
    }
    function req(method, url, body) {
        var opts = {
            method: method,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
        if (method !== 'GET') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body || {});
        }
        return fetch(url, opts).then(function (res) {
            return res.text().then(function (text) {
                var json = {};
                try { json = text ? JSON.parse(text) : {}; } catch (e) { json = { message: text || 'Invalid response' }; }
                json.ok = res.ok && json.ok !== false;
                json._status = res.status;
                if (!res.ok) {
                    if (json.errors) {
                        var k = Object.keys(json.errors)[0];
                        json.message = json.errors[k][0];
                    }
                    json.ok = false;
                    json.message = json.message || ('Request failed (' + res.status + ')');
                }
                return json;
            });
        });
    }

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function () {
        var jobId = null, items = [], picked = {}, hasMore = false, filter = 'all';
        var previewPage = 1, previewPer = 15, jobsPage = 1, timer = null;
        var loc = document.getElementById('location');
        var box = document.getElementById('suggest');
        var form = document.getElementById('scrape-form');
        var searchBtn = document.getElementById('search-btn');
        var moreBtn = document.getElementById('more-btn');
        var modal = document.getElementById('assign-modal');

        document.querySelectorAll('#radius-chips .chip').forEach(function (chip) {
            if (chip.getAttribute('data-m') === String(defaultRadius)) chip.classList.add('on');
            chip.addEventListener('click', function () {
                document.querySelectorAll('#radius-chips .chip').forEach(function (c) { c.classList.remove('on'); });
                chip.classList.add('on');
                document.getElementById('radius').value = chip.getAttribute('data-m');
            });
        });

        function setStep(n) {
            document.querySelectorAll('.scrape-steps .st').forEach(function (el) {
                var s = parseInt(el.getAttribute('data-step'), 10);
                el.classList.toggle('on', s === n);
                el.classList.toggle('done', s < n);
            });
        }

        loc.addEventListener('input', function () {
            document.getElementById('place_id').value = '';
            clearTimeout(timer);
            var q = loc.value.trim();
            if (q.length < 2) { box.classList.remove('show'); return; }
            timer = setTimeout(function () {
                req('GET', autoUrl + '?q=' + encodeURIComponent(q)).then(function (res) {
                    if (!res.ok) { alertBox(res.message || 'Location lookup failed', 'err'); return; }
                    var rows = res.predictions || [];
                    box.innerHTML = rows.length
                        ? rows.map(function (p) {
                            return '<button type="button" data-id="' + esc(p.place_id) + '">' + esc(p.description) + '</button>';
                        }).join('')
                        : '<button type="button" disabled>No matches</button>';
                    box.classList.add('show');
                });
            }, 220);
        });
        box.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b || !b.getAttribute('data-id')) return;
            loc.value = b.textContent;
            document.getElementById('place_id').value = b.getAttribute('data-id');
            box.classList.remove('show');
        });
        document.addEventListener('click', function (e) {
            if (!box.contains(e.target) && e.target !== loc) box.classList.remove('show');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var location = loc.value.trim();
            var keyword = document.getElementById('keyword').value.trim();
            if (!location) { alertBox('Type a location first.', 'err'); return; }
            if (!keyword) { alertBox('Enter a keyword such as gym.', 'err'); return; }
            searchBtn.disabled = true;
            searchBtn.textContent = 'Searching…';
            loader(true, 'Querying Google Places…');
            alertBox('Searching Google Maps…', 'info');
            req('POST', searchUrl, {
                location: location,
                place_id: document.getElementById('place_id').value || null,
                keyword: keyword,
                type: document.getElementById('place-type').value,
                radius: parseInt(document.getElementById('radius').value, 10) || 3000,
                mode: document.getElementById('place-mode').value
            }).then(function (res) {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Search Maps';
                loader(false);
                if (!res.ok) { alertBox(res.message || 'Search failed', 'err'); return; }
                jobId = res.job_id;
                items = res.items || [];
                picked = {};
                previewPage = 1;
                hasMore = !!res.has_more;
                document.getElementById('results-panel').style.display = 'block';
                moreBtn.style.display = hasMore ? 'inline-flex' : 'none';
                setStep(2);
                render();
                alertBox((res.found || items.length) + ' places loaded. Select new ones, then assign.', 'ok');
                document.getElementById('results-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
                loadJobs();
            }).catch(function (err) {
                searchBtn.disabled = false;
                searchBtn.textContent = 'Search Maps';
                loader(false);
                alertBox('Network error: ' + (err && err.message ? err.message : 'request failed'), 'err');
            });
        });

        moreBtn.addEventListener('click', function () {
            if (!jobId) return;
            moreBtn.disabled = true;
            loader(true, 'Fetching next Google page…');
            req('POST', moreUrl, { job_id: jobId }).then(function (res) {
                moreBtn.disabled = false;
                loader(false);
                if (!res.ok) { alertBox(res.message || 'Could not load more', 'err'); return; }
                items = items.concat(res.items || []);
                hasMore = !!res.has_more;
                moreBtn.style.display = hasMore ? 'inline-flex' : 'none';
                render();
                alertBox('Added ' + ((res.items || []).length) + ' more places.', 'ok');
            }).catch(function () {
                moreBtn.disabled = false;
                loader(false);
                alertBox('Could not load more results.', 'err');
            });
        });

        function visibleItems() {
            var q = (document.getElementById('result-q').value || '').toLowerCase();
            return items.map(function (it, i) { it._i = i; return it; }).filter(function (it) {
                if (filter === 'new' && it.is_duplicate) return false;
                if (filter === 'dup' && !it.is_duplicate) return false;
                if (!q) return true;
                return (it.name || '').toLowerCase().indexOf(q) !== -1 || (it.address || '').toLowerCase().indexOf(q) !== -1;
            });
        }
        function selectedPlaces() {
            return items.filter(function (it, i) { return picked[i] && !it.is_duplicate; });
        }
        function updateCount() {
            var n = selectedPlaces().length;
            document.getElementById('dock-count').textContent = n + ' selected';
            document.getElementById('kpi-sel').textContent = n;
            document.getElementById('scrape-dock').classList.toggle('show', n > 0);
            document.getElementById('assign-summary').textContent = n + ' new place(s) will be saved and assigned.';
        }
        function render() {
            var vis = visibleItems();
            var last = Math.max(1, Math.ceil(vis.length / previewPer) || 1);
            if (previewPage > last) previewPage = last;
            var slice = vis.slice((previewPage - 1) * previewPer, previewPage * previewPer);
            var tb = document.getElementById('scrape-rows');
            if (!slice.length) {
                tb.innerHTML = '<tr><td colspan="6"><div class="empty">No places in this view.</div></td></tr>';
            } else {
                tb.innerHTML = slice.map(function (it) {
                    var i = it._i;
                    var flag = it.is_duplicate
                        ? '<span class="badge badge-duplicate">In CRM</span>'
                        : '<span class="badge badge-new">New</span>';
                    return '<tr class="' + (it.is_duplicate ? 'dim' : '') + '">' +
                        '<td class="col-check"><input type="checkbox" class="pick" data-i="' + i + '" ' + (it.is_duplicate ? 'disabled' : '') + ' ' + (picked[i] ? 'checked' : '') + '></td>' +
                        '<td><strong>' + esc(it.name) + '</strong><div class="sub">' + esc(it.category || '') + '</div></td>' +
                        '<td>' + esc(it.address || '') + '</td>' +
                        '<td>' + esc(String(it.rating || '—')) + '</td>' +
                        '<td>' + esc(it.phone || '—') + '</td>' +
                        '<td>' + flag + '</td></tr>';
                }).join('');
            }
            document.querySelectorAll('.pick').forEach(function (c) {
                c.addEventListener('change', function () {
                    if (c.checked) picked[c.getAttribute('data-i')] = true;
                    else delete picked[c.getAttribute('data-i')];
                    updateCount();
                });
            });
            var dups = items.filter(function (it) { return it.is_duplicate; }).length;
            document.getElementById('kpi-found').textContent = items.length;
            document.getElementById('kpi-new').textContent = items.length - dups;
            document.getElementById('kpi-dup').textContent = dups;
            document.getElementById('result-meta').textContent = items.length + ' loaded';
            if (window.GymLead && GymLead.pager) {
                GymLead.pager('scrape-pager', {
                    current_page: previewPage, last_page: last, total: vis.length, per_page: previewPer
                }, function (p) { previewPage = p; render(); });
            }
            updateCount();
        }

        document.getElementById('result-q').addEventListener('input', function () { previewPage = 1; render(); });
        document.getElementById('result-filter').addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            filter = b.getAttribute('data-f');
            document.querySelectorAll('#result-filter button').forEach(function (x) { x.classList.toggle('on', x === b); });
            previewPage = 1;
            render();
        });
        document.getElementById('pick-all').addEventListener('change', function () {
            var on = this.checked;
            items.forEach(function (it, i) {
                if (it.is_duplicate) return;
                if (on) picked[i] = true; else delete picked[i];
            });
            render();
        });

        document.getElementById('enrich-btn').addEventListener('click', function () {
            var sel = selectedPlaces();
            if (!sel.length) { alertBox('Select places first.', 'err'); return; }
            loader(true, 'Fetching phones and websites…');
            req('POST', enrichUrl, { place_ids: sel.map(function (p) { return p.google_place_id; }) }).then(function (res) {
                loader(false);
                if (!res.ok) { alertBox(res.message || 'Enrich failed', 'err'); return; }
                (res.items || []).forEach(function (d) {
                    items = items.map(function (it) {
                        if (it.google_place_id === d.google_place_id) return Object.assign({}, it, d);
                        return it;
                    });
                });
                render();
                alertBox('Contact details loaded.', 'ok');
            }).catch(function () { loader(false); alertBox('Enrich failed.', 'err'); });
        });

        function closeModal() { modal.style.display = 'none'; }
        document.getElementById('assign-open').addEventListener('click', function () {
            if (!selectedPlaces().length) { alertBox('Select at least one new place.', 'err'); return; }
            setStep(3);
            modal.style.display = 'grid';
        });
        document.getElementById('assign-close').addEventListener('click', closeModal);
        document.getElementById('assign-cancel').addEventListener('click', closeModal);

        document.getElementById('assign-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var sel = selectedPlaces();
            var staff = document.getElementById('assign-staff').value;
            if (!sel.length) { alertBox('Select at least one new place.', 'err'); return; }
            if (!staff) { alertBox('Pick a staff member.', 'err'); return; }
            if (!jobId) { alertBox('Run a search first.', 'err'); return; }
            var btn = document.getElementById('assign-btn');
            btn.disabled = true;
            loader(true, 'Saving leads and assigning…');
            req('POST', assignUrl, { job_id: jobId, assigned_to: staff, places: sel }).then(function (res) {
                btn.disabled = false;
                loader(false);
                if (!res.ok) { alertBox(res.message || 'Assign failed', 'err'); return; }
                closeModal();
                sel.forEach(function (p) {
                    items = items.map(function (it) {
                        if (it.google_place_id === p.google_place_id) it.is_duplicate = true;
                        return it;
                    });
                });
                picked = {};
                setStep(2);
                render();
                loadJobs();
                alertBox(res.message || 'Leads saved and assigned.', 'ok');
            }).catch(function () {
                btn.disabled = false;
                loader(false);
                alertBox('Assign failed.', 'err');
            });
        });

        function loadJobs() {
            req('GET', jobsUrl + '?page=' + jobsPage + '&per_page=10').then(function (res) {
                var list = res.jobs || [];
                document.getElementById('jobs').innerHTML = list.length
                    ? list.map(function (j) {
                        return '<div class="rowlink"><strong>' + esc(j.location_label || j.keyword || 'Job') + '</strong><small>' +
                            esc(j.status) + ' · found ' + j.results_found + ' · saved ' + j.saved_count + '</small></div>';
                    }).join('')
                    : '<p class="muted">No jobs yet.</p>';
            });
        }
        loadJobs();
    });
})();
</script>
@endpush
