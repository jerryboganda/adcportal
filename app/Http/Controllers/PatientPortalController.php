<?php

namespace App\Http\Controllers;

use App\Enums\StudyState;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;

/**
 * Patient self-service: my studies, statuses, prep instructions,
 * and download of finalized reports that have been released.
 */
class PatientPortalController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $studies = Appointment::query()
            ->where('customer_id', $user->id)
            ->whereIn('workflow_state', [
                StudyState::Booked->value, StudyState::CheckedIn->value, StudyState::Preparing->value,
                StudyState::InProgress->value, StudyState::Acquired->value, StudyState::Reading->value,
                StudyState::Reported->value, StudyState::Delivered->value,
            ])
            ->with(['ServiceData.modality', 'LocationData', 'radiologyReports'])
            ->orderByDesc('date_sort')
            ->limit(50)
            ->get();

        return view('portal.studies', compact('studies'));
    }

    public function downloadReport(Appointment $appointment)
    {
        abort_unless($appointment->customer_id === Auth::id(), 404);

        // Patients may only fetch FINAL signed reports that the clinic released.
        $released = $appointment->radiologyReports
            ->first(fn ($r) => $r->isFinal() && $r->releases()->where('channel', '!=', 'hand')->exists()
                || ($r->isSigned() && company_setting('release_reports_to_patients')));

        if (! $released?->pdf_path) {
            abort(404);
        }

        return \Storage::disk('public')->download($released->pdf_path, basename($released->pdf_path));
    }
}
