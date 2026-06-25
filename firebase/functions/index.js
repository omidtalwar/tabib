/**
 * Tabib Pharmacy — Cloud Functions (Phase 0 security).
 *
 * Provides the only sanctioned way to grant a staff user access to a pharmacy:
 * a Firebase Auth custom claim { pharmacyId, role }. Firestore rules trust this
 * claim (see ../firestore.rules). Plaintext passwords must NOT live in
 * Firestore — use createPharmacyStaff to mint real Auth users.
 *
 * Callables (Functions v2):
 *   - setPharmacyClaim({ uid, pharmacyId, role })
 *   - createPharmacyStaff({ email, password, pharmacyId, role })
 *
 * Admin guard: the caller must already be an 'admin' of the SAME pharmacyId.
 * The very first admin of a brand-new pharmacy is bootstrapped out-of-band
 * (migrate-passwords.js, or a trusted Admin SDK script) — a client can never
 * escalate itself into a pharmacy it isn't already an admin of.
 */

const { onCall, HttpsError } = require("firebase-functions/v2/https");
const { initializeApp } = require("firebase-admin/app");
const { getAuth } = require("firebase-admin/auth");
const { getFirestore, FieldValue } = require("firebase-admin/firestore");

initializeApp();

const ROLES = ["admin", "staff"];

/**
 * Throw unless the caller may manage `pharmacyId`. Allowed if EITHER:
 *  - their custom claim is admin of exactly this pharmacy, OR
 *  - they are the pharmacy's owner (pharmacies/{id}.ownerUid == uid).
 * The owner check covers owners of multiple pharmacies whose single claim
 * points at a different one (they switch between pharmacies in the portal).
 */
async function assertCallerIsPharmacyAdmin(request, pharmacyId) {
  const uid = request.auth && request.auth.uid;
  const token = request.auth && request.auth.token;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
  if (token.role === "admin" && token.pharmacyId === pharmacyId) return;
  const snap = await getFirestore().doc(`pharmacies/${pharmacyId}`).get();
  if (snap.exists && snap.data().ownerUid === uid) return;
  throw new HttpsError(
    "permission-denied",
    "Only an admin or owner of this pharmacy can manage its staff."
  );
}

function validateRole(role) {
  if (!ROLES.includes(role)) {
    throw new HttpsError(
      "invalid-argument",
      `role must be one of: ${ROLES.join(", ")}`
    );
  }
}

/**
 * setPharmacyClaim — set { pharmacyId, role } on an existing Auth user.
 * Caller must be an admin of the target pharmacyId.
 */
exports.setPharmacyClaim = onCall(async (request) => {
  const { uid, pharmacyId, role } = request.data || {};
  if (!uid || !pharmacyId || !role) {
    throw new HttpsError("invalid-argument", "uid, pharmacyId and role are required.");
  }
  validateRole(role);
  await assertCallerIsPharmacyAdmin(request, pharmacyId);

  await getAuth().setCustomUserClaims(uid, { pharmacyId, role });

  // Mirror the link so the app/portal can list staff without reading Auth.
  await getFirestore()
    .doc(`user_practices/${uid}`)
    .set(
      { pharmacyId, role, updatedAt: FieldValue.serverTimestamp() },
      { merge: true }
    );

  return { ok: true, uid, pharmacyId, role };
});

/**
 * claimOwnedPharmacy — bootstrap: a signed-in user who OWNS a pharmacy
 * (pharmacies/{id}.ownerUid == their uid) grants themselves the admin claim.
 * This is how the first admin gets a claim without any plaintext password —
 * the app calls it right after creating the pharmacy doc.
 */
exports.claimOwnedPharmacy = onCall(async (request) => {
  const uid = request.auth && request.auth.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
  const { pharmacyId } = request.data || {};
  if (!pharmacyId) throw new HttpsError("invalid-argument", "pharmacyId is required.");

  const snap = await getFirestore().doc(`pharmacies/${pharmacyId}`).get();
  if (!snap.exists) throw new HttpsError("not-found", "Pharmacy not found.");
  if (snap.data().ownerUid !== uid) {
    throw new HttpsError("permission-denied", "You don't own this pharmacy.");
  }

  await getAuth().setCustomUserClaims(uid, { pharmacyId, role: "admin" });
  await getFirestore()
    .doc(`user_practices/${uid}`)
    .set({ pharmacyId, role: "admin", updatedAt: FieldValue.serverTimestamp() }, { merge: true });

  return { ok: true, pharmacyId, role: "admin" };
});

/**
 * createPharmacyStaff — create an Auth user AND set its claim in one call.
 * Caller must be an admin of the target pharmacyId.
 */
exports.createPharmacyStaff = onCall(async (request) => {
  const { email, password, pharmacyId, role } = request.data || {};
  if (!email || !password || !pharmacyId || !role) {
    throw new HttpsError(
      "invalid-argument",
      "email, password, pharmacyId and role are required."
    );
  }
  validateRole(role);
  await assertCallerIsPharmacyAdmin(request, pharmacyId);

  let user;
  try {
    user = await getAuth().createUser({ email, password });
  } catch (e) {
    if (e.code === "auth/email-already-exists") {
      throw new HttpsError("already-exists", "That email already has an account.");
    }
    throw new HttpsError("internal", e.message);
  }

  await getAuth().setCustomUserClaims(user.uid, { pharmacyId, role });
  await getFirestore()
    .doc(`user_practices/${user.uid}`)
    .set(
      { pharmacyId, role, email, createdAt: FieldValue.serverTimestamp() },
      { merge: true }
    );

  return { ok: true, uid: user.uid, email, pharmacyId, role };
});
