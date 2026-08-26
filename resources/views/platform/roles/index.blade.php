@extends('layouts/layoutMaster')

@section('title', 'Roles — Platform')

@section('content')
<h4 class="mb-1">Roles</h4>
<p class="mb-6">Roles are a product-wide concept — shared across every tenant. Only Super Admin can create, edit, or delete them.</p>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-6">
  @foreach ($roles as $role)
    <div class="col-xl-4 col-lg-6 col-md-6">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h6 class="fw-normal mb-0 text-body">{{ $role->users_count }} {{ $role->users_count === 1 ? 'user' : 'users' }}</h6>
          </div>
          <div class="d-flex justify-content-between align-items-end">
            <div class="role-heading">
              <h5 class="mb-1">{{ $role->name }}</h5>
              <a href="{{ route('platform.roles.edit', $role) }}">Edit Role</a>
            </div>
            <form method="POST" action="{{ route('platform.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-icon text-danger" title="Delete role">
                <i class="ti ti-trash"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  @endforeach

  <div class="col-xl-4 col-lg-6 col-md-6">
    <div class="card h-100">
      <div class="card-body d-flex align-items-center justify-content-center">
        <button data-bs-target="#addRoleModal" data-bs-toggle="modal" class="btn btn-sm btn-primary">Add New Role</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <h4 class="mb-4">Add New Role</h4>
        <form method="POST" action="{{ route('platform.roles.store') }}">
          @csrf
          <div class="mb-6">
            <label class="form-label" for="name">Role Name</label>
            <input type="text" id="name" name="name" class="form-control" required autofocus>
          </div>
          <div class="mb-6">
            <h6 class="mb-4">Permissions</h6>
            @foreach ($permissions ?? [] as $permission)
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="perm-{{ $permission->id }}">
                <label class="form-check-label" for="perm-{{ $permission->id }}">{{ $permission->name }}</label>
              </div>
            @endforeach
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-primary me-3">Save</button>
            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
