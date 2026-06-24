/**
 * Diagnostic: show a user's claim + each owned pharmacy's subcollection counts,
 * so we can see WHERE the app actually wrote data and whether it matches the
 * pharmacyId on the user's claim.
 *
 *   $env:GOOGLE_APPLICATION_CREDENTIALS="C:\path\key.json"
 *   node inspect.js omidtalwar.official@gmail.com
 */
const { initializeApp, applicationDefault } = require("firebase-admin/app");
const { getAuth } = require("firebase-admin/auth");
const { getFirestore } = require("firebase-admin/firestore");

initializeApp({ credential: applicationDefault() });
const auth = getAuth();
const db = getFirestore();

const SUBS = ["drugs", "sales", "patients", "prescriptions", "suppliers"];

async function run() {
  const email = process.argv[2];
  const user = await auth.getUserByEmail(email);
  const rec = await auth.getUser(user.uid);
  console.log("uid:   ", user.uid);
  console.log("claims:", JSON.stringify(rec.customClaims || {}));

  const snap = await db.collection("pharmacies").where("ownerUid", "==", user.uid).get();
  console.log(`\npharmacies owned by this user: ${snap.size}`);
  for (const doc of snap.docs) {
    const counts = {};
    for (const s of SUBS) {
      try {
        const c = await doc.ref.collection(s).count().get();
        counts[s] = c.data().count;
      } catch (e) { counts[s] = `err:${e.code || e.message}`; }
    }
    const claimed = (rec.customClaims || {}).pharmacyId === doc.id ? "  <-- CLAIMED" : "";
    console.log(`- ${doc.id}  "${doc.data().name || ""}"${claimed}`);
    console.log(`    ${JSON.stringify(counts)}`);
  }

  // Also check legacy top-level collections in case sync used the fallback path.
  console.log("\nlegacy top-level pharmacy_* (fallback path):");
  for (const s of SUBS) {
    try {
      const c = await db.collection(`pharmacy_${s}`).count().get();
      if (c.data().count > 0) console.log(`- pharmacy_${s}: ${c.data().count}`);
    } catch (e) { /* ignore */ }
  }
}

run().catch((e) => { console.error("❌", e.message); process.exit(1); });
