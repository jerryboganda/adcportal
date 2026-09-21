import React, { useMemo } from 'react';
import type {
  PrintBranding,
  PrintCode,
  PrintColumn,
  PrintCriticalLog,
  PrintDocumentModel,
  PrintItem,
  PrintLabeledValue,
  PrintMark,
  PrintNotice,
  PrintObservation,
  PrintPageFoot,
  PrintParty,
  PrintPayment,
  PrintSection,
  PrintSignature,
  PrintStatus,
  PrintTone,
  PrintTotal,
} from './types';

/**
 * Print document primitives.
 *
 * These mirror `resources/views/print/partials/*.blade.php` CLASS FOR CLASS and
 * element for element. That mirroring is the parity contract: the same markup
 * tree, styled by the same stylesheet, painted by the same engines — a browser
 * for the preview and the print dialog, DomPDF or headless Chromium for the PDF.
 * `tests/print/visual.spec.js` compares the two renderings, so a structural
 * change made on one side and not the other fails in CI instead of on paper.
 *
 * Rules for everything in this file:
 *   - no Tailwind utility classes (app CSS, including its responsive variants,
 *     must not be able to change printed geometry);
 *   - no `mm`/percentage arithmetic of our own: geometry comes from the payload;
 *   - no formatting: every string is already finished by the server.
 */

const toneClass = (tone?: PrintTone): string => `pd-tone-${tone ?? 'default'}`;

export const DocumentMarks: React.FC<{ marks: PrintMark[] }> = ({ marks }) => {
  if (marks.length === 0) {
    return null;
  }

  return (
    <div className="pd-marks">
      {marks.map((mark) => (
        <span
          key={mark.code}
          className={`pd-mark pd-mark--${mark.tone ?? 'warn'}${mark.code === 'DRAFT' ? ' pd-mark--draft' : ''}`}
        >
          {mark.label || mark.code}
        </span>
      ))}
    </div>
  );
};

export const DocumentHeader: React.FC<{
  paper: PrintDocumentModel['paper'];
  branding: PrintBranding;
  kindLabel: string;
  status: PrintStatus | null;
  documentKey: string;
  showLogo: boolean;
}> = ({ paper, branding, kindLabel, status, documentKey, showLogo }) => {
  // The browser renders the public URL; the PDFs embed the same asset as a data
  // URI. Same image, one source, no second copy of the letterhead.
  const logo = branding.logoUrl || branding.logoDataUri;

  if (paper === 'a4') {
    return (
      <>
        <table className="pd-header">
          <tbody>
            <tr>
              <td style={{ width: '62%' }}>
                {showLogo && logo ? <img className="pd-logo" src={logo} alt={branding.name} /> : null}
                <div className="pd-org">{branding.name}</div>
                {branding.tagline ? <div className="pd-org-tagline">{branding.tagline}</div> : null}
                <div className="pd-org-lines">
                  {branding.branch ? (
                    <>
                      {branding.branch}
                      <br />
                    </>
                  ) : null}
                  {branding.addressLine ? (
                    <>
                      {branding.addressLine}
                      <br />
                    </>
                  ) : null}
                  {branding.contactLine ? (
                    <>
                      {branding.contactLine}
                      <br />
                    </>
                  ) : null}
                  {branding.registrations.map((registration, index) => (
                    <React.Fragment key={registration}>
                      <span>{registration}</span>
                      {index < branding.registrations.length - 1 ? ' • ' : null}
                    </React.Fragment>
                  ))}
                </div>
              </td>
              <td className="pd-doctype">
                <span className="pd-doctype-label">{kindLabel}</span>
                {status ? <div className={`pd-doctype-status ${toneClass(status.tone)}`}>{status.label}</div> : null}
                <div className="pd-org-lines">{documentKey}</div>
              </td>
            </tr>
          </tbody>
        </table>
        <div className="pd-rule-strong pd-block" />
      </>
    );
  }

  return (
    <>
      <div className="pd-c">
        {showLogo && logo ? <img className="pd-logo" style={{ maxHeight: '12mm' }} src={logo} alt={branding.name} /> : null}
        <div className="pd-b pd-upper">{branding.name}</div>
        {branding.tagline ? <div>{branding.tagline}</div> : null}
        {branding.addressLine ? <div>{branding.addressLine}</div> : null}
        {branding.contactLine ? <div>{branding.contactLine}</div> : null}
        {branding.registrations.map((registration) => (
          <div key={registration}>{registration}</div>
        ))}
        <div className="pd-b pd-upper" style={{ marginTop: '1mm' }}>
          {kindLabel}
        </div>
        {status ? <div className="pd-b">{status.label}</div> : null}
      </div>
      <div className="pd-rule-dashed" />
    </>
  );
};

