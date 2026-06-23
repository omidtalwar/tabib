/** Inventory — low-stock, out-of-stock, expiry tracker, atomic restock. */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Inventory", {
    responsibility:
      "Low-stock (stockQuantity <= reorderThreshold && isActive), out-of-stock, and an expiry tracker (expiring ≤30 days and already-expired, in separate sections). Quick restock increments stockQuantity atomically and updates batchNumber/expiryDate.",
    fields: ["stockQuantity", "reorderThreshold", "isActive", "expiryDate", "batchNumber"],
    operations: [
      "watch('drugs') filtered client/server-side for each view",
      "adjustStock(drugId, +qty, { batchNumber, expiryDate }) — FieldValue.increment",
    ],
  }));
}
