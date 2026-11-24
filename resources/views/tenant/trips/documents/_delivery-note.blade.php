{{--
  Delivery Note — DN-100001 (FRD V01.14 §1.14 "سند التسليم"; the paper "Service Note").
  Proof the trip was served: the client's representative's name and signature (required) and stamp
  (if any), and GCM's — the hauler's — representative who gave the final approval (د5).
--}}
@php use App\Domain\Trips\Documents\TripDocumentRenderer as R; @endphp
@include('tenant.trips.documents._head', ['title' => 'DELIVERY NOTE', 'number' => $doc['delivery_note_number']])

<table class="grid">
  <tr>
    <td class="label">Contractor Name</td><td class="value">{{ R::text($doc['company']) }}</td>
    <td class="label">Date</td><td class="value">{{ $doc['date'] }}</td>
  </tr>
  <tr>
    <td class="label">Project Name</td><td class="value">{{ R::text($doc['project']) }}</td>
    <td class="label">Waste Manifest No.</td><td class="value">{{ $doc['waste_manifest_number'] ?? '—' }}</td>
  </tr>
  <tr>
    <td class="label">Driver Name</td><td class="value">{{ R::text($doc['driver']) }}</td>
    <td class="label">Vehicle Number</td><td class="value">{{ $doc['vehicle'] }}</td>
  </tr>
</table>

@include('tenant.trips.documents._lists', [
  'caption' => 'Type of Service',
  'captionSpan' => 3,
  'firstRows' => 35,
  'columns' => [
    ['title' => 'Non-Hazardous Waste', 'items' => $doc['services']['non_hazardous']],
    ['title' => 'Hazardous Waste', 'items' => $doc['services']['hazardous']],
    ['title' => 'Supplying', 'items' => $doc['services']['supplying']],
    ['title' => 'Quantity', 'items' => $doc['capacities']],
  ],
])

<div class="section">Remarks</div>
<div class="remarks">
  @forelse (R::lines($doc['remarks']) as $line)
    <div @if (\App\Support\ArabicText::containsArabic($line)) style="text-align: right" @endif>{{ $line }}</div>
  @empty
    <span class="muted">—</span>
  @endforelse
</div>

<table class="signs" style="page-break-inside: avoid">
  <tr>
    <td>@include('tenant.trips.documents._sign', ['title' => 'Hauler Representative', 'person' => $doc['gcm']])</td>
    <td>@include('tenant.trips.documents._sign', ['title' => 'Contractor Representative', 'person' => $doc['client']])</td>
  </tr>
</table>
