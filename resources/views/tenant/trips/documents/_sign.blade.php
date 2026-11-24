{{-- One representative's box: name (+ phone), then the signature and the stamp snapshotted on the trip. --}}
@php use App\Domain\Trips\Documents\TripDocumentRenderer as R; @endphp
<div class="sign-title">{{ $title }}</div>
<div class="sign-line">Name: <strong>{{ R::text($person['name']) ?: '—' }}</strong></div>
@if (! empty($withPhone))
  <div class="sign-line">Tel. No.: {{ $person['phone'] ?: '—' }}</div>
@endif
<div class="sign-images">
  @if ($person['signature'])<img class="signature" src="{{ $person['signature'] }}" alt="">@endif
  @if ($person['stamp'])<img class="stamp" src="{{ $person['stamp'] }}" alt="">@endif
  @if (! $person['signature'] && ! $person['stamp'])<span class="muted">—</span>@endif
</div>
