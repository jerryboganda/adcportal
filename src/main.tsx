import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import { TvDisplayView } from './components/TvDisplayView';
import './index.css';

// The waiting-room TV kiosk (/tv?key=…) is a public surface: it renders
// OUTSIDE the authenticated app shell entirely — no bootstrap round-trip,
// no login screen, no session handling. routes/web.php already serves the
// SPA for this path, so the branch happens purely at the mount point.
const isTvKiosk = window.location.pathname === '/tv';

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    {isTvKiosk ? <TvDisplayView /> : <App />}
  </React.StrictMode>,
);
