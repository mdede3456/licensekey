<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Models\Admin\License;
use App\Models\Admin\Setting;
use App\Models\Admin\Store;
use Illuminate\Support\Facades\Request as FacadesRequest;

trait Keycheck
{

    public function index()
    {
        $setting = Setting::first();
        $data = Store::all();

        if (Auth()->user()->store_id != '0') {
            return redirect()->route('choose.store', Auth()->user()->store_id);
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
        $getLicense = License::first();
        $deviceName = getHostName();
        $domain = substr(FacadesRequest::root(), 7);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://mdhpos.com/api/open/get-credential',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'purchase' => $getLicense->purchase,
                'email' => $getLicense->email,
                'domain' => $domain,
                'device'    => $deviceName
            ),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $hasil = json_decode($response);
        
        if ($hasil->status == 'error') {
            return false;
        }

        if($hasil->status == 'success') {
            return $hasil->token;
        }
    }
}
