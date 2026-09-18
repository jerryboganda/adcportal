# Live Queue TV — Waiting-Room Display & Patient Calling

*Added 2026-09-19 (`feat/live-queue-tv`). The full workflow contract for the
queue board, per user type, with the security and data-exposure rules.*

## What it is

Two surfaces over **one polling feed**, with no websocket infrastructure
(the architecture stance in `SAAS_GAP_MATRIX.md` — "polling-only realtime"):

| Surface | Route | Audience | Auth |
|---|---|---|---|
| **Waiting-room TV** (`TvDisplayView`) | `/tv?key=<display-key>` | Patients in the lounge | Public; the display key IS the credential |
| **Staff Queue Console** (`QueueBoardView`, tab "Live Queue TV") | SPA tab | Reception / tech / radiologist / billing / admin | Session + `queue view` (server-enforced) |

Both poll `GET /api/v1/queue/display` / `GET /api/v1/public/queue-display`
every 5 s, so a call made at any terminal reaches every screen within one
interval. There is no local-only queue state anywhere.

## Workflow, per user type

| User | Workflow |
|---|---|
| **Patient (waiting area)** | Watches the TV: per-zone NOW SERVING cards (token, name, room, elapsed), NEXT token chips, recently-called strip, announcement ticker. Hears the tri-tone chime + English/Urdu voice when their token is called (one tap on "Enable voice" per TV browser — browser autoplay policy). |
| **Receptionist** | Books/checks-in at Reception Desk (unchanged). On the console: **Call** moves the patient out of "Next in Line" into "Now Serving" on every screen and announces them; **Call again** re-announces (re-stamps `called_at`); **No-show** clears a called patient who never appeared. |
| **Technician** | Tech Worklist unchanged (Prepare → Start → Complete → Send to Reading); the TV reflects every step automatically. Technicians may also call from the console (`study checkin`). |
| **Radiologist / Billing** | Read-only console — no call buttons (no `study checkin`); the server refuses the action regardless of the UI. |
| **Tenant admin** | Display Setup panel on the console (`setting manage`): copy the TV link, regenerate it (kills the old link instantly), edit the ticker announcement. Grants `queue view` / `study checkin` via Users & RBAC as usual. |
| **Platform staff** | Nothing tenant-scoped; break-glass support sessions work exactly as everywhere else. |

## Backend contract

- `GET /api/v1/queue/display` — staff feed. `auth + tenant.active +
  throttle:tenant`, **`queue view` enforced server-side** (previously the
  permission gated only the SPA nav).
- `GET /api/v1/public/queue-display?key=…` — kiosk feed. `throttle:20,1`;
  timing-safe key lookup in the per-tenant `settings` KV; 404 on unknown key
  **and** on a non-subscribable tenant (a suspended clinic's TV goes dark,
  mirroring `EnsureTenantActive`).
- `GET/PUT /api/v1/queue/display/settings` — `setting manage`. Lazily
  provisions `queue_display_key` (`tv_` + 24 random chars); PUT accepts
  `announcement` (≤500 chars, shown first on the ticker) and
  `regenerateKey`. No dedicated migration — plain `settings` rows.
- `POST /studies/{id}/transition {action:'call'}` (`study checkin`) — now
  **guarded**: only `booked / checked_in / preparing / in_progress` studies
  can be called (422 otherwise). Re-calling re-stamps `called_at`, which
  every TV treats as a fresh announcement. Audited `queue_patient_called`.
- `StudyState` now allows `checked_in → no_show` so a called patient who
  never appeared can be cleared from the board.
- "Serving" semantics: `called_at` set **or** state `preparing / in_progress`
  — a called patient therefore leaves "Next in Line" immediately (the old
  board kept them there until a technologist acted). The board is
  **today-only** (`date_sort`), so stale check-ins can never linger.

### Deliberate exclusions

- **No per-call notification-center row.** A busy clinic calls hundreds of
  patients a day; the 200-row notification center would flood and drown
  critical alerts. The polling feed is the broadcast; the audit log is the
  record.
- **No websockets.** Documented polling stance; no broadcast infra on the VPS.
- **No patient portal queue page.** Patient accounts are portal-blocked by
  design; the TV is the patient-facing queue surface.

## Data exposure rules (minimal PHI)

`ApiShape::queueEntry` emits ONLY: token, patient display name, priority,
state, modality (id/code/name/color), room name, queue timestamps. The
public kiosk payload must never grow MRN, contact, DOB, notes, financials
or report data — it is designed for an unattended public screen. The TV's
hospital name follows the same brand chain as the staff topbar
(`TenantBrandingService` → clinic name → platform default), so the lounge
screen can never disagree with the app.

## Kiosk runbook

1. Sign in as an admin → **Live Queue TV → Display Setup → Copy link**.
2. Open the link in the waiting-area TV browser (Chrome/Firefox, autostart
   on boot is OS-level). Tap "Enable voice announcements" once.
3. Optionally filter zones per hall with `&zones=CT,MR` (modality codes).
4. If the link leaks or a TV is decommissioned: **Regenerate**. Old screens
   show "Display link is no longer valid" within seconds and never a login.

## Regressions

`tests/Feature/QueueDisplayTest.php` (staff gating, serving semantics,
today-only + tenant isolation, minimal PHI, key lifecycle, brand chain) and
`tests/portal.spec.js` (console call flow + kiosk journey in CI).
