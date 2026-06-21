// Auto-detects a Flycatcher web app (via its <meta name="flycatcher"> marker)
// on any page you visit, and tells the extension the site's URL plus the
// logged-in token. The extension then configures itself and shows the same
// URLs and request history — no manual setup. On any non-Flycatcher page this
// does nothing at all (and never reads or sends anything).

if (document.querySelector('meta[name="flycatcher"]')) {
  const report = () => {
    let token = null;
    try {
      token = localStorage.getItem('auth_token'); // the only key we ever read
    } catch (e) {
      /* storage blocked */
    }
    try {
      chrome.runtime.sendMessage({
        type: 'flycatcher',
        origin: location.origin,
        token: token || null,
      });
    } catch (e) {
      /* extension reloading */
    }
  };

  report();                            // on load
  window.addEventListener('focus', report);
  setInterval(report, 3000);           // pick up a login without a refresh
}
