{{--
  A trip's generated documents in one PDF (part 8.1) — rendered by TripDocumentRenderer, never directly.

  $pages: list of ['doc' => TripDocumentData::for($trip), 'kinds' => [...]] — one entry per trip (8.1 sends
  one; the sustainability report of 8.5 may send several). Each kind starts on its own page; the footer is
  the same on every page (position: fixed repeats it).

  English documents (FRD); every value typed by a user goes through TripDocumentRenderer::text() / lines()
  for Arabic, and Blade escapes it. DejaVu Sans is the bundled font that has Arabic and the ✓ glyph.
--}}
@php
  use App\Domain\Trips\Documents\TripDocumentRenderer as R;
  $footer = config('trip_documents.footer');
  $first = true;
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: A4 portrait; margin: 26pt 30pt 78pt 30pt; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #1d2433; }
    .page-break { page-break-before: always; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }

    /* Header: logo + company on one side, the document's title and number on the other. */
    table.doc-head { margin-bottom: 8pt; border-bottom: 1.5pt solid #1b2a5c; }
    table.doc-head td { vertical-align: middle; padding-bottom: 6pt; }
    table.doc-head img { width: 36pt; height: 36pt; }
    .company { font-size: 11pt; font-weight: bold; color: #1b2a5c; }
    .company-sub { font-size: 7pt; color: #5b6478; }
    .doc-title { font-size: 13pt; font-weight: bold; color: #1b2a5c; text-align: right; letter-spacing: 0.5pt; }
    .doc-number { font-size: 11pt; font-weight: bold; color: #b3261e; text-align: right; }
    .doc-trip { font-size: 7pt; color: #5b6478; text-align: right; }

    /* Label / value grids. */
    table.grid { margin-bottom: 6pt; }
    table.grid td { border: 0.6pt solid #8592b8; padding: 3pt 5pt; white-space: nowrap; }
    table.grid td.label { background: #e3e8f6; color: #1b2a5c; font-weight: bold; width: 21%; }
    table.grid td.value { width: 29%; }

    /* Section titles. */
    .section { background: #1b2a5c; color: #ffffff; font-weight: bold; padding: 3pt 5pt; font-size: 8pt; letter-spacing: 0.4pt; }

    /* The lists with check boxes — like the paper forms. */
    table.lists { margin-bottom: 6pt; }
    table.lists th { background: #e3e8f6; color: #1b2a5c; border: 0.6pt solid #8592b8; padding: 3pt 4pt; text-align: left; font-size: 7.5pt; }
    table.lists td { border: 0.6pt solid #8592b8; padding: 1.8pt 4pt; font-size: 7.5pt; white-space: nowrap; }
    table.lists td.box { width: 10pt; text-align: center; padding: 1.8pt 2pt; }
    table.lists td.empty { background: #fafbfe; }
    .cb { display: inline-block; width: 7pt; height: 7pt; border: 0.7pt solid #1b2a5c; font-size: 6.5pt; line-height: 7pt; text-align: center; color: #1b2a5c; font-weight: bold; }
    .cb.on { background: #1b2a5c; color: #ffffff; }
    tr.picked td { font-weight: bold; color: #1b2a5c; }
    .swatch { display: inline-block; width: 8pt; height: 6pt; border: 0.5pt solid #555; margin-right: 3pt; }

    /* Notes, signatures. */
    .remarks { border: 0.6pt solid #8592b8; padding: 4pt 5pt; min-height: 22pt; margin-bottom: 6pt; }
    .remarks div { white-space: nowrap; }
    .muted { color: #8a91a5; }
    table.signs td { border: 0.6pt solid #8592b8; padding: 4pt 5pt; width: 50%; }
    .sign-title { font-weight: bold; color: #1b2a5c; margin-bottom: 4pt; }
    .sign-line { margin-bottom: 3pt; white-space: nowrap; }
    .sign-images { height: 48pt; }
    .sign-images img.signature { max-height: 44pt; max-width: 140pt; }
    .sign-images img.stamp { max-height: 48pt; max-width: 70pt; margin-left: 8pt; }
    .blank { height: 22pt; }
    .certification { border: 0.6pt solid #8592b8; border-top: none; padding: 4pt 5pt; font-size: 7.5pt; }

    .receipt { text-align: center; margin-top: 6pt; }

    /* The footer on every page. */
    #footer { position: fixed; bottom: -62pt; left: 0; right: 0; height: 52pt; border-top: 1pt solid #1b2a5c; padding-top: 4pt; text-align: center; font-size: 7pt; color: #3c4459; }
    #footer .f-title { font-weight: bold; color: #1b2a5c; font-size: 7.5pt; }
    #footer div { white-space: nowrap; }
  </style>
</head>
<body>
  <div id="footer">
    <div><span class="f-title">{{ $footer['title'] }}</span> · {{ $footer['issued_by'] }} · {{ $footer['binding'] }}</div>
    {{-- One Arabic line shaped as a whole: shaping the two phrases apart would lay them out left-to-right, i.e. swapped. --}}
    <div>{{ R::text($footer['issued_by_ar'].' · '.$footer['binding_ar']) }}</div>
    <div>{{ $footer['address'] }}</div>
    <div>{{ $footer['contact'] }}</div>
  </div>

  @foreach ($pages as $group)
    @foreach ($group['kinds'] as $kind)
      <div class="{{ $first ? '' : 'page-break' }}">
        @include('tenant.trips.documents._'.$kind, ['doc' => $group['doc']])
      </div>
      @php $first = false; @endphp
    @endforeach
  @endforeach
</body>
</html>
