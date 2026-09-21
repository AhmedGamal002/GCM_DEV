<?php

namespace App\Http\Controllers\Web\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SecuritySettingsController extends Controller
{
  public function index(Request $request)
  {
    abort_unless($request->user()->canChangeOwnPassword(), 403);

    return view('tenant.profile.security');
  }
}
