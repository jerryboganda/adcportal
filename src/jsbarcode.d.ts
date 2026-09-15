// Minimal ambient declaration: jsbarcode ships no TypeScript types, and we
// deliberately avoided @types/jsbarcode (it would drag @types/node into the
// tree just for a print-only helper).
declare module 'jsbarcode' {
  interface JsBarcodeOptions {
    format?: string;
    height?: number;
    width?: number;
    displayValue?: boolean;
    fontSize?: number;
    margin?: number;
    lineColor?: string;
    [key: string]: unknown;
  }

  function JsBarcode(element: SVGElement | unknown, value: string, options?: JsBarcodeOptions): void;

  export default JsBarcode;
}
