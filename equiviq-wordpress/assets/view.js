document.addEventListener('DOMContentLoaded', function () {
  var marker = document.querySelector('.eqw-view-marker');
  if (!marker || document.visibilityState !== 'visible' || !window.eqwViewEndpoint) return;
  var headers = {'Content-Type':'application/json'};
  if (window.eqwViewNonce) headers['X-WP-Nonce'] = window.eqwViewNonce;
  fetch(window.eqwViewEndpoint, {
    method: 'POST', credentials: 'same-origin', keepalive: true,
    headers: headers,
    body: JSON.stringify({slug:marker.dataset.slug, page:parseInt(marker.dataset.page,10), event_id:crypto.randomUUID()})
  }).catch(function () {});
});
