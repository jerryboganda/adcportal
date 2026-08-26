<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Patient Queue') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #0f172a; color: #e2e8f0;
            min-height: 100vh; padding: 2.5rem 3rem;
        }
        h1 { font-size: 2.4rem; letter-spacing: .06em; text-transform: uppercase; color: #38bdf8; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; margin-top: 2rem; }
        .panel h2 { font-size: 1.3rem; text-transform: uppercase; letter-spacing: .12em; color:#94a3b8; margin-bottom: 1rem; border-bottom: 2px solid #1e293b; padding-bottom: .5rem;}
        .row-item { display: flex; justify-content: space-between; align-items:center; background:#1e293b; border-radius:.75rem; padding: 1rem 1.25rem; margin-bottom:.7rem; font-size:1.35rem;}
        .row-item.serving { background:#065f46; border-left: 6px solid #34d399; }
        .token { font-weight:800; color:#fbbf24; min-width:6ch;}
        .name { flex:1; padding:0 1rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
        .room { color:#94a3b8; font-size:1.05rem;}
        .empty { color:#64748b; font-style: italic; font-size:1.15rem; padding:1rem 0;}
        footer { margin-top:2.5rem; color:#475569; font-size:.95rem; display:flex; justify-content:space-between;}
        @media (prefers-reduced-motion: no-preference) {
            #board.flash .serving { animation: servePulse .9s ease-out; }
            @keyframes servePulse {
                0% { transform: scale(.985); box-shadow: 0 0 0 0 rgba(52,211,153,.55); }
                100% { transform: scale(1); box-shadow: 0 0 0 14px rgba(52,211,153,0); }
            }
        }
        @media (max-width: 900px) { body { padding: 1.25rem; } .grid { grid-template-columns: 1fr; gap: 1.5rem; } h1 { font-size: 1.6rem; } }
    </style>
</head>
<body>
    @php
        $maskName = function ($name) {
            $parts = preg_split('/\s+/', trim((string) $name));
            if (count($parts) <= 1) {
                return $parts[0] ?? '';
            }
            return $parts[0] . ' ' . strtoupper(mb_substr(end($parts), 0, 1)) . '.';
        };
    @endphp

    <h1>{{ getCompanyAllSetting()['title_text'] ?? config('app.name') }} — {{ __('Queue Board') }}</h1>

    <div id="board">
        <div class="grid">
            <div class="panel">
                <h2>{{ __('Now Serving') }}</h2>
                @forelse($nowServing as $s)
                    <div class="row-item serving">
                        <span class="token">{{ $s->token_number ? '#'.$s->token_number : '—' }}</span>
                        <span class="name">{{ $maskName($s->patientDisplayName()) }}</span>
                        <span class="room">{{ optional(optional($s->ServiceData)->modality)->name ?? '' }}</span>
                    </div>
                @empty
                    <div class="empty">{{ __('No study currently in progress.') }}</div>
                @endforelse
            </div>

            <div class="panel">
                <h2>{{ __('Waiting') }} ({{ $waiting->count() }})</h2>
                @forelse($waiting as $w)
                    <div class="row-item">
                        <span class="token">{{ $w->token_number ? '#'.$w->token_number : '—' }}</span>
                        <span class="name">{{ $maskName($w->patientDisplayName()) }}</span>
                        <span class="room">{{ optional(optional($w->ServiceData)->modality)->name ?? '' }}</span>
                    </div>
                @empty
                    <div class="empty">{{ __('The waiting list is empty.') }}</div>
                @endforelse
            </div>
        </div>

        <footer>
            <span>{{ __('Auto-refreshes every :sec seconds', ['sec' => $refresh]) }}</span>
            <span id="clock">{{ now()->format('d M Y — H:i') }}</span>
        </footer>
    </div>

    <script>
        (function () {
            var last = '';
            var board = document.getElementById('board');
            var clockEl = document.getElementById('clock');

            function renderClock() {
                var d = new Date();
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                function p(n) { return n < 10 ? '0' + n : '' + n; }
                clockEl.textContent = p(d.getDate()) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' — ' + p(d.getHours()) + ':' + p(d.getMinutes());
            }

            async function poll() {
                try {
                    var res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                    if (!res.ok) return;
                    var doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    var fresh = doc.getElementById('board');
                    if (!fresh) return;
                    var html = fresh.innerHTML;
                    if (html !== last) {
                        var hadPrevious = last !== '';
                        board.innerHTML = html;
                        if (hadPrevious) {
                            board.classList.remove('flash');
                            void board.offsetWidth;
                            board.classList.add('flash');
                        }
                        last = html;
                    }
                } catch (e) { /* keep showing last good state */ }
            }

            renderClock();
            setInterval(renderClock, 15000);
            poll();
            setInterval(poll, {{ (int) $refresh * 1000 }});
        })();
    </script>
</body>
</html>
