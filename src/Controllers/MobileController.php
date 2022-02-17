<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Models\Account\Expense;
use App\Models\Admin\Setting;
use App\Models\Admin\Store;
use App\Models\Transaction\Transaction;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MobileController extends Controller
{
      public function index()
      {
            $data = [
                  'total_purchase'    => Transaction::where('type', 'purchase')->sum('final_total'),
                  'total_sell'        => Transaction::where('type', 'sell')->sum('final_total'),
                  'total_due'         => Transaction::where('type', 'sell')->where('status', 'due')->sum("final_total"),
                  'total_expense'     => Expense::sum('amount')
            ];
            return view('vendor.mobile.index', ["page" => "My Dashboard"], compact("data"));
      }

      public function analyticSale()
      {
            $data['selling'] = array();
            $selling = Transaction::selectRaw('LEFT(created_at,10) as date, sum(final_total) as total')->where('type', 'sell')->whereYear('created_at', date('Y'))->groupBy('date')->limit(7)->get();
            foreach ($selling as $sell) {
                  $list = [
                        'date'  => $sell->date,
                        'total' => $sell->total
                  ];
                  array_push($data['selling'], $list);
            }

            return response()->json($data);
      }

      public function transaction()
      {
            return view("vendor.mobile.transaction", ["page" => "Laporan Transaksi"]);
      }

      public function analytic()
      {
            return view('vendor.mobile.analytic', ["page" => "Data Analisis Penjualan"]);
      }

      public function settings()
      {
            $store = Store::findOrFail(Session::get('mystore'));
            $setting = Setting::first();
            return view("vendor.mobile.setting", ["page" => 'Pengaturan'], compact("store", "setting"));
      }
}
