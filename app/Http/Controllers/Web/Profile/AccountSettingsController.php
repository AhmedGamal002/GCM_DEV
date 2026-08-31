<?php

namespace App\Http\Controllers\Web\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
  public function index(Request $request)
  {
    return view('tenant.profile.account', [
      'user' => $request->user(),
    ]);
  }
}
