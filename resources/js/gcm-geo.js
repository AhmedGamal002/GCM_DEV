/**
 * The device's LIVE position and the Google Maps link for it (trips, decision ج12: the driver cannot create a
 * trip without a live location, and the trip shows a "view on the map" link — no Mapbox).
 *
 *   gcmLocate(options?)  → Promise<{ lat, lng, accuracy, takenAt, mapsUrl }>
 *   gcmMapsLink(lat, lng) → 'https://www.google.com/maps/search/?api=1&query=24.713600,46.675300'
 *
 * "Live" means a fresh fix: `maximumAge: 0` so a position the browser cached an hour ago is never returned,
 * and high accuracy (GPS rather than the cell/IP guess). Coordinates are rounded to 6 decimals (~0.1 m).
 *
 * Failures reject with a GcmGeoError whose `code` the page can branch on and whose `message` is already
 * translated (window.gcmI18n.geo_*):
 *   unsupported  the browser has no geolocation
 *   insecure     the page is not HTTPS (browsers refuse geolocation on plain http, except on localhost)
 *   denied       the user (or the browser) refused the permission
 *   unavailable  the device could not work out a position
 *   timeout      no fix within `timeout` ms
 *
 * The coordinates come from the client, so the server must treat them as the driver's claim (they are stored on
 * the trip, not used to authorise anything).
 */
'use strict';

const DEFAULTS = { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 };

function text(key) {
  const i18n = window.gcmI18n || {};

  return i18n[key] || key;
}

class GcmGeoError extends Error {
  constructor(code) {
    super(text('geo_' + code));
    this.name = 'GcmGeoError';
    this.code = code;
  }
}

function round6(n) {
  return Math.round(n * 1e6) / 1e6;
}

function valid(lat, lng) {
  return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
}

window.GcmGeoError = GcmGeoError;

window.gcmMapsLink = function (lat, lng) {
  const a = Number(lat);
  const b = Number(lng);

  if (!valid(a, b)) return null;

  return 'https://www.google.com/maps/search/?api=1&query=' + a.toFixed(6) + ',' + b.toFixed(6);
};

window.gcmLocate = function (options) {
  return new Promise(function (resolve, reject) {
    if (!('geolocation' in navigator)) {
      reject(new GcmGeoError('unsupported'));
      return;
    }

    if (window.isSecureContext === false) {
      reject(new GcmGeoError('insecure'));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      function (position) {
        const lat = round6(position.coords.latitude);
        const lng = round6(position.coords.longitude);

        if (!valid(lat, lng)) {
          reject(new GcmGeoError('unavailable'));
          return;
        }

        resolve({
          lat: lat,
          lng: lng,
          accuracy: Math.round(position.coords.accuracy || 0),
          takenAt: new Date(position.timestamp || Date.now()),
          mapsUrl: window.gcmMapsLink(lat, lng)
        });
      },
      function (error) {
        // GeolocationPositionError: 1 permission denied, 2 position unavailable, 3 timeout.
        reject(new GcmGeoError(error && error.code === 1 ? 'denied' : error && error.code === 3 ? 'timeout' : 'unavailable'));
      },
      Object.assign({}, DEFAULTS, options || {})
    );
  });
};
