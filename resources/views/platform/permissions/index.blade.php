@extends('layouts/layoutMaster')

@section('title', 'Permissions — Platform')

@section('content')
<h4 class="mb-1">Permissions</h4>
<p class="mb-6">Fine-grained permissions, assignable to roles. Only Super Admin can manage them.</p>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card mb-6">
  <div class="card-body">
    <h6 class="mb-4">Add Permission</h6>
    <form method="POST" action="{{ route('platform.permissions.store') }}" class="row g-4 align-items-end">
      @csrf
      <div class="col-md-8">
        <label class="form-label" for="name">Name</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. vehicles.create" required>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table border-top">
      <thead>
        <tr>
          <th>Name</th>
          <th>Roles Using It</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($permissions as $permission)
          <tr>
            <td>{{ $permission->name }}</td>
            <td>{{ $permission->roles_count }}</td>
            <td>{{ $permission->created_at->format('Y-m-d') }}</td>
            <td class="text-end">
              <form method="POST" action="{{ route('platform.permissions.destroy', $permission) }}" onsubmit="return confirm('Delete this permission?');" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon text-danger" title="Delete">
                  <i class="ti ti-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
