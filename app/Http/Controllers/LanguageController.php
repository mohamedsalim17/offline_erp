<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    public function switch(string $lang): RedirectResponse
    {
        abort_unless(in_array($lang, ['ar', 'en'], true), 404);

        App::setLocale($lang);
        session(['locale' => $lang]);

        return redirect('/admin');
    }
}
