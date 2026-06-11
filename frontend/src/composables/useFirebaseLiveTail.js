/**
 * useFirebaseLiveTail
 *
 * Subscribes to a Firebase Realtime Database path and calls `onNewRequest`
 * whenever the Laravel ingestion service pushes a new-request signal.
 *
 * Architecture:
 *  - Laravel IngestController writes a small summary object to
 *    /endpoints/{endpointId}/latest after each capture.
 *  - This composable listens to that path with onValue().
 *  - On change, it calls `onNewRequest(summary)` so the parent component
 *    can prepend the new row to its list and trigger a full fetch if needed.
 *
 * The full request data always lives in Postgres and is fetched via the
 * Laravel API — Firebase only carries the lightweight trigger signal.
 */

import { ref, watch, onUnmounted } from 'vue'
import { initializeApp, getApps } from 'firebase/app'
import { getDatabase, ref as dbRef, onValue, off } from 'firebase/database'

// ── Firebase config ─────────────────────────────────────────────────────────
// These are safe to expose in frontend code — they identify your project,
// but access is controlled by Firebase security rules (set rules to deny
// all reads/writes except from your backend service account).
const firebaseConfig = {
  apiKey:            import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain:        import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  databaseURL:       import.meta.env.VITE_FIREBASE_DATABASE_URL,
  projectId:         import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket:     import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId:             import.meta.env.VITE_FIREBASE_APP_ID,
}

function getFirebaseDb() {
  const app = getApps().length ? getApps()[0] : initializeApp(firebaseConfig)
  return getDatabase(app)
}

// ── Composable ───────────────────────────────────────────────────────────────

/**
 * @param {() => string | null} getEndpointId  Reactive getter for the internal endpoint UUID.
 *                                              Returns null until the endpoint is loaded.
 * @param {(summary: object) => void} onNewRequest  Called with each new-request signal.
 */
export function useFirebaseLiveTail(getEndpointId, onNewRequest) {
  const connected = ref(false)
  let unsubscribe = null
  let currentRef  = null

  function subscribe(endpointId) {
    if (!endpointId) return
    if (!firebaseConfig.databaseURL) {
      console.warn('[LiveTail] VITE_FIREBASE_DATABASE_URL not set — live updates disabled.')
      return
    }

    const db   = getFirebaseDb()
    currentRef = dbRef(db, `endpoints/${endpointId}/latest`)

    let isFirst = true // skip the initial snapshot (it's stale data, not a new request)

    unsubscribe = onValue(currentRef, (snapshot) => {
      if (isFirst) {
        isFirst = false
        connected.value = true
        return
      }

      const data = snapshot.val()
      if (data) {
        onNewRequest(data)
      }
    }, (error) => {
      console.error('[LiveTail] Firebase error:', error)
      connected.value = false
    })
  }

  function stop() {
    if (currentRef && unsubscribe) {
      off(currentRef)
    }
    connected.value = false
    unsubscribe = null
    currentRef  = null
  }

  // Re-subscribe when the endpointId becomes available (async endpoint load)
  watch(
    getEndpointId,
    (id) => {
      stop()
      if (id) subscribe(id)
    },
    { immediate: true }
  )

  onUnmounted(stop)

  return { connected, stop }
}
