@extends('layouts/layoutMaster')

@section('title', 'Tenants — Platform')

@section('content')

@include('_partials.breadcrumb', [
  'homeUrl' => route('platform.dashboard'),
  'breadcrumbs' => [
    ['title' => 'Tenants', 'url' => route('platform.tenants.index')],
    ['title' => 'List'],
  ],
])

<div class="d-flex justify-content-between align-items-center mb-1">
  <h4 class="mb-0">Tenants</h4>
  <a href="{{ route('platform.tenants.create') }}" class="btn btn-primary">
    <i class="ti ti-plus me-1"></i> Add New Tenant
  </a>
</div>
<p class="mb-6">Each tenant is a separate organization with its own isolated users and data.</p>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead class="border-top">
        <tr>
          <th>Name</th>
          <th>Slug</th>
          <th>Domain</th>
          <th>Users</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($tenants as $tenant)
          <tr>
            <td>{{ $tenant->name }}</td>
            <td><code>{{ $tenant->slug }}</code></td>
            <td>{{ $tenant->domain ?: '—' }}</td>
            <td>{{ $tenant->users_count }}</td>
            <td>
              <span class="badge {{ $tenant->status === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">
                {{ ucfirst($tenant->status) }}
              </span>
            </td>
            <td>
              <div class="d-flex align-items-center">
                <a href="{{ route('platform.tenants.edit', $tenant) }}" class="btn btn-icon btn-text-secondary rounded-pill" title="Edit">
                  <i class="ti ti-edit ti-md"></i>
                </a>
                <form method="POST" action="{{ route('platform.tenants.status', $tenant) }}"
                  onsubmit="return confirm('{{ $tenant->status === 'active' ? 'Suspend' : 'Reactivate' }} \'{{ $tenant->name }}\'?');">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="{{ $tenant->status === 'active' ? 'suspended' : 'active' }}">
                  <button type="submit" class="btn btn-icon btn-text-secondary rounded-pill" title="{{ $tenant->status === 'active' ? 'Suspend' : 'Reactivate' }}">
                    <i class="ti {{ $tenant->status === 'active' ? 'ti-player-pause' : 'ti-player-play' }} ti-md"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center py-6">No tenants yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
