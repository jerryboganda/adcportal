import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import { TvDisplayView } from './components/TvDisplayView';
import { PrintDocumentRoute } from './print/PrintDocumentRoute';
import './index.css';

// The waiting-room TV kiosk (/tv?key=…) is a public surface: it renders
// OUTSIDE the authenticated app shell entirely — no bootstrap round-trip,
// no login screen, no session handling. routes/web.php already serves the
// SPA for this path, so the branch happens purely at the mount point.
const isTvKiosk = window.location.pathname === '/tv';

// `/print/{artifact}/{id}` is the chrome-free document surface: it still talks to
// the authenticated print API (the server decides whether this session may see
// the document), but it never renders the application shell — nothing that could
// appear on paper by accident, and a stable URL for print QA.
const isPrintDocument = /^\/print\/[a-z-]+\/[^/]+$/i.test(window.location.pathname);

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    {isTvKiosk ? <TvDisplayView /> : isPrintDocument ? <PrintDocumentRoute /> : <App />}
  </React.StrictMode>,
);
