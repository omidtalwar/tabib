/** Drugs / Catalog — list, search, filters, create/edit/soft-delete. */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Drugs", {
    responsibility:
      "Catalog of drugs with search (name, generic, brand, barcode), filters (category, controlled, active, low-stock), create/edit, and soft-delete (isActive=false — never hard delete). Status badges: low, out, expiring, expired, controlled.",
    fields: [
      "name", "genericName", "brand", "category", "barcode", "unit", "description",
      "stockQuantity", "reorderThreshold", "unitPrice", "sellingPrice",
      "expiryDate (ISO str)", "batchNumber", "supplierId", "isControlled", "isActive",
      "lastSyncedAt (ISO str)", "firestoreId", "id", "isDirty",
    ],
    operations: [
      "watch('drugs') with where(isActive==true) — real-time list",
      "create / update / softDelete via repo.js",
      "expiryDate written as ISO string (toIso) — app reads DateTime.tryParse",
    ],
  }));
}
