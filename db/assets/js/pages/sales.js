/**
 * Sales / POS — the safety-critical screen. On confirm, a single Firestore
 * transaction writes the sale AND decrements each drug's stockQuantity, with
 * oversell protection (REFERENCE §5.6 — the app currently does NOT decrement;
 * the web must). Receipt number R-{yyyymmdd}-{seq}; printable receipt.
 */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Sales (POS)", {
    responsibility:
      "Search a drug → add line items (drugId, drugName, qty, unitPrice, subtotal) → cart. Capture subtotal, discount, insuranceCoverage, total, paymentMethod, patientName (optional), staffName (from session). Block selling more than available stock; warn on controlled drugs. Print receipt; reset for next sale.",
    fields: [
      "itemsJson (JSON STRING of [{drugId,drugName,quantity,unitPrice,subtotal}])",
      "subtotal", "discountPercent", "discountAmount", "insuranceCoverage", "total",
      "paymentMethod (cash|card|insurance|credit)", "patientName", "patientId",
      "prescriptionId", "staffName", "receiptNumber", "createdAt (ISO str)",
      "firestoreId", "id", "isDirty",
    ],
    operations: [
      "txn(): read each drug, assert stockQuantity >= quantity, write sale + decrement all stock atomically (app does NOT — §5.6)",
      "receiptNumber RCP-{millis} (match app)",
      "print stylesheet for the receipt",
    ],
  }));
}
