/**
 * Google Maps embed for a pasted map link (FRD V01.14 §1.12.3: "خريطة جوجل لموقع المشروع").
 *
 * The record stores whatever link the user pasted ("Location" = a Google
 * Maps link). A link cannot be put in an <iframe> as it is, so this reads
 * the place out of it and builds the key-less embed URL
 * (`https://www.google.com/maps?q=…&output=embed`):
 *   1. coordinates, if the link carries any (`@lat,lng`, `?q=lat,lng`,
 *      `ll=`, `!3d…!4d…`, `/place/lat,lng`);
 *   2. otherwise the place text (`?q=Some+Place`, `/maps/place/Some+Place`);
 *   3. otherwise the `fallback` text the page passes (the record's address).
 * A shortened link (maps.app.goo.gl/…) carries none of these and cannot be
 * resolved in the browser, so it falls to the fallback. Returns null when
 * nothing usable is found — the page then keeps just the link.
 *
 * The returned URL is always built here (never the pasted one), so the
 * iframe can only ever point at google.com/maps.
 */
const NUM = '(-?\\d{1,3}(?:\\.\\d+)?)';
const COORD_PATTERNS = [
  new RegExp('@' + NUM + ',' + NUM),
  new RegExp('[?&](?:q|ll|query|destination|center|daddr)=' + NUM + ',\\s*' + NUM),
  new RegExp('!3d' + NUM + '!4d' + NUM),
  new RegExp('/place/' + NUM + ',' + NUM),
  new RegExp('/search/' + NUM + ',\\s*' + NUM)
];

function embed(query) {
  return 'https://www.google.com/maps?q=' + encodeURIComponent(query) + '&z=15&output=embed';
}

function validLatLng(lat, lng) {
  return Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
}

window.gcmMapEmbedUrl = function (link, fallback) {
  let decoded = '';
  if (typeof link === 'string' && /^https?:\/\//i.test(link.trim())) {
    try {
      decoded = decodeURIComponent(link.trim().replace(/\+/g, ' '));
    } catch (e) {
      decoded = link.trim();
    }
  }

  if (decoded) {
    for (const re of COORD_PATTERNS) {
      const m = decoded.match(re);
      if (m && validLatLng(parseFloat(m[1]), parseFloat(m[2]))) {
        return embed(m[1] + ',' + m[2]);
      }
    }

    const q = decoded.match(/[?&](?:q|query)=([^&#]+)/);
    if (q && q[1].trim()) return embed(q[1].trim());

    const place = decoded.match(/\/maps\/place\/([^/@?#]+)/);
    if (place && place[1].trim()) return embed(place[1].trim());
  }

  if (typeof fallback === 'string' && fallback.trim()) return embed(fallback.trim());

  return null;
};
