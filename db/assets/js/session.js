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
  signInWithPopup,
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
 * Sign in with Google via POPUP. Recommended when authDomain differs from the
 * app's domain (tabib-01.firebaseapp.com vs www.tabib.af): popup keeps the
 * session on the app origin and avoids the cross-site storage that breaks
 * signInWithRedirect (especially in incognito). The COOP header
 * `same-origin-allow-popups` (db/.htaccess) lets the popup complete.
 */
export async function loginWithGoogle(remember = true) {
  await setPersistence(
    auth,
    remember ? browserLocalPersistence : browserSessionPersistence
  );
  const provider = new GoogleAuthProvider();
  provider.setCustomParameters({ prompt: "select_account" });
  await signInWithPopup(auth, provider);
  // onAuthStateChanged refreshes _session with claims.
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
export async function requirePharmacySession({ loginUrl = "./index.html" } = {}) {
  // Wait for Firebase to finish restoring any persisted session. Without this,
  // auth.currentUser is transiently null at page load and we'd bounce to login
  // → login bounces back → redirect loop.
  await auth.authStateReady();

  const user = auth.currentUser;
  if (!user) {
    window.location.replace(loginUrl);
    return new Promise(() => {}); // never resolves; page is navigating away
  }

  // Force-refresh the ID token so a newly-granted custom claim (pharmacyId/role)
  // is picked up without requiring a full sign-out/sign-in.
  const res = await user.getIdTokenResult(true);
  const s = {
    uid: user.uid,
    email: user.email,
    pharmacyId: res.claims.pharmacyId ?? null,
    role: res.claims.role ?? null,
  };
  _session = s;
  return s.pharmacyId ? s : { ...s, unlinked: true };
}
