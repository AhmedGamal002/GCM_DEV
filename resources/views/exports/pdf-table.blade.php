{{--
  Shared PDF table for every list export. DomPDF's layout cost grows faster
  than the row count for ONE long table (measured: 1200 rows ≈ 15s vs ≈ 6s
  when the same rows are split into page-sized tables), so rows are chunked
  into tables of $perPage rows, one per page. 40 fits an A4 page with a
  safety margin. Light styling on purpose — per-cell borders cost extra
  layout work for no gain.

  Arabic: DomPDF can neither join Arabic letters nor lay out right-to-left,
  so every string goes through ArabicText::forPdf() (joined glyphs, visual
  order) and the font is DejaVu Sans, the bundled one that has Arabic. In the
  Arabic locale the whole table is mirrored — columns run right to left and
  cells align right. Because the text is pre-reversed a cell must never wrap,
  hence white-space: nowrap.

  $title (string), $headers (array), $rows (Collection of arrays of cells)
--}}
@php
  use App\Support\ArabicText;

  $rtl = app()->getLocale() === 'ar';
  $align = $rtl ? 'right' : 'left';
  $pdf = fn ($value) => ArabicText::forPdf(is_null($value) ? '' : (string) $value);
  $headers = array_values($headers);
  if ($rtl) {
    $headers = array_reverse($headers);
  }
  $chunks = $rows->chunk($perPage ?? 40);
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
    h3 { margin: 0 0 6px; text-align: {{ $align }}; }
    table { width: 100%; }
    th { background: #f0f0f0; text-align: {{ $align }}; padding: 3px 4px; white-space: nowrap; }
    td { text-align: {{ $align }}; padding: 3px 4px; white-space: nowrap; }
    tr.z td { background: #f7f7f7; }
  </style>
</head>
<body>
  <h3>{{ $pdf($title) }}</h3>
  @foreach ($chunks as $chunk)
    <table @if (! $loop->last) style="page-break-after: always" @endif>
      <thead>
        <tr>
          @foreach ($headers as $header)
            <th>{{ $pdf($header) }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach ($chunk->values() as $i => $row)
          @php($cells = $rtl ? array_reverse(array_values($row)) : $row)
          <tr @if ($i % 2) class="z" @endif>
            @foreach ($cells as $cell)
              <td>{{ $pdf($cell) }}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
</body>
</html>
