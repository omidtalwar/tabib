/**
 * Auth session + claims. The signed-in user's pharmacy and role come from the
 * ID token's custom claims ({ pharmacyId, role }), set server-side by the
 * setPharmacyClaim / createPharmacyStaff Cloud Functions. The URL never grants
 * access — Firestore rules enforce the claim.
 */

import { auth } from "./firebase.js";
import {
  onAuthStateChanged,
  signInWithEmailAndPassword,
  signOut,
  setPersistence,
  browserLocalPersistence,
  browserSessionPersistence,
  sendPasswordResetEmail,
  GoogleAuthProvider,
  signInWithRedirect,
  getRedirectResult,
} from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";

/** @typedef {{ uid:string, email:string|null, pharmacyId:string|null, role:string|null }} Session */

let _session = null;
const _listeners = new Set();

/** Subscribe to session changes. Returns an unsubscribe fn. Fires immediately. */
export function onSession(cb) {
  _listeners.add(cb);
  cb(_session);
  return () => _listeners.delete(cb);
}

export function currentSession() {
  return _session;
}

function emit() {
  for (const cb of _listeners) cb(_session);
}

/** Sign in. `remember` = LOCAL persistence, else SESSION (cleared on tab close). */
export async function login(email, password, remember = true) {
  await setPersistence(
    auth,
    remember ? browserLocalPersistence : browserSessionPersistence
  );
  await signInWithEmailAndPassword(auth, email, password);
  // onAuthStateChanged will refresh _session with claims.
}

/**
 * Sign in with Google via full-page REDIRECT (not popup). Redirect avoids the
 * Cross-Origin-Opener-Policy / window.closed problems that block popups on
 * shared hosting. The page navigates to Google and back; on return,
 * completeGoogleRedirect() finalizes and onAuthStateChanged sets the session.
 */
export async function loginWithGoogle(remember = true) {
  await setPersistence(
    auth,
    remember ? browserLocalPersistence : browserSessionPersistence
  );
  const provider = new GoogleAuthProvider();
  provider.setCustomParameters({ prompt: "select_account" });
  await signInWithRedirect(auth, provider); // navigates away
}

/**
 * Call once on the login page load to complete a returning Google redirect and
 * surface any error. Resolves null when there's no pending redirect.
 */
export function completeGoogleRedirect() {
  return getRedirectResult(auth);
}

export async function logout() {
  await signOut(auth);
}

export function resetPassword(email) {
  return sendPasswordResetEmail(auth, email);
}

/** Force a fresh ID token (after claims change) and re-read the session. */
export async function refreshClaims() {
  if (!auth.currentUser) return null;
  const res = await auth.currentUser.getIdTokenResult(true);
  _session = toSession(auth.currentUser, res.claims);
  emit();
  return _session;
}

function toSession(user, claims) {
  return {
    uid: user.uid,
    email: user.email,
    pharmacyId: claims.pharmacyId ?? null,
    role: claims.role ?? null,
  };
}

// Single source of truth for auth state across the app.
onAuthStateChanged(auth, async (user) => {
  if (!user) {
    _session = null;
    emit();
    return;
  }
  const res = await user.getIdTokenResult();
  _session = toSession(user, res.claims);
  emit();
});

/**
 * Guard for protected pages: resolves to a Session that HAS a pharmacyId, or
 * redirects. Used by app.html before rendering the shell.
 *   - not signed in            -> redirect to login
 *   - signed in, no pharmacyId -> reject ("account not linked to a pharmacy")
 */
export function requirePharmacySession({ loginUrl = "./index.html" } = {}) {
  return new Promise((resolve) => {
    // `unsub` is intentionally declared (and null) before onSession runs:
    // onSession invokes the handler synchronously, so the handler must not
    // touch a not-yet-assigned const. We clean up after onSession returns.
    let unsub = null;
    let settled = false;

    const handle = (s) => {
      if (settled) return;
      // Wait for the first definitive auth resolution.
      if (s === null && auth.currentUser === null) {
        settled = true;
        if (unsub) unsub();
        window.location.replace(loginUrl);
      } else if (s && s.pharmacyId) {
        settled = true;
        if (unsub) unsub();
        resolve(s);
      } else if (s && !s.pharmacyId) {
        settled = true;
        if (unsub) unsub();
        resolve({ ...s, unlinked: true });
      }
    };

    unsub = onSession(handle);
    // If it resolved during the synchronous first call (before `unsub` existed),
    // remove the listener now.
    if (settled && unsub) unsub();
  });
}
