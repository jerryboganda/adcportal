import React, { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Check, Loader2, Printer, Ruler, Save } from 'lucide-react';
import {
  fetchPrintSettings,
  printDeviceId,
  savePrintSettings,
} from '../print/printService';
import type { PrintArtifactMeta, PrintPaper, PrintRegistryPayload, PrintSettingsModel } from '../print/types';

/**
 * Printing & documents — tenant administration.
 *
 * Everything a clinic legitimately needs to change about its own paper lives
 * here: which paper each document defaults to, the A4 margin preset, the thermal
 * dimensions of the counter printers, the visibility of the logo/barcode/
 * signature, currency and date presentation, and the footer text.
 *
 * It is deliberately NOT a CSS editor. Every value is structured and clamped on
 * the server, so a tenant cannot author markup, cannot configure a 900 mm
 * receipt, and cannot put a clinical report on a receipt roll — attempting
 * either is silently corrected rather than accepted and then printed.
 *
 * The panel also explains the one dimension that is NOT tenant-wide: the thermal
 * safe width belongs to the printer in front of the operator, and is calibrated
 * per workstation from the print preview itself (`Calibrate`), because a counter
 * whose roll only feeds 58 mm must be fixable without a support ticket.
 */

const PAPER_LABELS: Record<PrintPaper, string> = {
  a4: 'A4 sheet',
  thermal80: '80 mm thermal roll',
  label: 'Label tag',
};

const CURRENCY_LABELS: Record<string, string> = {
  symbol: 'Symbol (Rs. 2,000.00)',
  code: 'Currency code (PKR 2,000.00)',
  sign: 'Compact sign (Rs. 2,000)',
};

