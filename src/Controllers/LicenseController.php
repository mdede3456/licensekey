<?php

namespace Mdhpos\Licensekey\Controllers; 
use Illuminate\Routing\Controller; 

class LicenseController extends Controller
{
    public function welcome()
    {
         return view('vendor.license.welcome',["page" => "Input License Key"]);
    }

    public static function hai()
    {
        return 'hai';
    }
}
