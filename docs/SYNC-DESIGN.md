# Tabib Pharmacy — Sync, Auth & Rules Design (for approval)

Status: **DRAFT for your decisions.** No Phase 2/3 portal code and no rules
deploy until the marked decisions (D1–D6) are made. Companion to
`docs/REFERENCE.md` (data shapes).

---

## 1. As-is (verified from the app)

**Data flow (pharmacy drugs/sales/patients/prescriptions/suppliers):**
- Stored locally (SharedPreferences JSON, Isar-shaped models).
- Pushed to Firestore by a **fire-and-forget queue** (`SyncService.flushQueue`):
  `set`/`update`/`delete` to `pharmacies/{pharmacyId}/{collection}/{firestoreId}`
  (legacy fallback `pharmacy_{collection}/...` when no pharmacyId).
- Docs = Isar `toMap()`: ISO date strings, `firestoreId` + int `id` + `isDirty`,
  `itemsJson` as a JSON string. (Full shapes: `docs/REFERENCE.md`.)
- **No read-back:** the app loads these collections from local storage, not
  Firestore. (Confirmed: only `entries`/setup reads use Firestore in the feature.)

**Auth / credentials (manage_page.dart pharmacy creation):**
- Creator is an authenticated user (`FirebaseAuth … currentUser.uid` = `ownerUid`).
- Client-side `batch.set` writes:
  - `pharmacies/{pharmacyId}` = `{ pharmacyId, name, password (PLAINTEXT), mode,
    ownerUid, createdAt, updatedAt }`
  - `user_practices/{uid}` = `{ type:'pharmacy', name, pharmacyId, … }`
  - `pharmacy_settings/{uid}` = `{ mode, pharmacyId, pharmacyName,
    password (PLAINTEXT), … }`
- `pharmacyId` cached locally (`pharmacy_id`). The plaintext password was meant
  to be the portal login.

## 2. Problems to resolve

1. **No web→app sync.** Web writes land in Firestore but the app never reads them
   → invisible in the app. Breaks the brief's "appears in the app within seconds."
2. **Whole-doc overwrite.** The app re-`set()`s entire docs from its local copy on
   each sync → silently clobbers web edits (most dangerous for `stockQuantity`).
3. **Plaintext passwords** in two places (`pharmacies/{id}`, `pharmacy_settings/{uid}`).
4. **Rules vs. live app conflict (production risk).** Phase 0 rules set
   `pharmacies/{id}` and `user_practices/{uid}` to `write:false`. The current app
   writes both from the client during pharmacy creation. **Deploying the strict
   rules first breaks pharmacy creation in the shipped app.** Must be sequenced.
5. **Legacy `pharmacy_*` top-level paths** exist for old installs; strict rules
   deny them. Decide migrate vs. ignore.
6. **Auth method mismatch (open):** the portal login uses email/password. Confirm
   the app's Firebase Auth method — if users sign in by phone/Google, they may
   have no email/password credential to reuse for the portal. (D5)

## 3. Proposed target design

### 3.1 Identity & access
- Reuse each owner's **existing Firebase Auth account**; grant the
  `{ pharmacyId, role }` **custom claim** via the existing `setPharmacyClaim` /
  `createPharmacyStaff` functions. Portal authorizes off the claim; rules enforce it.
- **Drop the plaintext `password`** from `pharmacies/{id}` and
  `pharmacy_settings/{uid}` (migration script already drafted:
  `firebase/functions/migrate-passwords.js`).

### 3.2 Read-back (make the app reflect Firestore)  — **D1**
Options:
- **(a) App adds Firestore listeners** per collection
  (`pharmacies/{id}/{col}.snapshots()`), reconciling into the local store. True
  two-way sync; requires app changes.
- **(b) Portal-only store:** treat Firestore as the portal's system of record;
  app stays write-mostly; full app reflection deferred. Fastest; app shows web
  edits only after (a) ships later.
- **(c) Hybrid:** app pulls on launch/refresh (one-shot `get()`) instead of live
  listeners. Cheaper than (a), not real-time.

### 3.3 Conflict policy  — **D2**
- Stop whole-doc overwrite. Recommended: **field-level last-write-wins** keyed by a
  monotonic `updatedAt` (ISO) the writer always sets; on app sync, only push
  locally-changed fields (`isDirty` tracking) and merge, never blind `set()`.
- **Stock is special:** never write an absolute `stockQuantity` from a stale local
  copy. Use `FieldValue.increment` for deltas on both sides (web POS already does).

### 3.4 Rules rollout sequencing  — **D3**
Avoid breaking production:
- **Path A (app-first):** update the app to create pharmacies via a Cloud Function
  (Admin SDK) → then deploy strict rules. Clean end state; needs an app release first.
- **Path B (interim rules):** deploy rules that still allow the **owner** to
  client-write `pharmacies/{id}` (owner-only fields, no `password`) and
  `user_practices/{uid}`, matching today's app; tighten later. Ships portal now
  without an app release; slightly looser interim rules.

### 3.5 Legacy `pharmacy_*` paths  — **D4**
Migrate old top-level docs into `pharmacies/{id}/...` (one-off script) vs. leave
denied. Recommend migrate if any production pharmacies predate the scoped path.

## 4. Decisions

- **D1 — DECIDED: on-launch / refresh pull.** The app does a one-shot Firestore
  `get()` per pharmacy collection on pharmacy-module launch and on manual
  refresh, reconciling into the local store (not real-time).
- **D2 — adopted (recommendation):** field-level last-write-wins keyed by an ISO
  `updatedAt` the writer always sets; stock changes use `FieldValue.increment`,
  never an absolute write from a stale copy. (Confirm if you want different.)
- **D3 — DECIDED: interim owner-write rules.** Implemented in
  `firebase/firestore.rules` (owner-or-claim membership; no plaintext password).
  ⚠️ The app must stop writing `password` BEFORE these rules deploy.
- **D4 — pending:** legacy top-level `pharmacy_*` migrate vs. ignore. Default:
  ignore (denied) unless production pharmacies predate the scoped path.
- **D5 — confirmed:** app uses Firebase Auth **email/password** (also Google +
  anonymous). Portal email/password login works for email/password owners;
  Google/anonymous-only owners need a set-password or Google-on-portal path
  (later enhancement).
- **D6 — DECIDED: I may edit the Flutter app** for the pull (D1), the
  password/claim change, and migration.

## 5. Plan once decided
1. Lock D1–D6; finalize rules for the chosen rollout path (do **not** deploy current
   strict rules until then).
2. Phase 2: read-only dashboard + lists against the real shapes.
3. Phase 3: CRUD + atomic POS (txn: sale write + per-line stock decrement + oversell block).
4. Migration: passwords → Auth/claims; legacy paths if D4 = migrate; app-side change for D3 Path A.
5. Phase 4: reports/exports, staff management, locale.
