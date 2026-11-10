<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $systemLocale = Schema::hasTable('system_settings')
            ? SystemSetting::query()->where('key', 'system.locale')->value('value')
            : null;
        $locale = $request->session()->get('locale', $systemLocale ?? config('app.locale'));

        App::setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
