<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $code = session('locale', config('app.locale'));
        app()->setLocale($code);
        view()->share('htmlDir', in_array($code, ['ar','ur','fa','he']) ? 'rtl' : 'ltr');
        return $next($request);
    }
    
}
