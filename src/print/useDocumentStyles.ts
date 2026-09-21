import { useLayoutEffect } from 'react';
import type { PrintDocumentModel } from './types';

/**
 * Inject a document's stylesheet + `@page` rule into `<head>`.
 *
 * The CSS arrives INSIDE the payload, rendered by the server from the same blade
 * partial the PDF engines consume. That is the structural reason a preview and a
 * printed page cannot drift: there is no second stylesheet, no build-time class
 * purge to worry about, and nothing for a framework upgrade to change.
 *
 * `@page` must live in the document head — a rule that only exists inside a
 * scrolled container is not reliably applied by the print pipeline.
 */
export function useDocumentStyles(document: PrintDocumentModel | null): void {
  useLayoutEffect(() => {
    if (!document) {
      return;
    }

    const style = window.document.createElement('style');
    style.setAttribute('data-print-document', 'true');
    style.textContent = [
      document.tokensCss,
      document.pageCss,
      document.documentCss,
    ]
      .filter(Boolean)
      .join('\n');

    window.document.head.appendChild(style);

    return () => {
      style.remove();
    };
  }, [document]);
}
