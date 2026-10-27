<?php

namespace App\Http\Controllers\language;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
  public function swap(Request $request, $locale)
  {
    if (!in_array($locale, ['en', 'ar'])) {
      abort(400);
    } else {
      $request->session()->put('locale', $locale);
    }
    App::setLocale($locale);

    // back() trusts the Referer header, so a link on another site could bounce the visitor to an arbitrary
    // address after the switch — only go back to a page of this app.
    $previous = url()->previous();

    return redirect()->to(parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : url('/'));
  }
}