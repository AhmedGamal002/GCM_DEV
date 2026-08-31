<?php

namespace App\Http\Controllers\Web\Profile;

use App\Http\Controllers\Controller;

class SecuritySettingsController extends Controller
{
  public function index()
  {
    return view('tenant.profile.security');
  }
}
