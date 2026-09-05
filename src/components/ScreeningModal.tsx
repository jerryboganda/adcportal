import React, { useState } from 'react';
import {
  ShieldAlert,
  ShieldCheck,
  AlertTriangle,
  CheckCircle2,
  X,
  FileCheck
} from 'lucide-react';
import { Appointment, ScreeningForm, StudyScreeningAnswer } from '../types';

interface ScreeningModalProps {
  appointment: Appointment;
  forms: ScreeningForm[];
  onSaveScreening: (aptId: string, answers: StudyScreeningAnswer[], isCleared: boolean) => void;
  onClose: () => void;
}

export const ScreeningModal: React.FC<ScreeningModalProps> = ({
  appointment,
  forms,
  onSaveScreening,
  onClose,
}) => {
  // Determine relevant form based on modality (MRI form vs Contrast form)
  const isMri = appointment.modality.code === 'MR';
  const activeForm = isMri
    ? forms.find(f => f.slug === 'mri-safety-screening') || forms[0]
    : forms.find(f => f.slug === 'contrast-screening') || forms[0];

  // Initialize answers from existing or defaults
  const [answersState, setAnswersState] = useState<Record<string, { val: string; override?: string }>>(() => {
    const init: Record<string, { val: string; override?: string }> = {};
    if (appointment.screeningAnswers && appointment.screeningAnswers.length > 0) {
      appointment.screeningAnswers.forEach(a => {
        init[a.questionId] = { val: a.answerValue, override: a.overrideReason };
      });
    } else {
      activeForm.questions.forEach(q => {
        init[q.id] = { val: 'no', override: '' };
      });
    }
    return init;
  });

  const [overrideSupervisor, setOverrideSupervisor] = useState('');

  // Check if any affirmative risk answers exist
  const hasBlockingRisk = activeForm.questions.some(q => {
    const ans = answersState[q.id]?.val;
    return ans === q.riskValue && q.isRiskBlocking;
  });

  const hasAnyRisk = activeForm.questions.some(q => {
    const ans = answersState[q.id]?.val;
    return ans === q.riskValue;
  });

  const handleAnswerChange = (qId: string, val: string) => {
    setAnswersState(prev => ({
      ...prev,
      [qId]: { ...prev[qId], val }
    }));
  };

  const handleOverrideChange = (qId: string, override: string) => {
    setAnswersState(prev => ({
      ...prev,
      [qId]: { ...prev[qId], override }
    }));
  };

  const handleSubmit = () => {
    const formattedAnswers: StudyScreeningAnswer[] = activeForm.questions.map(q => {
      const state = answersState[q.id] || { val: 'no' };
      const isRisk = state.val === q.riskValue;
      return {
        appointmentId: appointment.id,
        questionId: q.id,
        questionText: q.questionText,
        answerValue: state.val,
        isRisk,
        overrideReason: state.override || (hasBlockingRisk ? overrideSupervisor : undefined),
        answeredBy: 'Technologist On Duty',
        answeredAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      };
    });

    // If there is a blocking risk, require an override reason before clearing
    const cleared = !hasBlockingRisk || Boolean(overrideSupervisor.trim());
    onSaveScreening(appointment.id, formattedAnswers, cleared);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-5 max-h-[90vh] flex flex-col">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-slate-200 pb-3">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center shadow-xs">
              <ShieldAlert className="w-5 h-5 text-amber-600" />
            </div>
            <div>
              <h2 className="font-bold text-slate-900 text-base">{activeForm.name}</h2>
              <p className="text-xs text-slate-500">
                Patient: <strong className="text-slate-900">{appointment.patient.name}</strong> ({appointment.patient.mrn}) • Token #{appointment.tokenNumber}
              </p>
            </div>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Scrollable Questions Form */}
        <div className="overflow-y-auto pr-2 space-y-4 flex-1">
          <div className="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200">
            {activeForm.description}
          </div>

          <div className="space-y-3">
            {activeForm.questions.map((q, idx) => {
              const currentVal = answersState[q.id]?.val || 'no';
              const isRiskFlagged = currentVal === q.riskValue;

              return (
                <div
                  key={q.id}
                  className={`p-4 rounded-xl border transition-all ${
                    isRiskFlagged
                      ? 'bg-rose-50/80 border-rose-300 ring-1 ring-rose-200'
                      : 'bg-white border-slate-200 shadow-xs'
                  }`}
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                      <div className="flex items-center space-x-2">
                        <span className="w-5 h-5 rounded-md bg-slate-100 text-slate-700 font-mono text-[10px] flex items-center justify-center font-bold border border-slate-200">
                          {idx + 1}
                        </span>
                        <h4 className="text-xs font-semibold text-slate-900">{q.questionText}</h4>
                      </div>
                      {q.helpText && (
                        <p className="text-[11px] text-slate-500 pl-7">{q.helpText}</p>
                      )}
                    </div>

                    {/* Radio options */}
                    <div className="flex items-center space-x-2 shrink-0">
                      <button
                        type="button"
                        onClick={() => handleAnswerChange(q.id, 'no')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer ${
                          currentVal === 'no'
                            ? 'bg-emerald-600 text-white shadow-xs'
                            : 'bg-slate-100 text-slate-600 hover:text-slate-900 border border-slate-200'
                        }`}
                      >
                        NO
                      </button>
                      <button
                        type="button"
                        onClick={() => handleAnswerChange(q.id, 'yes')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer ${
                          currentVal === 'yes'
                            ? 'bg-rose-600 text-white shadow-xs animate-pulse'
                            : 'bg-slate-100 text-slate-600 hover:text-slate-900 border border-slate-200'
                        }`}
                      >
                        YES
                      </button>
                    </div>
                  </div>

                  {/* If affirmative and blocking, show specific override prompt */}
                  {isRiskFlagged && q.isRiskBlocking && (
                    <div className="mt-3 pl-7 pt-2 border-t border-rose-200">
                      <label className="block text-[11px] font-bold text-rose-700 mb-1 flex items-center gap-1">
                        <AlertTriangle className="w-3.5 h-3.5 text-rose-600" />
                        SAFETY RISK DETECTED: Specific Clearance / Verification Reason
                      </label>
                      <input
                        type="text"
                        placeholder="e.g. Implant certificate verified MRI-Conditional at 1.5T; Radiologist approved."
                        value={answersState[q.id]?.override || ''}
                        onChange={(e) => handleOverrideChange(q.id, e.target.value)}
                        className="w-full bg-white text-slate-900 p-2 rounded-lg border border-rose-300 text-xs focus:outline-none focus:ring-1 focus:ring-rose-500"
                      />
                    </div>
                  )}
                </div>
              );
            })}
          </div>

          {/* Supervisor Global Override if any blocking risk detected */}
          {hasBlockingRisk && (
            <div className="bg-rose-50 border border-rose-300 p-4 rounded-xl space-y-2">
              <div className="flex items-center space-x-2 text-rose-700 font-bold text-xs">
                <AlertTriangle className="w-4 h-4 text-rose-600" />
                <span>Supervising Radiologist / Clinical Safety Override Required</span>
              </div>
              <p className="text-[11px] text-slate-700">
                One or more critical implant/contrast contraindications were flagged. An authorized clinical rationale must be documented to clear patient for scan entry.
              </p>
              <input
                type="text"
                placeholder="Enter doctor's approval statement (e.g. Dr. Shahzad approved scan with low-dose contrast pre-medication)..."
                value={overrideSupervisor}
                onChange={(e) => setOverrideSupervisor(e.target.value)}
                className="w-full bg-white text-slate-900 p-2.5 rounded-lg border border-rose-300 text-xs focus:outline-none focus:ring-1 focus:ring-rose-500 font-medium"
              />
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="border-t border-slate-200 pt-3 flex items-center justify-between">
          <div className="text-xs">
            {hasBlockingRisk && !overrideSupervisor ? (
              <span className="text-rose-700 font-bold flex items-center gap-1">
                <ShieldAlert className="w-4 h-4 text-rose-600" /> Action Blocked: Provide Doctor Override
              </span>
            ) : hasAnyRisk ? (
              <span className="text-amber-700 font-semibold flex items-center gap-1">
                <AlertTriangle className="w-4 h-4 text-amber-600" /> Cleared with Documented Exceptions
              </span>
            ) : (
              <span className="text-emerald-700 font-semibold flex items-center gap-1">
                <ShieldCheck className="w-4 h-4 text-emerald-600" /> 100% Negative: Safe for Examination
              </span>
            )}
          </div>

          <div className="flex space-x-2">
            <button
              onClick={onClose}
              className="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs"
            >
              Cancel
            </button>
            <button
              onClick={handleSubmit}
              disabled={hasBlockingRisk && !overrideSupervisor.trim()}
              className="flex items-center space-x-1.5 px-5 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md shadow-cyan-600/30 cursor-pointer disabled:opacity-50"
            >
              <FileCheck className="w-4 h-4" />
              <span>Save & Sign Questionnaire</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
