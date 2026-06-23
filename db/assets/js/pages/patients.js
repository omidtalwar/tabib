/** Patients — CRUD, search, detail with past sales + prescriptions. */
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet) {
  outlet.append(pendingNotice("Patients", {
    responsibility:
      "Create/read/update/delete patients with search by name/contact. Patient detail shows past sales and prescriptions for that patient.",
    fields: [
      "fullName", "phone", "dateOfBirth (ISO|null)", "gender", "address",
      "allergies (array)", "bloodGroup", "emergencyContact", "insuranceId", "notes",
      "createdAt (ISO)", "firestoreId", "id", "isDirty",
    ],
    operations: [
      "watch('patients') with fullName/phone search",
      "detail: query sales + prescriptions by patientId",
    ],
  }));
}
