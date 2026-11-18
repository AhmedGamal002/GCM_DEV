/**
 * Try-out page for the reusable components (resources/js/gcm-geo.js, gcm-camera.js, gcm-signature-pad.js).
 * Not used by any real page.
 */
'use strict';

const $ = (id) => document.getElementById(id);

function show(id, on = true) {
  $(id).classList.toggle('d-none', !on);
}

function fail(id, error) {
  $(id).textContent = (error && error.message) || String(error);
  show(id, true);
}

// 1. location
$('demo-geo-btn').addEventListener('click', async function () {
  show('demo-geo-error', false);
  show('demo-geo-result', false);
  this.disabled = true;

  try {
    const here = await window.gcmLocate();
    $('demo-geo-coords').textContent = here.lat + ', ' + here.lng;
    $('demo-geo-accuracy').textContent = here.accuracy;
    $('demo-geo-link').href = here.mapsUrl;
    show('demo-geo-result');
  } catch (error) {
    fail('demo-geo-error', error);
  } finally {
    this.disabled = false;
  }
});

// 2. camera
$('demo-cam-btn').addEventListener('click', async function () {
  show('demo-cam-error', false);
  show('demo-cam-result', false);
  this.disabled = true;

  try {
    const location = $('demo-cam-withloc').checked ? await window.gcmLocate() : null;
    const photo = await window.gcmCamera.capture({ location });
    $('demo-cam-img').src = URL.createObjectURL(photo.blob);
    $('demo-cam-info').textContent =
      photo.width + ' × ' + photo.height + ' · ' + Math.round(photo.blob.size / 1024) + ' KB · ' + photo.file.name;
    show('demo-cam-result');
  } catch (error) {
    if (!error || error.code !== 'cancelled') fail('demo-cam-error', error);
  } finally {
    this.disabled = false;
  }
});

// 3. signature
const pad = window.gcmSignaturePad($('demo-signature'), {
  height: 200,
  onChange: (empty) => {
    $('demo-sig-export').disabled = empty;
  }
});

$('demo-sig-clear').addEventListener('click', () => {
  pad.clear();
  show('demo-sig-result', false);
  show('demo-sig-error', false);
});

$('demo-sig-export').addEventListener('click', async () => {
  show('demo-sig-error', false);

  try {
    const blob = await pad.toBlob();
    $('demo-sig-img').src = URL.createObjectURL(blob);
    $('demo-sig-info').textContent = blob.type + ' · ' + Math.round(blob.size / 1024) + ' KB';
    show('demo-sig-result');
  } catch (error) {
    fail('demo-sig-error', error);
  }
});
