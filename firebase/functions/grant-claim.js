/**
 * One-off: grant a user the admin claim for the pharmacy they own.
 *
 * Looks up the user by email, finds the pharmacies/{id} doc where
 * ownerUid == that user, sets the custom claim { pharmacyId, role:'admin' },
 * and writes user_practices/{uid}. Use this to unlock the web portal for an
 * existing owner (pharmacy created by the old app build, no claim yet).
 *
 *   # download a service-account key from Firebase console first
 *   export GOOGLE_APPLICATION_CREDENTIALS=/abs/path/serviceAccount.json   # PowerShell: $env:GOOGLE_APPLICATION_CREDENTIALS="C:\path\key.json"
 *   node grant-claim.js omidtalwar.official@gmail.com
 *
 * After it runs, the user must SIGN OUT and back in (or refresh their token)
 * for the new claim to take effect.
 */

const { initializeApp, applicationDefault } = require("firebase-admin/app");
const { getAuth } = require("firebase-admin/auth");
const { getFirestore, FieldValue } = require("firebase-admin/firestore");

initializeApp({ credential: applicationDefault() });
const auth = getAuth();
const db = getFirestore();

async function run() {
  const email = process.argv[2];
  if (!email) throw new Error("usage: node grant-claim.js <email>");

  const user = await auth.getUserByEmail(email);
  const snap = await db.collection("pharmacies").where("ownerUid", "==", user.uid).get();
  if (snap.empty) {
    throw new Error(`No pharmacy has ownerUid == ${user.uid} (${email}). Create one in the app first.`);
  }

  const doc = snap.docs[0];
  const pharmacyId = doc.id;

  await auth.setCustomUserClaims(user.uid, { pharmacyId, role: "admin" });
  await db.doc(`user_practices/${user.uid}`).set(
    { pharmacyId, role: "admin", email, updatedAt: FieldValue.serverTimestamp() },
    { merge: true }
  );

  console.log(`✅ Granted admin claim to ${email}`);
  console.log(`   uid:        ${user.uid}`);
  console.log(`   pharmacyId: ${pharmacyId} (${doc.data().name || "unnamed"})`);
  if (snap.size > 1) {
    console.log(`   note: ${snap.size} pharmacies owned by this user; used the first.`);
  }
  console.log("\nNow sign out of the portal and sign back in to pick up the claim.");
}

run().catch((e) => { console.error("❌", e.message); process.exit(1); });
