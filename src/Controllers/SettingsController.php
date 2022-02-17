<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Models\Admin\Setting;
use App\Models\Admin\Store;
use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
      public function settShiftRegister($id)
      {
            if (!Auth::user()->can('Update Toko')) {
                  abort(403, 'Unauthorized action.');
            }

            if ($id == 0) {
                  $option = 'no';
            } else {
                  $option = 'active';
            }

            $store = Store::findOrFail(Session::get('mystore'));
            $store->shift_register = $option;
            $store->save();
      }

      public function settMobile(Request $request)
      {
            if (!Auth::user()->can('Setting')) {
                  abort(403, 'Unauthorized action.');
            }

            $setting = Setting::first();
            $setting->mobile_version = 'off';
            $setting->save();
            return redirect()->route("index");
      }

      public function setting()
      {

            if (!Auth::user()->can('Setting')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::findOrFail(Session::get('mystore'));
            return view("vendor.mobile.setting.index", ["page" => "Pengaturan Toko"], compact("store"));
      }

      public function storeSett(Request $request)
      {

            if (!Auth::user()->can('Setting')) {
                  abort(403, 'Unauthorized action.');
            }
            $validator = Validator::make($request->all(), [
                  'name'              => 'required',
                  'email'             => 'required',
                  'phone'             => 'required',
                  'address'           => 'required',
            ]);

            if ($validator->fails()) {
                  if ($request->ajax()) {
                        return response()->json([
                              'errors' => $validator->errors(),
                              'message' => 'error'
                        ]);
                  }
            }

            $data = Store::findOrFail(Session::get('mystore'));
            $data->name         = $request->name;
            $data->email        = $request->email;
            $data->phone        = $request->phone;
            $data->address      = $request->address;
            $request->footer_text ? $data->footer_text = $request->footer_text : null;
            $data->save();
      }

      public function updateProfile(Request $request)
      {
            if ($request->name) {
                  $validator = Validator::make($request->all(), [
                        'name'  => 'required',
                        'email' => 'required|unique:users,email,' . Auth::user()->id,
                        'photo' => 'mimes:jpg,jpeg,png',
                  ]);

                  if ($validator->fails()) {
                        return response()->json([
                              'errors' => $validator->errors(),
                              'message' => 'error'
                        ]);
                  }

                  $data = User::findOrFail(Auth::user()->id);
                  $data->name = $request->name;
                  $data->email = $request->email; 
                  $request->photo ? $data->photo = $this->uploadImage($request, 'photo', 'users') : null;
                  $data->save();
            }
            return view("vendor.mobile.setting.profile", ["page" => "Edit Password"]);
      }

      public function updatePass(Request $request)
      {
            if ($request->password) {
                   
                    if ($request->password != $request->confirm) {
                        return response()->json([
                              'errors' => "Password dan konfirmasi password harus sama",
                              'message' => 'combine'
                        ]);
                    }
            
                    $data = User::findOrFail(Auth::user()->id);
                    $data->password = Hash::make($request->password);
                    $data->save();
            }
            return view("vendor.mobile.setting.password", ["page" => "Edit Password"]);
      }
}
