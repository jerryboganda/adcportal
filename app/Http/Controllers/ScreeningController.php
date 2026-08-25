<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\StudyScreeningAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScreeningController extends Controller
{
    // ==================== Form builder ====================

    public function index()
    {
        if (! Auth::user()->isAbleTo('report manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $forms = ScreeningForm::forClinic()->withCount('questions')->orderBy('name')->get();

        return view('screening.index', compact('forms'));
    }

    public function store(Request $request)
    {
        if (! Auth::user()->isAbleTo('report template create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'modality_id' => 'nullable|integer|exists:modalities,id',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string|max:1000',
            'questions.*.answer_type' => 'required|in:boolean,select,text',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        DB::transaction(function () use ($request) {
            $form = ScreeningForm::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name).'-'.Str::random(4),
                'description' => $request->description,
                'modality_id' => $request->modality_id,
                'is_active' => true,
                'business_id' => getActiveBusiness(),
                'created_by' => creatorId(),
            ]);

            foreach ($request->input('questions', []) as $i => $q) {
                if (trim((string) ($q['question_text'] ?? '')) === '') {
                    continue;
                }
                ScreeningQuestion::create([
                    'screening_form_id' => $form->id,
                    'question_text' => $q['question_text'],
                    'help_text' => $q['help_text'] ?? null,
                    'answer_type' => $q['answer_type'],
                    'options' => $q['answer_type'] === 'select'
                        ? array_values(array_filter(array_map('trim', explode("\n", (string) ($q['options_text'] ?? '')))))
                        : null,
                    'risk_value' => $q['risk_value'] ?? null,
                    'is_risk_blocking' => (bool) ($q['is_risk_blocking'] ?? true),
                    'sort_order' => $i,
                ]);
            }
        });

        return redirect()->back()->with('success', __('Screening form created.'));
    }

    public function toggle(ScreeningForm $form)
    {
        if (! Auth::user()->isAbleTo('report template edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $form->update(['is_active' => ! $form->is_active]);

        return redirect()->back()->with('success', __('Screening form updated.'));
    }

    public function destroy(ScreeningForm $form)
    {
        if (! Auth::user()->isAbleTo('report template delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $form->delete();

        return redirect()->back()->with('success', __('Screening form deleted.'));
    }

    // ==================== Per-study capture ====================

    /** Show + capture the screening questionnaire for one study. */
    public function answer(Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('study screen')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $service = $appointment->ServiceData;
        $form = ScreeningForm::forClinic()
            ->where('is_active', true)
            ->where(function ($q) use ($service) {
                $q->where('modality_id', optional($service)->modality_id)->orWhereNull('modality_id');
            })
            ->orderByDesc('modality_id') // modality-specific first
            ->first();

        if (! $form) {
            return redirect()->back()->with('error', __('No active screening form configured.'));
        }

        $questions = $form->questions()->get();
        $existing = $appointment->screeningAnswers->keyBy('screening_question_id');

        return view('screening.answer', compact('appointment', 'form', 'questions', 'existing'));
    }

    public function submitAnswers(Request $request, Appointment $appointment)
    {
        if (! Auth::user()->isAbleTo('study screen')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $answers = $request->input('answers', []);
        $overrides = $request->input('overrides', []);

        DB::transaction(function () use ($appointment, $answers, $overrides) {
            $hasRisk = false;

            foreach ($answers as $questionId => $value) {
                $question = ScreeningQuestion::find($questionId);
                if (! $question) {
                    continue;
                }

                $isRisk = $question->flagsRisk(is_array($value) ? null : $value);
                $hasRisk = $hasRisk || $isRisk;

                StudyScreeningAnswer::updateOrCreate(
                    ['appointment_id' => $appointment->id, 'screening_question_id' => $questionId],
                    [
                        'answer_value' => is_array($value) ? json_encode($value) : $value,
                        'is_risk' => $isRisk,
                        'override_reason' => trim((string) ($overrides[$questionId] ?? '')) ?: null,
                        'answered_by' => Auth::id(),
                    ]
                );
            }

            $unresolvedRisk = $hasRisk && collect($overrides)
                ->filter(fn ($r) => trim((string) $r) !== '')
                ->isEmpty();

            $appointment->forceFill([
                'screening_required' => true,
                'screening_cleared' => ! $hasRisk || ! $unresolvedRisk,
            ])->save();
        });

        AuditLog::record('screening_submitted', $appointment);

        return redirect()
            ->to($this->returnUrl($appointment))
            ->with('success', __('Safety screening recorded.'));
    }

    private function returnUrl(Appointment $appointment): string
    {
        return route('study.technologist');
    }
}
