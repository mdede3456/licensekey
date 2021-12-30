<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Models\Admin\License;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Facades\Validator;

class LicenseController extends Controller
{
    public function welcome()
    {
        return view('vendor.license.welcome');
    }

    public function validation()
    {

        return view('vendor.license.input');
    }

    public function checkValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase'      => 'required',
            'email'      => 'required|email',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'errors' => $validator->errors(),
                    'message' => 'error'
                ]);
            }
        }

        $deviceName = getHostName();
        $domain = substr(FacadesRequest::root(), 7);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://mdhpos.com/api/open/check-license',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'purchase' => $request->purchase,
                'email' => $request->email,
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
            return response()->json([
                'pesan' => $hasil->message,
                'status' => $hasil->status
            ]);
        } else { 
            $newlicense = new License();
            $newlicense->name = '-';
            $newlicense->customer_id = $hasil->data->transaction_id;
            $newlicense->purchase = $hasil->data->purchase_code;
            $newlicense->email = $request->email;
            $newlicense->type = $hasil->data->use;
            $newlicense->ip_or_domain = $hasil->data->domain_or_ip;
            $newlicense->barcode_code = $hasil->data->barcode_encrypt;
            $newlicense->save();
            return response()->json([
                'pesan' => $hasil->message,
                'status' => $hasil->status
            ]);
        }
    }

    public function updateLicense()
    {
        $license = License::first();
        return view('vendor.license.update',["page" => "Update License"],compact('license'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase'      => 'required',
            'email'      => 'required|email',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'errors' => $validator->errors(),
                    'message' => 'error'
                ]);
            }
        }

        $deviceName = getHostName();
        $domain = substr(FacadesRequest::root(), 7);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'http://mdhpos.com/api/open/check-license',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'purchase' => $request->purchase,
                'email' => $request->email,
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
            return response()->json([
                'pesan' => $hasil->message,
                'status' => $hasil->status
            ]);
        } else { 
            $newlicense = License::first();
            $newlicense->name = '-';
            $newlicense->customer_id = $hasil->data->transaction_id;
            $newlicense->purchase = $hasil->data->purchase_code;
            $newlicense->email = $request->email;
            $newlicense->type = $hasil->data->use;
            $newlicense->ip_or_domain = $hasil->data->domain_or_ip;
            $newlicense->barcode_code = $hasil->data->barcode_encrypt;
            $newlicense->save();
            return response()->json([
                'pesan' => $hasil->message,
                'status' => $hasil->status
            ]);
        }
    }
}
