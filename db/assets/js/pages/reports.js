/** Reports — date-range revenue, top drugs, low-stock, expiry; CSV/PDF export. */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Reports", {
    responsibility:
      "Date-range revenue, top drugs, low-stock report, and expiry report. Export CSV (SheetJS, built locally) and PDF (print stylesheet or jsPDF).",
    fields: ["sale.total", "sale.createdAt", "sale.items[]", "drug.stockQuantity", "drug.expiryDate"],
    operations: [
      "readAll('sales', [where(createdAt range)]) — one-shot for reporting",
      "aggregate revenue + units; CSV / PDF export",
    ],
  }));
}
