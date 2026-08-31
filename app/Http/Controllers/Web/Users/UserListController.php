<?php

namespace App\Http\Controllers\Web\Users;

use App\Http\Controllers\Controller;

class UserListController extends Controller
{
  public function index()
  {
    return view('tenant.users.list');
  }
}
