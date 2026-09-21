/**
 * Print readiness.
 *
 * `window.print()` on click is how a document reaches paper with a fallback
 * font, a missing logo and a barcode that has not been laid out yet. The print
 * dialog opens only once the document can be painted as designed:
 *
 *   1. the document is in the DOM (React has committed);
 *   2. the fonts it uses are loaded (`document.fonts.ready`, plus an explicit
 *      request for the weights this document uses — a bold signature line may
 *      not have been requested by any other screen yet);
 *   3. every image (tenant logo, embedded vector assets) has decoded;
 *   4. the browser has actually laid out and painted a frame.
 *
 * No `setTimeout(500)`: a delay is a guess that is both too long on a fast desk
 * and too short on a slow one. Every wait here is bounded, so a font CDN outage
 * delays a print by a second or two instead of blocking it forever — and the
 * document still prints, in its fallback font, rather than not at all.
 */

const FONT_TIMEOUT_MS = 2500;
const IMAGE_TIMEOUT_MS = 3000;
const LAYOUT_TIMEOUT_MS = 1500;

/** Families/weights the printed documents actually use. */
const PRINT_FONT_REQUESTS = [
  '400 9pt "Plus Jakarta Sans"',
  '600 9pt "Plus Jakarta Sans"',
  '700 9pt "Plus Jakarta Sans"',
  '800 16pt "Plus Jakarta Sans"',
  '400 8pt "JetBrains Mono"',
  '700 8pt "JetBrains Mono"',
];

function withTimeout<T>(promise: Promise<T>, ms: number, fallback: T): Promise<T> {
  return new Promise<T>((resolve) => {
    const timer = window.setTimeout(() => resolve(fallback), ms);
    promise
      .then((value) => resolve(value))
      .catch(() => resolve(fallback))
      .finally(() => window.clearTimeout(timer));
  });
}

async function fontsReady(): Promise<void> {
  if (typeof document === 'undefined' || !document.fonts) {
    return;
  }

  const loads = PRINT_FONT_REQUESTS.map((font) => document.fonts.load(font).catch(() => undefined));

  await withTimeout(Promise.all(loads).then(() => undefined), FONT_TIMEOUT_MS, undefined);
  await withTimeout(document.fonts.ready.then(() => undefined), FONT_TIMEOUT_MS, undefined);
}

async function imagesReady(root: HTMLElement): Promise<void> {
  const images = Array.from(root.querySelectorAll('img'));

  await withTimeout(
    Promise.all(
      images.map((image) => {
        if (image.complete) {
          return Promise.resolve();
        }

        return new Promise<void>((resolve) => {
          image.addEventListener('load', () => resolve(), { once: true });
          image.addEventListener('error', () => resolve(), { once: true });
        });
      }),
    ).then(() => undefined),
    IMAGE_TIMEOUT_MS,
    undefined,
  );
}

function nextPaint(): Promise<void> {
  return new Promise<void>((resolve) => {
    let done = false;
    const finish = () => {
      if (!done) {
        done = true;
        resolve();
      }
    };

    // Two frames: one to flush layout, one to flush paint. Bounded so a
    // background tab (which pauses rAF) cannot strand a print request.
    requestAnimationFrame(() => requestAnimationFrame(finish));
    window.setTimeout(finish, LAYOUT_TIMEOUT_MS);
  });
}

/**
 * Resolve when the document inside `root` is ready to be put on paper.
 * Never rejects: a document that cannot fully settle still prints.
 */
export async function waitForPrintReady(root: HTMLElement | null): Promise<void> {
  await fontsReady();
  if (root) {
    await imagesReady(root);
  }
  await nextPaint();
}
