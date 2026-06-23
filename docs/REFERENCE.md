# docs/REFERENCE.md — Tabib Pharmacy data reference (extracted from the app)

Source of truth: the Flutter app at
`C:\Users\aimal\Documents\Software-Projects\tabib1\tabib1`, specifically
`lib/features/pharmacy/models/isar/*_isar.dart`,
`lib/features/pharmacy/repositories/*_repository.dart`, and
`lib/features/pharmacy/core/sync/sync_service.dart`.

> The portal MUST mirror these shapes. Where the brief and the code disagree,
> **the code wins** (the app is the source of truth).

## 1. How the app actually persists pharmacy data (important)

- Pharmacy data is stored **locally** (SharedPreferences JSON; Isar-shaped
  models). It is pushed to Firestore through a **fire-and-forget sync queue**
  (`SyncService`), which writes `set/update/delete` per queued op.
- **The documents written to Firestore are the Isar `toMap()` output** — NOT the
  `*Model.toFirestore()` shape. The `*Model.fromFirestore` classes exist but are
  inconsistent with what's stored (e.g. they read `items`/`Timestamp`, while the
  queue writes `itemsJson`/ISO strings). **Mirror the Isar `toMap()` shape.**
- **Dates are ISO-8601 strings** (`DateTime.toIso8601String()`), not Firestore
  `Timestamp`s. Read with `toDate()` (handles both defensively); **write ISO
  strings** with `toIso()`. (`repo.js` does this.)
- Each doc has an explicit **string `firestoreId`** (uuid v4) that is also the
  document id, plus a local int **`id`**, plus an **`isDirty`** bool.
- ⚠️ **The app does not appear to READ pharmacy data back from Firestore** — it
  loads from local storage. So web→app live sync is NOT guaranteed by the
  current app; see §6.

## 2. Firestore paths (`SyncService.flushQueue`)

- Scoped (current): `pharmacies/{pharmacyId}/{collection}/{firestoreId}`
- Legacy fallback (no pharmacyId set): top-level `pharmacy_{collection}/{firestoreId}`

`{collection}` ∈ `drugs`, `sales`, `patients`, `prescriptions`, `suppliers`.
`pharmacyId` is cached in SharedPreferences key `pharmacy_id`.

The portal targets the **scoped** path only (Firestore rules cover
`pharmacies/{id}/**`; the legacy top-level collections are intentionally denied).

## 3. Collection field shapes (mirror exactly)

### drugs  (`drug_isar.dart`)
`id`(int) · `firestoreId`(str) · `name` · `genericName` · `brand` ·
`category`(default "Other") · `barcode` · `unit`(default "Tablet") ·
`description` · `stockQuantity`(int) · `reorderThreshold`(int, default 10) ·
`unitPrice`(double) · `sellingPrice`(double) · `expiryDate`(ISO str|null) ·
`batchNumber` · `supplierId` · `isControlled`(bool) · `isActive`(bool) ·
`lastSyncedAt`(ISO str|null) · `isDirty`(bool). **No `createdAt`.**

### sales  (`sale_isar.dart`)
`id` · `firestoreId` · `prescriptionId` · `patientId` · `patientName` ·
**`itemsJson`(JSON *string*)** · `subtotal` · `discountPercent` ·
`discountAmount` · `insuranceCoverage` · `total` · `paymentMethod`
('cash'|'card'|'insurance'|'credit') · `staffName` · `createdAt`(ISO str) ·
`receiptNumber` · `isDirty`.
`itemsJson` decodes to a list of: `{ drugId, drugName, quantity(int),
unitPrice(double), subtotal(double) }`.
Receipt format used by the app: `RCP-{millisSinceEpoch}`.

### patients  (`patient_isar.dart`)
`id` · `firestoreId` · `fullName` · `phone` · `dateOfBirth`(ISO str|null) ·
`gender`(default "Other") · `address` · `allergies`(array of strings) ·
`bloodGroup` · `emergencyContact` · `insuranceId` · `notes` ·
`createdAt`(ISO str) · `isDirty`.

### prescriptions  (`prescription_isar.dart`)
`id` · `firestoreId` · `patientId` · `patientName` · `doctorName` ·
`doctorPhone` · **`itemsJson`(JSON *string*)** ·
`status` ('pending'|'dispensed'|'partially_dispensed'|'cancelled'|'expired') ·
`issuedDate`(ISO str) · `expiryDate`(ISO str) · `notes` ·
`dispensedAt`(ISO str|null) · `dispensedBy` · `isDirty`.
`itemsJson` decodes to: `{ drugId, drugName, dosage, frequency,
durationDays(int), quantity(int), refillsAllowed(int), refillsUsed(int) }`.

### suppliers  (`supplier_isar.dart`)
`id` · `firestoreId` · `name` · `contactName` · `phone` · `email` ·
`address` · `itemsSupplied`(array of strings) · `notes` ·
`createdAt`(ISO str) · `isDirty`.

## 4. §5.6 — POS does NOT decrement stock

`SaleRepository.addSale` writes the sale and **never touches drug
`stockQuantity`**. The portal POS must decrement stock itself, atomically (sale
write + per-line `stockQuantity` decrement in one transaction), with oversell
protection.

## 5. Soft delete

Only `drugs` carries `isActive`. "Delete" a drug = set `isActive=false`. Other
collections have no soft-delete flag in the model (decide per-collection in
Phase 3 — likely a real delete via the queue's `delete` op, or an added flag).

## 6. Open architectural questions (raised with the user)

1. **Does the app read Firestore back?** If not, web changes won't show in the
   app until the app adds a Firestore listener/pull. This affects the brief's
   acceptance check "create on web appears in app within seconds."
2. **Conflict model:** the app re-`set()`s whole docs from its local copy on
   sync, which can overwrite web edits. A merge/last-write-wins policy needs
   deciding.
3. **Credentials / pharmacyId origin & the plaintext password:** confirm how the
   app establishes `pharmacy_id` and where `pharmacies/{id}.password` is written,
   to finalize the migration + the app-side change (Phase 0 §3.3).

## 7. Firebase project

- Project: **tabib-01** (number 1026232203696). Android pkg `tabib.app.af`.
- Web app "Tabib Pharmacy Portal" registered; config wired in
  `db/assets/js/firebase.js`. `.firebaserc` default = `tabib-01`.