export const DocumentHero: React.FC<{ tokenNumber?: string; roomLabel?: string }> = ({ tokenNumber, roomLabel }) => {
  if (!tokenNumber) {
    return null;
  }

  return (
    <div className="pd-hero pd-keep">
      <div className="pd-hero-label">Queue token number</div>
      <div className="pd-hero-value">{tokenNumber}</div>
      {roomLabel ? <div className="pd-hero-room">{roomLabel}</div> : null}
    </div>
  );
};

export const DocumentMeta: React.FC<{ rows: PrintLabeledValue[] }> = ({ rows }) => {
  if (rows.length === 0) {
    return null;
  }

  return (
    <table className="pd-meta pd-block">
      <tbody>
        {rows.map((row) => (
          <tr className="pd-meta-row" key={`${row.label}:${row.value}`}>
            <td className="pd-meta-label">{row.label}</td>
            <td className="pd-meta-value">{row.value}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
};

export const DocumentParties: React.FC<{ parties: PrintParty[] }> = ({ parties }) => {
  if (parties.length === 0) {
    return null;
  }

  const blanks = Math.max(0, 2 - parties.length);

  return (
    <table className="pd-parties pd-block">
      <tbody>
        <tr>
          {parties.map((party) => (
            <td key={party.title}>
              <div className="pd-party pd-keep">
                <div className="pd-party-title">{party.title}</div>
                <table>
                  <tbody>
                    {party.rows.map((row) => (
                      <tr className="pd-party-row" key={`${row.label}:${row.value}`}>
                        <td className="pd-party-label">{row.label}</td>
                        <td className="pd-party-value">{row.value}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </td>
          ))}
          {Array.from({ length: blanks }).map((_, index) => (
            <td key={`blank-${index}`} />
          ))}
        </tr>
      </tbody>
    </table>
  );
};

export const DocumentNotices: React.FC<{ notices: PrintNotice[] }> = ({ notices }) => (
  <>
    {notices.map((notice) => (
      <div
        className={`pd-notice pd-keep pd-notice--${notice.tone ?? 'muted'}`}
        key={notice.text}
      >
        {notice.text}
      </div>
    ))}
  </>
);

export const DocumentItems: React.FC<{ paper: PrintDocumentModel['paper']; items: PrintItem[] }> = ({ paper, items }) => {
  if (items.length === 0) {
    return null;
  }

  if (paper === 'a4') {
    return (
      <>
        <div className="pd-section pd-keep-next">
          <div className="pd-section-title">Itemised services &amp; charges</div>
        </div>
        <table className="pd-table">
          <thead>
            <tr>
              <th className="pd-th pd-c" style={{ width: '9mm' }}>
                #
              </th>
              <th className="pd-th">Service / procedure / consumable</th>
              <th className="pd-th pd-c" style={{ width: '14mm' }}>
                Qty
              </th>
              <th className="pd-th pd-r" style={{ width: '30mm' }}>
                Unit rate
              </th>
              <th className="pd-th pd-r" style={{ width: '26mm' }}>
                Discount
              </th>
              <th className="pd-th pd-r" style={{ width: '32mm' }}>
                Amount
              </th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={`${item.index}-${item.description}`}>
                <td className="pd-td pd-c">{item.index}</td>
                <td className="pd-td">
                  <div className="pd-b">{item.description}</div>
                  {item.sub ? <div className="pd-items-sub">{item.sub}</div> : null}
                </td>
                <td className="pd-td pd-c pd-nowrap">{item.quantity}</td>
                <td className="pd-td pd-r pd-nowrap">{item.unitPrice}</td>
                <td className="pd-td pd-r pd-nowrap pd-tone-success">{item.discount ?? '—'}</td>
                <td className="pd-td pd-r pd-nowrap pd-b">{item.lineTotal}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </>
    );
  }

  // 80 mm: a desktop table would wrap every row into noise, so each item is
  // stacked — description, then the amount, then quantity/rate only when it
  // carries information.
  return (
    <>
      <div className="pd-rule-dashed" />
      {items.map((item) => (
        <table className="pd-table pd-keep" key={`${item.index}-${item.description}`}>
          <tbody>
            <tr>
              <td className="pd-label-value">{item.description}</td>
              <td className="pd-r pd-nowrap pd-b">{item.lineTotal}</td>
            </tr>
            {item.quantity && item.quantity !== '1' ? (
              <tr>
                <td className="pd-items-sub">
                  {item.quantity} × {item.unitPrice ?? ''}
                </td>
                <td className="pd-r pd-items-sub">{item.discount ?? ''}</td>
              </tr>
            ) : null}
          </tbody>
        </table>
      ))}
      <div className="pd-rule-dashed" />
    </>
  );
};

export const DocumentObservations: React.FC<{ observations: PrintObservation[] }> = ({ observations }) => {
  if (observations.length === 0) {
    return null;
  }

  return (
    <>
      <div className="pd-section pd-keep-next">
        <div className="pd-section-title">Structured measurements &amp; observations</div>
      </div>
      <table className="pd-table">
        <tbody>
          {observations.map((row) => (
            <tr className="pd-obs-row" key={`${row.label}:${row.value}`}>
              <td className="pd-obs-label">{row.label}</td>
              <td className="pd-b">{row.value}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
};

export const DocumentSections: React.FC<{ sections: PrintSection[] }> = ({ sections }) => (
  <>
    {sections.map((section, index) => {
      const body = (section.body ?? '').trim();
      const previousGroup = index > 0 ? sections[index - 1]?.group : undefined;
      const showGroup = section.group !== undefined && section.group !== previousGroup;

      if (body === '' && !showGroup) {
        return null;
      }

      return (
        <React.Fragment key={`${section.group ?? ''}-${section.label}-${index}`}>
          {showGroup ? (
            <div className="pd-section">
              <div className="pd-section-group">{section.group}</div>
            </div>
          ) : null}
          {body !== '' ? (
            <div className={`pd-section${section.emphasis ? ' pd-section--emphasis' : ''}`}>
              <div className="pd-section-title pd-keep-next">{section.label}</div>
              <div className="pd-section-body">{body}</div>
            </div>
          ) : null}
        </React.Fragment>
      );
    })}
  </>
);

export const DocumentCriticalLog: React.FC<{ heading?: string; logs: PrintCriticalLog[] }> = ({ heading, logs }) => {
  if (logs.length === 0) {
    return null;
  }

  return (
    <>
      <div className="pd-section pd-keep-next">
        <div className="pd-section-title">{heading ?? 'Critical Result Communication Record'}</div>
      </div>
      <table className="pd-table">
        <thead>
          <tr>
            <th className="pd-th">Communicated to</th>
            <th className="pd-th">Method</th>
            <th className="pd-th">Read-back</th>
            <th className="pd-th">When</th>
          </tr>
        </thead>
        <tbody>
          {logs.map((log) => (
            <tr key={`${log.notifiedTo}-${log.when}`}>
              <td className="pd-td">{log.notifiedTo}</td>
              <td className="pd-td">{log.method}</td>
              <td className="pd-td">{log.readBack}</td>
              <td className="pd-td pd-nowrap">{log.when}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
};

export const DocumentRows: React.FC<{ columns: PrintColumn[]; rows: Array<Record<string, string>> }> = ({
  columns,
  rows,
}) => {
  if (columns.length === 0) {
    return null;
  }

  return (
    <table className="pd-table pd-block">
      <thead>
        <tr>
          {columns.map((column) => (
            <th
              key={column.key}
              className={`pd-th${column.align === 'right' ? ' pd-r' : column.align === 'center' ? ' pd-c' : ''}`}
              style={{ width: column.width ?? 'auto' }}
            >
              {column.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.length === 0 ? (
          <tr>
            <td className="pd-td pd-row-empty" colSpan={columns.length}>
              No records for this document.
            </td>
          </tr>
        ) : (
          rows.map((row, index) => {
            const previousGroup = index > 0 ? rows[index - 1]?.group : undefined;
            const group = row.group;

            return (
              <React.Fragment key={`row-${index}`}>
                {group !== undefined && group !== previousGroup ? (
                  <tr className="pd-group-row">
                    <td colSpan={columns.length}>{group}</td>
                  </tr>
                ) : null}
                <tr>
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={`pd-td${column.align === 'right' ? ' pd-r pd-nowrap' : column.align === 'center' ? ' pd-c' : ''}`}
                    >
                      {row[column.key] ?? ''}
                    </td>
                  ))}
                </tr>
              </React.Fragment>
            );
          })
        )}
      </tbody>
    </table>
  );
};

export const DocumentTotals: React.FC<{ paper: PrintDocumentModel['paper']; totals: PrintTotal[] }> = ({ paper, totals }) => {
  if (totals.length === 0) {
    return null;
  }

  const rows = totals.map((row, index) => (
    <tr className={`pd-total-row${row.emphasis ? ' pd-total-row--emphasis' : ''}`} key={`${row.label}-${index}`}>
      <td className={`pd-total-label ${toneClass(row.tone)}`}>{row.label}</td>
      <td className={`pd-total-value ${toneClass(row.tone)}`}>{row.value}</td>
    </tr>
  ));

  if (paper === 'a4') {
    return (
      <table className="pd-totals pd-keep">
        <tbody>
          <tr>
            <td style={{ width: '55%' }} />
            <td style={{ width: '45%' }}>
              <table className="pd-table">
                <tbody>{rows}</tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    );
  }

  return (
    <>
      <div className="pd-rule-dashed" />
      <table className="pd-table pd-keep">
        <tbody>{rows}</tbody>
      </table>
      <div className="pd-rule-dashed" />
    </>
  );
};

export const DocumentPayments: React.FC<{ paper: PrintDocumentModel['paper']; payments: PrintPayment[] }> = ({
  paper,
  payments,
}) => {
  if (payments.length === 0) {
    return null;
  }

  if (paper === 'a4') {
    return (
      <>
        <div className="pd-section pd-keep-next">
          <div className="pd-section-title">Payment transactions &amp; receipts</div>
        </div>
        <table className="pd-table">
          <thead>
            <tr>
              <th className="pd-th">Received at</th>
              <th className="pd-th">Mode</th>
              <th className="pd-th">Reference</th>
              <th className="pd-th pd-r">Amount</th>
            </tr>
          </thead>
          <tbody>
            {payments.map((payment, index) => (
              <tr key={`${payment.when}-${index}`}>
                <td className="pd-td pd-nowrap">{payment.when}</td>
                <td className="pd-td pd-upper">
                  {payment.isRefund ? `REFUND — ${payment.method}` : payment.method}
                </td>
                <td className="pd-td">{payment.reference}</td>
                <td
                  className={`pd-td pd-r pd-nowrap ${payment.isRefund ? 'pd-tone-critical' : 'pd-tone-success'}`}
                >
                  {payment.amount}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </>
    );
  }

  return (
    <table className="pd-table pd-keep">
      <tbody>
        {payments.map((payment, index) => (
          <React.Fragment key={`${payment.when}-${index}`}>
            <tr>
              <td>{payment.isRefund ? `REFUND ${payment.method}` : payment.method}</td>
              <td className="pd-r pd-nowrap">{payment.amount}</td>
            </tr>
            <tr>
              <td className="pd-items-sub">
                {payment.when}
                {payment.reference && payment.reference !== '—' ? ` • ${payment.reference}` : ''}
              </td>
              <td />
            </tr>
          </React.Fragment>
        ))}
      </tbody>
    </table>
  );
};

export const DocumentSignature: React.FC<{ signature: PrintSignature | null; show: boolean }> = ({ signature, show }) => {
  if (!signature || !show) {
    return null;
  }

  return (
    <table className="pd-signature pd-keep">
      <tbody>
        <tr>
          <td style={{ width: '50%' }}>
            <div className="pd-signature-name">{signature.name}</div>
            <div>{signature.role}</div>
            {signature.lines.map((line) => (line ? <div className="pd-muted" key={line}>{line}</div> : null))}
          </td>
          <td className="pd-r" style={{ width: '50%' }}>
            <div className="pd-signature-line pd-muted">{signature.caption || 'Signature'}</div>
            {signature.statement ? <div className="pd-signature-statement">{signature.statement}</div> : null}
            {signature.reference ? <div className="pd-signature-ref">{signature.reference}</div> : null}
          </td>
        </tr>
      </tbody>
    </table>
  );
};

/**
 * The barcode as INLINE vector markup.
 *
 * The geometry comes from the server (one encoder for every engine), so the
 * symbol on the screen and the symbol in the PDF are the same bars — not two
 * libraries agreeing by luck. Only its own shapes are ever rendered: the server
 * builds this string, and `sanitizeCodeSvg` refuses anything else.
 */
export const DocumentCodes: React.FC<{ codes: PrintCode[]; show: boolean }> = ({ codes, show }) => {
  const safe = useMemo(() => codes.map((code) => ({ ...code, svg: sanitizeCodeSvg(code.svg) })).filter((code) => code.svg !== ''), [codes]);

  if (!show || safe.length === 0) {
    return null;
  }

  return (
    <div className="pd-codes">
      {safe.map((code) => (
        <div className="pd-code pd-keep" key={code.value}>
          <span dangerouslySetInnerHTML={{ __html: code.svg }} />
          <div className="pd-code-value">{code.label || code.value}</div>
        </div>
      ))}
    </div>
  );
};

/**
 * Accept only the shapes our encoder emits. The SVG is server-generated, but it
 * is inserted into the DOM, so it is validated rather than trusted: a tag or
 * attribute outside this whitelist means the symbol is dropped, never executed.
 */
export function sanitizeCodeSvg(svg: string): string {
  if (!svg || !svg.startsWith('<svg')) {
    return '';
  }

  if (/<script|on[a-z]+\s*=|javascript:|<foreignObject|<image/i.test(svg)) {
    return '';
  }

  return /^<svg[\s>][\s\S]*<\/svg>$/.test(svg) ? svg : '';
}

export const DocumentFooter: React.FC<{ notes: string[] }> = ({ notes }) => {
  const visible = notes.filter((note) => note.trim() !== '');

  if (visible.length === 0) {
    return null;
  }

  return (
    <div className="pd-footer">
      {visible.map((note, index) => (
        <div className="pd-footer-note" key={`${index}-${note.slice(0, 16)}`}>
          {note}
        </div>
      ))}
    </div>
  );
};

export const DocumentPageFoot: React.FC<{ paper: PrintDocumentModel['paper']; foot: PrintPageFoot }> = ({ paper, foot }) => {
  if (paper !== 'a4') {
    return null;
  }

  return (
    <div className="pd-pagefoot">
      <span className="pd-pagefoot-cell pd-pagefoot-left">{foot.left}</span>
      <span className="pd-pagefoot-cell pd-pagefoot-center">{foot.center}</span>
      <span className="pd-pagefoot-cell pd-pagefoot-right">{foot.right}</span>
    </div>
  );
};
