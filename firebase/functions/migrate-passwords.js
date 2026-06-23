/**
 * One-time migration: remove plaintext pharmacy passwords, grant the owner's
 * EXISTING Firebase Auth account the admin claim instead.
 *
 * The app creates pharmacies with `ownerUid` = the creating user's Firebase Auth
 * uid (they're already signed in). So we don't create any users or use the
 * plaintext password — we simply:
 *   1. set custom claim { pharmacyId, role:'admin' } on pharmacies/{id}.ownerUid,
 *   2. write user_practices/{ownerUid},
 *   3. delete the plaintext `password` from pharmacies/{id} AND
 *      pharmacy_settings/{ownerUid}.
 *
 * Run with the Admin SDK (service-account key), NOT from the browser:
 *
 *   export GOOGLE_APPLICATION_CREDENTIALS=/abs/path/serviceAccount.json
 *   node migrate-passwords.js              # dry run (prints what it would do)
 *   node migrate-passwords.js --commit     # actually writes
 *
 * Idempotent: re-running skips pharmacies that already have no password.
 */

const { initializeApp, applicationDefault } = require("firebase-admin/app");
const { getAuth } = require("firebase-admin/auth");
const { getFirestore, FieldValue } = require("firebase-admin/firestore");

const COMMIT = process.argv.includes("--commit");

initializeApp({ credential: applicationDefault() });
const auth = getAuth();
const db = getFirestore();

async function run() {
  const snap = await db.collection("pharmacies").get();
  let migrated = 0;
  let skipped = 0;

  for (const doc of snap.docs) {
    const data = doc.data();
    const pharmacyId = doc.id;
    const ownerUid = data.ownerUid;
    const hasPassword = data.password != null;

    if (!hasPassword) { skipped++; continue; }
    if (!ownerUid) {
      console.warn(`! ${pharmacyId}: has a plaintext password but no ownerUid — fix manually.`);
      skipped++;
      continue;
    }

    // Confirm the owner's Auth account exists before claiming.
    let owner;
    try {
      owner = await auth.getUser(ownerUid);
    } catch (e) {
      console.warn(`! ${pharmacyId}: ownerUid ${ownerUid} has no Auth account (${e.code}) — skipping.`);
      skipped++;
      continue;
    }

    console.log(`${COMMIT ? "MIGRATE" : "DRY-RUN"}  ${pharmacyId}  ->  claim admin on ${owner.email || ownerUid}`);
    if (!COMMIT) { migrated++; continue; }

    await auth.setCustomUserClaims(ownerUid, { pharmacyId, role: "admin" });
    await db.doc(`user_practices/${ownerUid}`).set(
      { pharmacyId, role: "admin", updatedAt: FieldValue.serverTimestamp() },
      { merge: true }
    );
    await doc.ref.update({ password: FieldValue.delete() });
    await db.doc(`pharmacy_settings/${ownerUid}`)
      .set({ password: FieldValue.delete() }, { merge: true })
      .catch(() => {}); // settings doc may not exist

    migrated++;
  }

  console.log(`\nDone. ${COMMIT ? "Migrated" : "Would migrate"}: ${migrated}, skipped: ${skipped}.`);
  if (!COMMIT) console.log("Re-run with --commit to apply.");
}

run().catch((e) => { console.error(e); process.exit(1); });
