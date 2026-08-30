@extends('layouts/layoutMaster')

@section('title', 'Edit Tenant — Platform')

@section('content')
<h4 class="mb-6">Edit Tenant — {{ $tenant->name }}</h4>

@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('platform.tenants.update', $tenant) }}">
      @csrf
      @method('PUT')
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">Name</label>
          <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $tenant->name) }}" required autofocus>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="slug">Slug</label>
          <input type="text" id="slug" name="slug" class="form-control" value="{{ old('slug', $tenant->slug) }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="domain">Custom Domain</label>
          <input type="text" id="domain" name="domain" class="form-control" value="{{ old('domain', $tenant->domain) }}" placeholder="Optional">
        </div>
        <div class="col-md-6">
          <label class="form-label d-block">Status</label>
          <span class="badge {{ $tenant->status === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">
            {{ ucfirst($tenant->status) }}
          </span>
          <small class="text-muted d-block mt-1">Change from the Tenants list.</small>
        </div>
      </div>

      <div class="pt-6">
        <button type="submit" class="btn btn-primary me-4">Save</button>
        <a href="{{ route('platform.tenants.index') }}" class="btn btn-label-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
