<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Models\Admin\License;
use App\Models\Admin\Setting;
use App\Models\Admin\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Jenssegers\Agent\Agent;

trait Keycheck
{

    public function index()
    {
        $setting = Setting::first();
        $data = Store::all();
        $device = new Agent();

        if (Auth()->user()->store_id != '0') {
            return redirect()->route('choose.store', Auth()->user()->store_id);
        }

        if ($device->isMobile()) {
            return view('auth.mobile.store', ["page" => "Pilih Toko"], compact("data", "setting"));
        }

        return view('auth.choose_store', ['page' => __('sidebar.choose_store')], compact('data', 'setting'));
    }

    public static function serverConnection()
    {
        $connected = @fsockopen("www.mdhpos.com", 80);
        if ($connected) {
            $is_conn = true;
            fclose($connected);
        } else {
            $is_conn = false;
        }
        return $is_conn;
    }

    public static function getCredential()
    {
        $getLicense         = License::first();
        $deviceName         = getHostName();
        $domain             = substr(FacadesRequest::root(), 7);

        $response       = Http::withHeaders([
            'Accept' => 'application/json', 
        ])->post('https://mdhpos.com/api/open/get-credential', [
            'purchase'      => $getLicense->purchase,
                'email'     => $getLicense->email,
                'domain'    => $domain,
                'device'    => $deviceName
        ]);

        $hasil = json_decode($response->body());

        if ($hasil->status == 'error') {
            return false;
        }

        if ($hasil->status == 'success') {
            return $hasil->token;
        }
    }
}
