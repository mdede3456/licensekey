<?php

namespace Mdhpos\Licensekey\Middleware;

use App\Models\Admin\License;
use Closure;
use Illuminate\Http\Request;

class IsLicense
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $getLicense = License::first();
        if($getLicense == null) {
            return $next($request);
        }
        return redirect()->route('login');
    }
}
