# Tabib Pharmacy — Web Portal

A static browser portal (`tabib.af/db/`) for pharmacy staff to manage drugs,
inventory, POS sales, patients, prescriptions, suppliers and reports — live-synced
with the Flutter app via Firestore. Plain HTML + built Tailwind + vanilla ES
modules + Firebase JS SDK v10. No React/Next/PHP/MySQL. Separate from the
`tabib.af` marketing site and the `mmi/` app.

## Status (what's built vs pending)

**Built — Phase 0 (security) + Phase 1 (shell):**
- `firebase/firestore.rules` — per-pharmacy ownership via `pharmacyId` custom claim.
- `firebase/functions/` — `setPharmacyClaim`, `createPharmacyStaff`, and a
  `migrate-passwords.js` script to kill plaintext passwords.
- `db/` shell — login, authenticated app shell (sidebar + outlet), offline
  banner, online/offline indicator, RTL toggle, hash router, session/claims guard.
- `db/assets/js/repo.js` — generic Firestore CRUD, `onSnapshot` helpers, atomic
  stock/transaction helpers, and Timestamp⇄ISO date coercion.
- All 9 page modules are routed and render their planned UI; the data binding is
  scaffolded.

**Pending — needs `docs/REFERENCE.md` (the real app doc / `*_model.dart`):**
- Phase 2: live dashboard aggregates + collection lists.
- Phase 3: full CRUD, and the **atomic POS** (sale + stock decrement in one
  transaction, with oversell protection).
- Phase 4: reports + CSV/PDF, staff management UI, locale.

The data layer is intentionally field-agnostic so we mirror the app's exact field
names instead of inventing them. See `docs/REFERENCE.md`.

## Firebase config — DONE

Project **tabib-01**. A web app ("Tabib Pharmacy Portal") was registered and its
config is wired into `db/assets/js/firebase.js`; `.firebaserc` default = `tabib-01`.
Still to do on the Google side: restrict the API key by HTTP referrer
(`tabib.af/*`, `www.tabib.af/*`, `localhost/*`) and enable Email/Password sign-in.

## ⚠️ Data shapes differ from the brief — code wins

Reading the app confirmed the real wire format (see `docs/REFERENCE.md`):
- Firestore docs mirror the Isar `toMap()` shape, **dates are ISO strings** (not
  Timestamps), each doc has `firestoreId` + int `id` + `isDirty`, and **sales /
  prescriptions store `itemsJson` as a JSON *string*** (not an array).
- `repo.js` was corrected to write ISO strings + explicit uuid ids accordingly.
- The app writes Firestore via a fire-and-forget queue and **doesn't appear to
  read it back** — so web→app live sync isn't guaranteed by the current app.
  This affects the "appears in the app within seconds" acceptance check and needs
  an app-side decision (see docs/REFERENCE.md §6) before Phase 2/3.

## Build the CSS

```
cd build
npm install
npm run build:css      # → db/assets/css/tailwind.css   (use watch:css while developing)
```

## Deploy

**Portal (static):** upload everything under `db/` to `public_html/db/` on
Namecheap. (Do not upload `build/` or `node_modules/`.)

**Rules + Functions:** from `firebase/`:
```
# set your project id in .firebaserc first (replace REPLACE_WITH_FIREBASE_PROJECT_ID)
cd firebase
firebase deploy --only firestore:rules
cd functions && npm install && cd ..
firebase deploy --only functions
```

## Bootstrap the first admin + migrate passwords

Plaintext `pharmacies/{id}.password` must be converted to real Auth users:
```
cd firebase/functions
export GOOGLE_APPLICATION_CREDENTIALS=/abs/path/serviceAccount.json
node migrate-passwords.js            # dry run — review output
node migrate-passwords.js --commit   # creates Auth users, sets admin claim, nulls password
```
After that, admins can add staff from Settings (calls `createPharmacyStaff`).

## ⚠️ Required app-side change (not built here)

The Flutter app's pharmacy-creation flow must stop writing a plaintext
`password` to Firestore and instead create the admin Auth user + claim (call
`createPharmacyStaff`, or use the Admin SDK). Until the app changes, new
pharmacies created from the app will keep producing plaintext passwords that the
migration script then has to clean up. This is required for the secure flow
end-to-end.

## Acceptance checks (from the brief)

- A signed-in user cannot read another pharmacy's data (rules).
- No plaintext password is read/written by the portal.
- Create on web ↔ appears in app within seconds (and vice versa).
- POS decrements stock atomically; overselling blocked.
- Offline create syncs on reconnect.
- Dates written by web are readable by the app and vice versa.