export const PrintSettingsPanel: React.FC = () => {
  const [payload, setPayload] = useState<PrintRegistryPayload | null>(null);
  const [form, setForm] = useState<PrintSettingsModel | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const data = await fetchPrintSettings();
        if (cancelled) {
          return;
        }

        setPayload({
          artifacts: data.artifacts,
          settings: data.settings,
          paperProfiles: [],
          currencyStyles: [],
          dateFormats: [],
          marginPresets: data.marginPresets,
        });
        setForm(data.settings);
      } catch {
        if (!cancelled) {
          setError('The printing settings could not be loaded.');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const deviceId = useMemo(() => printDeviceId(), []);
  const deviceCalibration = form?.calibration?.[deviceId] ?? null;

  const save = async () => {
    if (!form) {
      return;
    }

    setSaving(true);
    setError(null);
    setSaved(false);

    try {
      // No `device` is sent: this is the administrator's tenant-wide save. The
      // per-workstation dimensions are sent by the preview's calibration panel,
      // which names its device so it can never move another desk's receipts.
      const settings = await savePrintSettings({
        defaultPapers: form.defaultPapers,
        a4MarginPreset: form.a4MarginPreset,
        thermalSafeWidthMm: form.thermalSafeWidthMm,
        thermalFeedMm: form.thermalFeedMm,
        thermalFontScale: form.thermalFontScale,
        showLogo: form.showLogo,
        showBarcode: form.showBarcode,
        showSignature: form.showSignature,
        currencyStyle: form.currencyStyle,
        dateFormat: form.dateFormat,
        invoiceFooterText: form.invoiceFooterText,
        receiptFooterText: form.receiptFooterText,
        markReprints: form.markReprints,
      });

      setForm(settings);
      setSaved(true);
      window.setTimeout(() => setSaved(false), 4000);
    } catch {
      setError('The printing settings could not be saved.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="rounded-xl border border-slate-200 bg-white p-6 text-xs text-slate-500 inline-flex items-center gap-2">
        <Loader2 className="w-4 h-4 animate-spin" /> Loading printing settings…
      </div>
    );
  }

  if (!form || !payload) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 inline-flex items-center gap-2">
        <AlertTriangle className="w-4 h-4" /> {error ?? 'Printing settings are unavailable.'}
      </div>
    );
  }

  const artifacts: PrintArtifactMeta[] = payload.artifacts;
  const marginPresets = payload.marginPresets ?? [];

  const Field: React.FC<{ label: string; hint?: string; children: React.ReactNode }> = ({ label, hint, children }) => (
    <div>
      <label className="block text-[11px] font-bold uppercase tracking-wide text-slate-500">{label}</label>
      <div className="mt-1">{children}</div>
      {hint ? <p className="mt-1 text-[11px] text-slate-500">{hint}</p> : null}
    </div>
  );

  const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-cyan-500 focus:outline-none';

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-5">
        <div className="flex items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className="w-9 h-9 rounded-xl bg-cyan-50 border border-cyan-200 text-cyan-700 flex items-center justify-center">
              <Printer className="w-4 h-4" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-slate-900">Documents & paper</h3>
              <p className="text-xs text-slate-500">
                The paper a document prints on follows its meaning: a radiology report can never be sent to a
                receipt roll. Only formats the document supports are offered.
              </p>
            </div>
          </div>

          <button
            onClick={() => void save()}
            disabled={saving}
            className="flex items-center gap-2 rounded-xl bg-cyan-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-cyan-500 disabled:opacity-60 cursor-pointer"
          >
            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : saved ? <Check className="w-4 h-4" /> : <Save className="w-4 h-4" />}
            <span>{saved ? 'Saved' : 'Save settings'}</span>
          </button>
        </div>

        {error ? (
          <div className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800 inline-flex items-center gap-2">
            <AlertTriangle className="w-3.5 h-3.5" /> {error}
          </div>
        ) : null}

        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {artifacts.map((artifact) => (
            <Field
              key={artifact.artifact}
              label={artifact.label}
              hint={artifact.allowedPapers.length > 1 ? 'A4 sheet or 80 mm roll' : `${PAPER_LABELS[artifact.allowedPapers[0]]} only`}
            >
              <select
                className={inputClass}
                value={form.defaultPapers[artifact.artifact] ?? artifact.defaultPaper}
                disabled={artifact.allowedPapers.length <= 1}
                onChange={(event) =>
                  setForm({
                    ...form,
                    defaultPapers: { ...form.defaultPapers, [artifact.artifact]: event.target.value as PrintPaper },
                  })
                }
              >
                {artifact.allowedPapers.map((paper) => (
                  <option key={paper} value={paper}>
                    {PAPER_LABELS[paper]}
                  </option>
                ))}
              </select>
            </Field>
          ))}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
          <div className="flex items-center gap-2">
            <Ruler className="w-4 h-4 text-slate-500" />
            <h3 className="text-sm font-bold text-slate-900">A4 margins</h3>
          </div>
          <Field label="Margin preset" hint="Applies to every A4 document, including reports and statements.">
            <select
              className={inputClass}
              value={form.a4MarginPreset}
              onChange={(event) => setForm({ ...form, a4MarginPreset: event.target.value })}
            >
              {marginPresets.map((preset) => (
                <option key={preset} value={preset}>
                  {preset}
                </option>
              ))}
            </select>
          </Field>

          <div className="grid grid-cols-2 gap-3">
            <Field label="Currency">
              <select
                className={inputClass}
                value={form.currencyStyle}
                onChange={(event) => setForm({ ...form, currencyStyle: event.target.value as PrintSettingsModel['currencyStyle'] })}
              >
                {Object.entries(CURRENCY_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Date & time format">
              <select
                className={inputClass}
                value={form.dateFormat}
                onChange={(event) => setForm({ ...form, dateFormat: event.target.value })}
              >
                {[form.dateFormat, 'd M Y, h:i A', 'd/m/Y H:i', 'Y-m-d H:i'].filter((value, index, all) => all.indexOf(value) === index).map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
            </Field>
          </div>

          <div className="flex flex-wrap gap-4 pt-1">
            {([
              ['showLogo', 'Tenant logo'],
              ['showBarcode', 'Barcode / QR'],
              ['showSignature', 'Signature block'],
              ['markReprints', 'Mark reprints'],
            ] as const).map(([key, label]) => (
              <label key={key} className="inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                <input
                  type="checkbox"
                  checked={form[key]}
                  onChange={(event) => setForm({ ...form, [key]: event.target.checked })}
                />
                {label}
              </label>
            ))}
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
          <div className="flex items-center gap-2">
            <Printer className="w-4 h-4 text-slate-500" />
            <h3 className="text-sm font-bold text-slate-900">Thermal printers (tenant default)</h3>
          </div>
          <p className="text-xs text-slate-500">
            These are the defaults for rolls that have not been calibrated. A counter whose printer feeds less
            than this sets its own width from the print preview — that value is stored against that workstation
            and never changes another desk's receipts.
          </p>

          <div className="grid grid-cols-3 gap-3">
            <Field label="Printable width">
              <input
                className={inputClass}
                type="number"
                min={48}
                max={80}
                step={0.5}
                value={form.thermalSafeWidthMm}
                onChange={(event) => setForm({ ...form, thermalSafeWidthMm: Number(event.target.value) })}
              />
            </Field>
            <Field label="Bottom feed">
              <input
                className={inputClass}
                type="number"
                min={0}
                max={30}
                step={0.5}
                value={form.thermalFeedMm}
                onChange={(event) => setForm({ ...form, thermalFeedMm: Number(event.target.value) })}
              />
            </Field>
            <Field label="Text size">
              <input
                className={inputClass}
                type="number"
                min={0.85}
                max={1.25}
                step={0.05}
                value={form.thermalFontScale}
                onChange={(event) => setForm({ ...form, thermalFontScale: Number(event.target.value) })}
              />
            </Field>
          </div>

          <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-600">
            <span className="font-bold text-slate-800">This workstation</span> ({deviceId}){' '}
            {deviceCalibration
              ? `is calibrated to ${deviceCalibration.safeWidthMm} mm printable, ${deviceCalibration.feedMm} mm feed.`
              : 'uses the tenant defaults above.'}
          </div>
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4">
        <h3 className="text-sm font-bold text-slate-900">Document footers</h3>
        <div className="grid gap-4 lg:grid-cols-2">
          <Field label="Invoice footer" hint="Printed at the foot of A4 tax invoices. Plain text only.">
            <textarea
              className={`${inputClass} h-20 resize-none`}
              value={form.invoiceFooterText}
              onChange={(event) => setForm({ ...form, invoiceFooterText: event.target.value })}
            />
          </Field>
          <Field label="Receipt footer" hint="Printed at the foot of 80 mm receipts.">
            <textarea
              className={`${inputClass} h-20 resize-none`}
              value={form.receiptFooterText}
              onChange={(event) => setForm({ ...form, receiptFooterText: event.target.value })}
            />
          </Field>
        </div>
      </div>
    </div>
  );
};
