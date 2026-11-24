{{--
  The check-box lists of a document, side by side like the paper forms (decision د8: every item, the trip's
  marked, no cap — a long list runs over more pages, each with the header rows).

  $caption: the title over the lists (e.g. "Type of Service"), spanning $captionSpan columns from the left;
  $columns: list of ['title' => string, 'items' => list<['label', 'checked', 'hex'?]>];
  $firstRows: how many rows fit on the document's first page under its header blocks.

  Speed (TripDocumentListPages): DomPDF costs ~10 ms per row here, and more than that on one table spanning
  pages. So a list is one table per page: the first page's rows and the last page's rows are HTML tables
  (the last one followed by the rest of the document), and every page in between is an empty placeholder
  that TripDocumentListPages fills by drawing straight on the canvas — the same look, ~100x cheaper. A list
  of up to $firstRows + one page of rows is pure HTML.
--}}
@php
  use App\Domain\Trips\Documents\TripDocumentListPages as ListPages;
  $rows = max(1, ...array_map(fn ($c) => count($c['items']), $columns));
  $labelWidth = ListPages::labelContentWidth(count($columns)); // pt — the same columns as the drawn pages
  $perPage = ListPages::ROWS_PER_PAGE;

  // [from, to, drawn on the canvas?]
  $chunks = [[0, min($rows, $firstRows), false]];
  if ($rows > $firstRows) {
    $pages = (int) ceil(($rows - $firstRows) / $perPage);
    for ($p = 0; $p < $pages; $p++) {
      $from = $firstRows + $p * $perPage;
      $chunks[] = [$from, min($rows, $from + $perPage), $p < $pages - 1];
    }
  }
@endphp
@foreach ($chunks as $n => [$from, $to, $drawn])
@if ($drawn)
<div class="list-page" data-list="{{ ListPages::register($caption, $captionSpan, $columns, $from, $to) }}" style="page-break-before: always; height: {{ ListPages::placeholderHeight() }}pt"></div>
@else
<table class="lists" @if ($n > 0) style="page-break-before: always" @endif>
  <thead>
    <tr>
      <th colspan="{{ $captionSpan * 2 }}" style="text-align: center">{{ $caption }}{{ $n > 0 ? ' (continued)' : '' }}</th>
      @foreach (array_slice($columns, $captionSpan) as $column)
        <th colspan="2" rowspan="2" style="text-align: center; vertical-align: middle">{{ $column['title'] }}</th>
      @endforeach
    </tr>
    <tr>
      @foreach (array_slice($columns, 0, $captionSpan) as $column)
        <th colspan="2" style="text-align: center">{{ $column['title'] }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @for ($i = $from; $i < $to; $i++)
      <tr>
        @foreach ($columns as $column)
          @php $item = $column['items'][$i] ?? null; @endphp
          @if ($item === null)
            <td class="box empty"></td>
            <td class="empty" style="width: {{ $labelWidth }}pt"></td>
          @else
            <td class="box"><span class="cb {{ $item['checked'] ? 'on' : '' }}">{!! $item['checked'] ? '&#10003;' : '&nbsp;' !!}</span></td>
            <td style="width: {{ $labelWidth }}pt{{ $item['checked'] ? '; font-weight: bold; color: #1b2a5c' : '' }}">@if (array_key_exists('hex', $item))<span class="swatch" style="background: {{ $item['hex'] ?? '#ffffff' }}"></span>@endif{{ ListPages::label($item, count($columns)) }}</td>
          @endif
        @endforeach
      </tr>
    @endfor
  </tbody>
</table>
@endif
@endforeach
