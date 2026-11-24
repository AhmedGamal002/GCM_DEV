{{-- The header of a generated document: GCM's logo and name (static) + the document's title and number. --}}
@php use App\Support\Brand; @endphp
<table class="doc-head">
  <tr>
    <td style="width: 44pt"><img src="{{ Brand::logoPath() }}" alt=""></td>
    <td>
      <div class="company">{{ config('trip_documents.company_name') }}</div>
      <div class="company-sub">{{ config('trip_documents.footer.address') }}</div>
    </td>
    <td style="width: 52%">
      <div class="doc-title">{{ $title }}</div>
      <div class="doc-number">{{ $number ? 'No. '.$number : '' }}</div>
      <div class="doc-trip">Trip {{ $doc['trip_code'] }}</div>
    </td>
  </tr>
</table>
