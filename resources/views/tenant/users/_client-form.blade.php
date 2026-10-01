@php($isCreate = ($mode ?? 'create') === 'create')

@unless($isCreate)
  <div class="card mb-6" id="client-user-form-loading">
    <div class="card-body text-center py-6">
      <div class="spinner-border" role="status"></div>
    </div>
  </div>
@endunless

<form id="clientUserForm" class="{{ $isCreate ? '' : 'd-none' }}" enctype="multipart/form-data" novalidate>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Account Details') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="name">{{ __('Full Name') }}</label>
          <input type="text" id="name" name="name" class="form-control" maxlength="255" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="email">{{ __('Email') }}</label>
          <input type="email" id="email" name="email" class="form-control" maxlength="255" dir="ltr" {{ $isCreate ? 'required' : 'disabled' }} />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="phone">{{ __('Mobile Number') }}</label>
          <input type="tel" id="phone" name="phone" class="form-control" maxlength="32" dir="ltr" required />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="photo">{{ __('Photo') }}</label>
          <input type="file" id="photo" name="photo" class="form-control" accept="image/*" />
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <div class="form-password-toggle">
            <label class="form-label" for="password">{{ $isCreate ? __('Password') : __('New Password') }}</label>
            <div class="input-group input-group-merge">
              <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" {{ $isCreate ? 'required' : '' }} />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
            @unless($isCreate)
              <small class="text-muted">{{ __('Leave blank to keep the current password.') }}</small>
            @endunless
            <div class="text-danger small mt-1 d-none" data-feedback></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-password-toggle">
            <label class="form-label" for="password_confirmation">{{ __('Confirm Password') }}</label>
            <div class="input-group input-group-merge">
              <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" {{ $isCreate ? 'required' : '' }} />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Company & Projects') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="company_id">{{ __('Company') }}</label>
          <select id="company_id" name="company_id" class="select2 form-select" data-placeholder="{{ __('Select company') }}" required>
            <option value=""></option>
          </select>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Job Role') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="role" id="role-manager" value="client_project_manager" checked>
            <label class="form-check-label" for="role-manager">{{ __('Project Manager') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="role" id="role-auditor" value="client_project_auditor">
            <label class="form-check-label" for="role-auditor">{{ __('Project Auditor') }}</label>
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-12">
          <label class="form-label d-block">{{ __('Projects') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="projects_scope" id="scope-all" value="all" checked>
            <label class="form-check-label" for="scope-all">{{ __('All projects') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="projects_scope" id="scope-specific" value="specific">
            <label class="form-check-label" for="scope-specific">{{ __('Specific projects') }}</label>
          </div>
          <div class="form-text">{{ __('"All projects" covers every project of the company, current and future.') }}</div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-12 d-none" id="projects-picker">
          <div class="border rounded p-3" id="projects-list" data-field="project_ids">
            <span class="text-muted">{{ __('Select a company first.') }}</span>
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-block">{{ __('Account Status') }}</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="status-active" value="active" checked>
            <label class="form-check-label" for="status-active">{{ __('Active') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="status-vacation" value="on_vacation">
            <label class="form-check-label" for="status-vacation">{{ __('On Vacation') }}</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="status-deactivated" value="deactivated">
            <label class="form-check-label" for="status-deactivated">{{ __('Deactivated') }}</label>
          </div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Signature & Stamp') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-md-6">
          <label class="form-label" for="signature">{{ __('Signature image') }}</label>
          <input type="file" id="signature" name="signature" class="form-control" accept="image/*" />
          <div class="small mt-1 d-none" id="current-signature"></div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="stamp">{{ __('Operational stamp image') }}</label>
          <input type="file" id="stamp" name="stamp" class="form-control" accept="image/*" />
          <div class="small mt-1 d-none" id="current-stamp"></div>
          <div class="text-danger small mt-1 d-none" data-feedback></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-6">
    <h5 class="card-header">{{ __('Additional Data') }}</h5>
    <div class="card-body">
      <div class="row g-6">
        <div class="col-12">
          <div class="comment-editor border" id="additional-data-editor"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pt-2">
    <button type="submit" class="btn btn-primary me-4">{{ __('Submit') }}</button>
    <a href="{{ route('app-user-list') }}" id="client-user-form-cancel" class="btn btn-label-secondary">{{ __('Back to list') }}</a>
  </div>
</form>
