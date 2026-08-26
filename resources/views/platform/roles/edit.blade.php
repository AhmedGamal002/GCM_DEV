@extends('layouts/layoutMaster')

@section('title', 'Edit Role — Platform')

@section('content')
<h4 class="mb-6">Edit Role — {{ $role->name }}</h4>

@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('platform.roles.update', $role) }}">
      @csrf
      @method('PUT')
      <div class="mb-6">
        <label class="form-label" for="name">Role Name</label>
        <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $role->name) }}" required>
      </div>
      <div class="mb-6">
        <h6 class="mb-4">Permissions</h6>
        @foreach ($permissions as $permission)
          <div class="form-check">
            <input
              class="form-check-input"
              type="checkbox"
              name="permissions[]"
              value="{{ $permission->name }}"
              id="perm-{{ $permission->id }}"
              @checked(in_array($permission->name, $assignedPermissions, true))
            >
            <label class="form-check-label" for="perm-{{ $permission->id }}">{{ $permission->name }}</label>
          </div>
        @endforeach
      </div>
      <button type="submit" class="btn btn-primary me-3">Save</button>
      <a href="{{ route('platform.roles.index') }}" class="btn btn-label-secondary">Cancel</a>
    </form>
  </div>
</div>
@endsection
