/** Suppliers — CRUD; drugs link via supplierId. */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Suppliers", {
    responsibility:
      "Create/read/update/delete suppliers. Drugs reference a supplier via supplierId.",
    fields: [
      "name", "contactName", "phone", "email", "address",
      "itemsSupplied (array)", "notes", "createdAt (ISO)", "firestoreId", "id", "isDirty",
    ],
    operations: ["watch('suppliers')", "create / update", "link from drugs via supplierId"],
  }));
}
