/**
 * The control next to a stored driver / vehicle attachment (documents and entry permits), on the details
 * and edit pages alike: an eye icon with a "View" tooltip that opens the file in a new tab. The files are
 * PDF / JPG / PNG served inline by the Gate-checked routes, so the browser's own viewer shows them and
 * offers its own download button — there is no separate download button.
 */
'use strict';

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

/** HTML (a link) for the stored file at `url`; callers put it in an `innerHTML`. */
window.gcmViewAttachment = function (url) {
  const label = (window.gcmI18n && window.gcmI18n.view) || 'View';
  const href = String(url).replace(/[&<>"']/g, (c) => ESCAPES[c]);

  return (
    '<a href="' + href + '" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-text-primary rounded-pill"' +
    ' title="' + label + '" aria-label="' + label + '"><i class="ti ti-eye ti-md"></i></a>'
  );
};
