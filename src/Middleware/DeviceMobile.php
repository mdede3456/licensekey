<?php

namespace Mdhpos\Licensekey\Middleware;

use App\Models\Admin\License;
use App\Models\Admin\Setting;
use Closure;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class DeviceMobile
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
            $settings = Setting::first();
            if ($settings->mobile_version == 'on') {
                  $device = new Agent();
                  if ($device->isMobile()) {
                        return $next($request);
                  } else {
                        return redirect()->route('index');
                  }
            }
            return redirect()->route('index');
      }
}
