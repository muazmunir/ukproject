<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ShareGTLang
{
    public function handle(Request $request, Closure $next)
    {
        $gtCookie = $request->cookie('googtrans'); // e.g. "/auto/fr"
        $current = 'en';

        if ($gtCookie && preg_match('~^/[^/]+/([^/]+)$~', $gtCookie, $m)) {
            $current = $m[1];
        } else {
            // also honor localStorage mirror via query param fallback if you ever add it
            $current = $request->get('gt_lang', 'en');
        }

        $map = config('google_translate.languages', []);
        $label = $map[$current][0] ?? config('google_translate.default_label', 'English');
        $isRtl = in_array($current, config('google_translate.rtl_codes', []), true);

        View::share('gtCurrent', $current);
        View::share('gtCurrentLabel', $label);
        View::share('gtIsRtl', $isRtl);
        View::share('gtLanguages', $map);

        return $next($request);
    }
}
