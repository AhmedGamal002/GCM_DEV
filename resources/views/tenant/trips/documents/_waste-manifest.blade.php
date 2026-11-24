{{--
  Waste Manifest — M-100001 (FRD V01.14 §1.14 "بيان النفايات (المنافست)"; the paper "Waste Management
  Manifest"): the trip from the waste-transport angle — the client as Generator, GCM as Transporter, the
  receiving facility as Receiver. The receiver's own boxes (discrepancies, owner, designation, sign) stay
  empty, as the FRD says.
--}}
@php use App\Domain\Trips\Documents\TripDocumentRenderer as R; @endphp
@include('tenant.trips.documents._head', ['title' => 'WASTE MANAGEMENT MANIFEST', 'number' => $doc['waste_manifest_number']])

<table class="grid">
  <tr>
    <td class="label">Generator Name</td><td class="value">{{ R::text($doc['company']) }}</td>
    <td class="label">Date</td><td class="value">{{ $doc['date'] }}</td>
  </tr>
  <tr>
    <td class="label">Project Name</td><td class="value">{{ R::text($doc['project']) }}</td>
    <td class="label">Delivery Note No.</td><td class="value">{{ $doc['delivery_note_number'] }}</td>
  </tr>
</table>

@include('tenant.trips.documents._lists', [
  'caption' => 'Type of Waste Collected',
  'captionSpan' => 2,
  'firstRows' => 36,
  'columns' => [
    ['title' => 'Non-Hazardous', 'items' => $doc['services']['non_hazardous']],
    ['title' => 'Hazardous', 'items' => $doc['services']['hazardous']],
    ['title' => 'Quantity', 'items' => $doc['capacities']],
    ['title' => 'Skip Color Code', 'items' => $doc['colors']],
  ],
])

<div style="page-break-inside: avoid">
  <div class="section">GENERATOR</div>
  <div class="certification"><strong>GENERATOR'S CERTIFICATION:</strong> {{ config('trip_documents.generator_certification') }}</div>
  <table class="grid">
    <tr><td class="label">Authorized Representative</td><td>{{ R::text($doc['client']['name']) ?: '—' }}</td></tr>
    <tr>
      <td class="label">Sign / Stamp</td>
      <td>
        <div class="sign-images">
          @if ($doc['client']['signature'])<img class="signature" src="{{ $doc['client']['signature'] }}" alt="">@endif
          @if ($doc['client']['stamp'])<img class="stamp" src="{{ $doc['client']['stamp'] }}" alt="">@endif
        </div>
      </td>
    </tr>
  </table>
</div>

<div style="page-break-inside: avoid">
  <div class="section">TRANSPORTER</div>
  <table class="grid">
    <tr>
      <td class="label">Waste Transporter – Representative</td><td class="value">{{ R::text($doc['gcm']['name']) ?: '—' }}</td>
      <td class="label">Tel. No.</td><td class="value">{{ $doc['gcm']['phone'] ?: '—' }}</td>
    </tr>
    <tr>
      <td class="label">Company Name</td><td class="value">{{ config('trip_documents.company_name') }}</td>
      <td class="label">Date</td><td class="value">{{ $doc['date'] }}</td>
    </tr>
    <tr>
      <td class="label">Vehicle No.</td><td class="value">{{ $doc['vehicle'] }}</td>
      <td class="label">Permit No.</td><td class="value">{{ R::text($doc['permit']) ?: '—' }}</td>
    </tr>
    <tr>
      <td class="label">Sign / Stamp</td>
      <td colspan="3">
        <div class="sign-images">
          @if ($doc['gcm']['signature'])<img class="signature" src="{{ $doc['gcm']['signature'] }}" alt="">@endif
          @if ($doc['gcm']['stamp'])<img class="stamp" src="{{ $doc['gcm']['stamp'] }}" alt="">@endif
        </div>
      </td>
    </tr>
  </table>
</div>

<div style="page-break-inside: avoid">
  <div class="section">RECEIVER — WASTE RECEIVING FACILITY</div>
  <table class="grid">
    <tr>
      <td class="label">Discrepancy Indication Space</td><td colspan="3"><div class="blank"></div></td>
    </tr>
    <tr>
      <td class="label">Facility Owner / Operator</td><td class="value"></td>
      <td class="label">Designation</td><td class="value"></td>
    </tr>
    <tr>
      <td class="label">Facility Name</td><td class="value">{{ R::text($doc['facility']) ?: '—' }}</td>
      <td class="label">Date</td><td class="value">{{ $doc['date'] }}</td>
    </tr>
    <tr>
      <td class="label">Sign / Stamp</td><td colspan="3"><div class="blank"></div></td>
    </tr>
  </table>
</div>
