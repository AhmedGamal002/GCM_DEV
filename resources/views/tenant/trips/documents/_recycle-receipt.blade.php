{{--
  Recycle Receipt (FRD "إيصال التدوير"): the photo uploaded with the trip — proof the waste was handed to the
  disposal facility — on a page of its own, with the receipt's number typed by staff (د1). Only in the
  combined PDF, and only when the trip has the photo (د10 (3)).
--}}
@php
  use App\Domain\Trips\Documents\TripDocumentRenderer as R;
  $size = R::fit($doc['receipt']['image']);
@endphp
@include('tenant.trips.documents._head', ['title' => 'RECYCLE RECEIPT', 'number' => $doc['receipt']['number']])

<table class="grid">
  <tr>
    <td class="label">Contractor Name</td><td class="value">{{ R::text($doc['company']) }}</td>
    <td class="label">Date</td><td class="value">{{ $doc['date'] }}</td>
  </tr>
  <tr>
    <td class="label">Project Name</td><td class="value">{{ R::text($doc['project']) }}</td>
    <td class="label">Facility Name</td><td class="value">{{ R::text($doc['facility']) ?: '—' }}</td>
  </tr>
</table>

<div class="receipt">
  <img src="{{ $doc['receipt']['image']['src'] }}" alt="" style="width: {{ $size['width'] }}pt; height: {{ $size['height'] }}pt">
</div>
