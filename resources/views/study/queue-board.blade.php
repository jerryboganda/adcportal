<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="{{ $refresh }}">
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
    </style>
</head>
<body>
    <h1>{{ getCompanyAllSetting()['title_text'] ?? config('app.name') }} — {{ __('Queue Board') }}</h1>

    <div class="grid">
        <div class="panel">
            <h2>{{ __('Now Serving') }}</h2>
            @forelse($nowServing as $s)
                <div class="row-item serving">
                    <span class="token">{{ $s->token_number ? '#'.$s->token_number : '—' }}</span>
                    <span class="name">{{ $s->patientDisplayName() }}</span>
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
                    <span class="name">{{ $w->patientDisplayName() }}</span>
                    <span class="room">{{ optional(optional($w->ServiceData)->modality)->name ?? '' }}</span>
                </div>
            @empty
                <div class="empty">{{ __('The waiting list is empty.') }}</div>
            @endforelse
        </div>
    </div>

    <footer>
        <span>{{ __('Auto-refreshes every :sec seconds', ['sec' => $refresh]) }}</span>
        <span>{{ now()->format('d M Y — H:i') }}</span>
    </footer>
</body>
</html>
