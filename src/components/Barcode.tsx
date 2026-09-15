import React, { useEffect, useRef } from 'react';
import JsBarcode from 'jsbarcode';

/**
 * A REAL scannable Code128 barcode rendered to SVG. Printed wristbands,
 * token slips and receipts carry these so downstream scanners can actually
 * decode them — the previous decorative div-stripes encoded nothing.
 */
export const Barcode: React.FC<{
  value: string;
  height?: number;
  width?: number;
  displayValue?: boolean;
}> = ({ value, height = 42, width = 2, displayValue = true }) => {
  const ref = useRef<SVGSVGElement | null>(null);

  useEffect(() => {
    if (!ref.current) return;
    if (!value || !value.trim()) {
      ref.current.innerHTML = '';
      return;
    }
    try {
      JsBarcode(ref.current, value.trim(), {
        format: 'CODE128',
        height,
        width,
        displayValue,
        fontSize: 13,
        margin: 4,
        lineColor: '#0f172a',
      });
    } catch {
      // Unencodable input: render nothing rather than a fake barcode.
      ref.current.innerHTML = '';
    }
  }, [value, height, width, displayValue]);

  return <svg ref={ref} role="img" aria-label={`Barcode ${value}`} />;
};
