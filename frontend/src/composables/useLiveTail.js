import { ref, onUnmounted } from 'vue'

/**
 * Live-tail by polling — calls `refresh` on a timer so new requests show up
 * without a page reload. No external services, nothing to configure: it just
 * talks to the same API the rest of the app uses.
 *
 * @param {() => Promise<void>} refresh   re-fetches and merges the latest requests
 * @param {{ interval?: number }} options  poll interval in ms (default 4000)
 */
export function useLiveTail(refresh, { interval = 4000 } = {}) {
  const connected = ref(false)
  let timer = null

  async function tick() {
    try {
      await refresh()
      connected.value = true
    } catch (e) {
      connected.value = false
    }
  }

  function start() {
    stop()
    timer = setInterval(tick, interval)
    connected.value = true
  }

  function stop() {
    if (timer) clearInterval(timer)
    timer = null
    connected.value = false
  }

  start()
  onUnmounted(stop)

  return { connected, stop }
}
