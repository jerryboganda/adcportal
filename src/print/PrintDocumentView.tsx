import React from 'react';
import {
  DocumentCodes,
  DocumentCriticalLog,
  DocumentFooter,
  DocumentHeader,
  DocumentHero,
  DocumentItems,
  DocumentLabel,
  DocumentMeta,
  DocumentNotices,
  DocumentObservations,
  DocumentPageFoot,
  DocumentParties,
  DocumentPayments,
  DocumentRows,
  DocumentSections,
  DocumentSignature,
  DocumentTotals,
  DocumentMarks,
} from './components';
import type { PrintDocumentModel } from './types';

/**
 * The document composer.
 *
 * One composition per artifact, mirroring `resources/views/print/bodies/*.blade.php`
 * exactly. Composition is per artifact because the ORDER of identity, items and
 * totals is a document decision — a receipt is not an invoice with sections
 * hidden — while every section itself is a shared primitive.
 */
export const PrintDocumentView: React.FC<{ document: PrintDocumentModel }> = ({ document }) => {
  const { paper, options } = document;
  const showLogo = options?.showLogo ?? true;
  const showBarcode = options?.showBarcode ?? true;
  const showSignature = options?.showSignature ?? true;

  const header = (
    <DocumentHeader
      paper={paper}
      branding={document.branding}
      kindLabel={document.kindLabel}
      status={document.status}
      documentKey={document.documentKey}
      showLogo={showLogo}
    />
  );

  return (
    <div className={`pd-doc pd-${paper}`} data-print-artifact={document.artifact}>
      <div className="pd-paper">
        <div className="pd-body">
          {document.artifact === 'invoice' ? (
            <>
              <DocumentMarks marks={document.marks} />
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentParties parties={document.parties} />
              <DocumentNotices notices={document.notices} />
              <DocumentItems paper={paper} items={document.items} />
              <DocumentPayments paper={paper} payments={document.payments} />
              <DocumentTotals paper={paper} totals={document.totals} />
              <DocumentSections sections={document.sections} />
              <DocumentSignature signature={document.signature} show={showSignature} />
              <DocumentCodes codes={document.codes} show={showBarcode} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'receipt' ? (
            <>
              <DocumentMarks marks={document.marks} />
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentItems paper={paper} items={document.items} />
              <DocumentTotals paper={paper} totals={document.totals} />
              <DocumentPayments paper={paper} payments={document.payments} />
              <DocumentNotices notices={document.notices} />
              <DocumentCodes codes={document.codes} show={showBarcode} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'report' ? (
            <>
              <DocumentMarks marks={document.marks} />
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentParties parties={document.parties} />
              <DocumentNotices notices={document.notices} />
              <DocumentObservations observations={document.observations} />
              <DocumentSections sections={document.sections} />
              <DocumentCriticalLog heading={document.criticalLogHeading} logs={document.criticalLogs} />
              <DocumentSignature signature={document.signature} show={showSignature} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'token' ? (
            <>
              {header}
              <DocumentHero tokenNumber={document.tokenNumber} roomLabel={document.roomLabel} />
              <DocumentMeta rows={document.meta} />
              <DocumentNotices notices={document.notices} />
              <DocumentCodes codes={document.codes} show={showBarcode} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'label' ? (
            <DocumentLabel
              branding={document.branding}
              rows={document.meta}
              codes={document.codes}
              show={showBarcode}
            />
          ) : null}

          {document.artifact === 'manifest' ? (
            <>
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentRows columns={document.columns} rows={document.rows} />
              <DocumentSignature signature={document.signature} show={showSignature} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'fee-schedule' ? (
            <>
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentRows columns={document.columns} rows={document.rows} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'doctor-settlement' ? (
            <>
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentRows columns={document.columns} rows={document.rows} />
              <DocumentTotals paper={paper} totals={document.totals} />
              <DocumentSignature signature={document.signature} show={showSignature} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}

          {document.artifact === 'shift-closing' ? (
            <>
              <DocumentMarks marks={document.marks} />
              {header}
              <DocumentMeta rows={document.meta} />
              <DocumentRows columns={document.columns} rows={document.rows} />
              <DocumentTotals paper={paper} totals={document.totals} />
              <DocumentSections sections={document.sections} />
              <DocumentSignature signature={document.signature} show={showSignature} />
              <DocumentFooter notes={document.footerNotes} />
            </>
          ) : null}
        </div>
      </div>
      <DocumentPageFoot paper={paper} foot={document.pageFoot} />
    </div>
  );
};
