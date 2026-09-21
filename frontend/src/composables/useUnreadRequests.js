import { reactive } from 'vue'

// Cap how many read IDs we remember per endpoint so long-lived, high-traffic
// endpoints don't grow this localStorage entry forever.
const MAX_STORED = 300

const storageKey = (token) => `read-ids:${token}`

function loadStored(token) {
  try {
    const raw = localStorage.getItem(storageKey(token))
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function persist(token, readIds) {
  localStorage.setItem(storageKey(token), JSON.stringify([...readIds].slice(-MAX_STORED)))
}

/**
 * Tracks which requests the user has already seen for a given endpoint.
 * The first time an endpoint is opened, whatever requests already exist are
 * treated as read immediately — only requests that arrive afterwards (via
 * live-tail) should ever show up as unread.
 */
export function useUnreadRequests(token) {
  const stored = loadStored(token)
  const readIds = reactive(new Set(stored ?? []))
  let seeded = stored !== null

  function seed(requestIds) {
    if (seeded) return
    seeded = true
    requestIds.forEach((id) => readIds.add(id))
    persist(token, readIds)
  }

  function isUnread(id) {
    return !readIds.has(id)
  }

  function countUnread(requestIds) {
    return requestIds.reduce((n, id) => n + (readIds.has(id) ? 0 : 1), 0)
  }

  function markRead(id) {
    if (readIds.has(id)) return
    readIds.add(id)
    persist(token, readIds)
  }

  function markAllRead(requestIds) {
    requestIds.forEach((id) => readIds.add(id))
    persist(token, readIds)
  }

  return { seed, isUnread, countUnread, markRead, markAllRead }
}
