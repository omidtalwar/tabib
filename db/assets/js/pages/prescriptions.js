/** Prescriptions — list + create; status draft/active/dispensed/cancelled. */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Prescriptions", {
    responsibility:
      "List and create prescriptions. 'Dispense' pre-fills a POS sale.",
    fields: [
      "patientId", "patientName", "doctorName", "doctorPhone",
      "itemsJson (JSON STRING of [{drugId,drugName,dosage,frequency,durationDays,quantity,refillsAllowed,refillsUsed}])",
      "status (pending|dispensed|partially_dispensed|cancelled|expired)",
      "issuedDate (ISO)", "expiryDate (ISO)", "dispensedAt (ISO|null)", "dispensedBy", "notes",
      "firestoreId", "id", "isDirty",
    ],
    operations: [
      "watch('prescriptions') — real-time list",
      "create / update status",
      "dispense → hand cart to Sales (POS)",
    ],
  }));
}
