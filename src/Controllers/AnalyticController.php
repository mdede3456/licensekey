<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Models\Account\Expense;
use App\Models\Admin\Store;
use App\Models\Product\Variation;
use App\Models\Transaction\Sell;
use App\Models\Transaction\ShiftRegister;
use App\Models\Transaction\Transaction;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\Facades\DataTables;

class AnalyticController extends Controller
{

      public function stock(Request $request)
      {
            if (!Auth::user()->can('Peringatan Stock')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            if ($request->ajax()) {
                  $data =  Variation::with("stock", "product", "sales")
                        ->join("stocks as s", "variations.id", "=", "s.variation_id")
                        ->join("products as p", "variations.product_id", "=", "p.id")
                        ->join("stores as st", "s.store_id", "=", "st.id")
                        ->selectRaw("p.name AS product_name, variations.name AS variation_name, s.store_id AS store, s.qty_available AS stok,st.name AS store_name, variations.id as id")
                        ->where(function ($query) use ($request) {
                              return $request->store ? $query->where('s.store_id', $request->store) : '';
                        })->where(function ($query) use ($request) {
                              return $request->name ?  $query->where('p.name', 'like', '%' . $request->name . '%') : '';
                        })->orWhere(function ($query) use ($request) {
                              return $request->name ? $query->where('variations.name', 'like', '%' . $request->name . '%') : '';
                        })->where(function ($query) {
                              return Auth::user()->store_id != 0 ? $query->where('s.store_id', Auth::user()->store_id) : '';
                        })->orderBy("p.name", "asc");

                  return DataTables::of($data)->addColumn('pname', function ($row) {
                        return  $row->product_name . ' - ' . $row->variation_name;
                  })->addColumn('sell_stock', function ($row) {
                        return  $row->sell_stock($row->store);
                  })->addColumn('purchases_stock', function ($row) {
                        return  $row->purchases_stock($row->store);
                  })->addColumn('return_sell_stock', function ($row) {
                        return  $row->return_sell_stock($row->store);
                  })->addColumn('return_purchase_stock', function ($row) {
                        return  $row->return_purchase_stock($row->store);
                  })->addColumn('transfer_stock_out', function ($row) {
                        return  $row->transfer_stock_out($row->store);
                  })->addColumn('transfer_stock_entry', function ($row) {
                        return  $row->transfer_stock_entry($row->store);
                  })->addColumn('total_stock', function ($row) {
                        return  number_format($row->stok);
                  })->addColumn('expire_stock', function ($row) {
                        return  number_format($row->store);
                  })->rawColumns(['sell_stock',  'purchases_stock', 'return_sell_stock', 'return_purchase_stock', 'transfer_stock_out', 'transfer_stock_entry', 'total_stock'])
                        ->make(true);
            }

            return view('vendor.mobile.analytic.stock', ['page' => __('sidebar.all_stock')], compact('store'));
      }

      public function profitExpense(Request $request)
      {

            if (!Auth::user()->can('Profit Loss Report')) {
                  abort(403, 'Unauthorized action.');
            }

            $jumlah = DB::table("transactions as t")
                  ->join('sells as s', 't.id', '=', 's.transaction_id')
                  ->join('sell_purchases as sp', 's.id', '=', 'sp.sell_id')
                  ->join('purchases as pp', 'sp.purchase_id', '=', 'pp.id')
                  ->selectRaw("SUM((s.qty * s.unit_price) - (s.qty * pp.purchase_price)) AS jumlah")
                  ->first();

            $expense = Expense::sum('amount');

            $data = [
                  'total_expense'           => $expense,
                  'total_profit'            => $jumlah->jumlah
            ];

            if ($request->ajax()) {
                  $item['expense']  =  (int)$expense;
                  $item['profit']   = $jumlah->jumlah;
                  return response()->json($item);
            }

            return view("vendor.mobile.analytic.loss_profit", ["page" => "Profit & Pengeluaran"], compact("data"));
      }

      public function transaction(Request $request)
      {
            if (!Auth::user()->can('Daftar Penjualan')) {
                  abort(403, 'Unauthorized action.');
            }

            $data['sell']               = (int)Transaction::where("type", "sell")->where("payment_status", "paid")->sum("final_total");
            $data['sell_return']        = (int)Transaction::where("type", "sales_return")->where("payment_status", "paid")->sum("final_total");
            $data['purchase']           = (int)Transaction::where("type", "purchase")->where("payment_status", "paid")->sum("final_total");
            $data['purchase_return']    = (int)Transaction::where("type", "purchase_return")->sum("final_total");
            $data['adjustment']         = (int)Transaction::where("type", "stock_adjustment")->sum("final_total");
            $data['transfer']           = (int)Transaction::where("type", "stock_transfer")->sum("final_total");

            if ($request->ajax()) {
                  return response()->json($data);
            }

            return view("vendor.mobile.analytic.transaction", ["page" => "Persentase Berdasarkan Transaksi"], compact("data"));
      }

      public function trendProduct(Request $request)
      {
            if (!Auth::user()->can('Peringatan Stock')) {
                  abort(403, 'Unauthorized action.');
            }

            if ($request->ajax()) {
                  $data = Sell::with(['variation', 'product'])
                        ->selectRaw('sum(qty) as quantity, variation_id as variation, store_id as store')
                        ->groupBy('variation', 'store')->limit(5)->get();
                  $label['label'] = array();
                  $selling['selling'] = array();

                  foreach ($data as $d) {
                        $getVariant = Variation::where("id", $d->variation)->first();
                        if ($getVariant != null) {
                              $pname = $getVariant->product->name ?? '';
                              if ($getVariant->name != 'no-name') {
                                    $name = $pname . ' - ' . $getVariant->name;
                              } else {
                                    $name = $pname;
                              }

                              $l['label']    = $name;
                              $label['label'][]          = $l['label'];

                              $i['selling']     = $d->quantity;
                              $selling['selling'][]        = $i['selling'];
                        }
                  }
                  return response()->json([
                        'label'     => $label['label'],
                        'selling'   => $selling['selling']
                  ]);
            }

            $five = Sell::with(['variation', 'product'])->selectRaw('sum(qty) as quantity, variation_id as variation, store_id as store')
                  ->where(function ($query) use ($request) {
                        return $request->start_date ?
                              $query->whereBetween('created_at', [$request->start_date, $request->end_date]) : '';
                  })->where(function ($query) use ($request) {
                        return $request->store ?
                              $query->where('store_id', $request->store) : '';
                  })->groupBy('variation', 'store')
                  ->groupBy('variation')->orderBy('quantity', 'desc')->limit(30)->get();

            $product = array();
            foreach ($five as $f) {
                  $variant = Variation::where("id", $f->variation)->first();
                  if ($variant != null) {
                        $pname = $variant->product->name ?? '';
                        if ($variant->name != 'no-name') {
                              $name = $pname . ' - ' . $variant->name;
                        } else {
                              $name = $pname;
                        }
                        $list = [
                              'name'  => $name,
                              'selling'  => $f->quantity,
                              'unit_price' => $variant->selling_price,
                              'image' => $variant->gambar->path ?? '/uploads/image.jpg',
                        ];
                        array_push($product, $list);
                  }
            }
            return view('vendor.mobile.analytic.top_product', ['page' => 'Top Product'], compact('product'));
      }

      public function forToday()
      {
            
            $store = Store::findOrFail(Session::get('mystore'));
 
            $getShift = ShiftRegister::whereYear("created_at", date('Y'))
                  ->whereMonth("created_at", date('m'))
                  ->whereDay("created_at", date('d'))
                  ->where("status", "open")
                  ->where("store_id", Session::get('mystore'))
                  ->first();

            if ($getShift == null) {
                  return view('vendor.mobile.closed_store', ["page" => "Toko Masih Tutup"]);
            }

            return view('vendor.mobile.analytic.today', ["page" => "Analisis Harian"], compact('getShift'));
      }

      public function closeRegister()
      {
            $getShift = ShiftRegister::whereYear("created_at", date('Y'))
                  ->whereMonth("created_at", date('m'))
                  ->whereDay("created_at", date('d'))
                  ->where("status", "open")
                  ->where("store_id", Session::get('mystore'))
                  ->first();

            $getShift->close_amount = $getShift->cash_in_hand;
            $getShift->status = 'close';
            $getShift->closed_at = date("Y-m-d H:i:s");

            $other = $getShift->sell_bank_transaction + $getShift->sell_other_transaction;
            $getShift->other_amount = $other;
            $getShift->save();
            return redirect()->back()->with(['flash' => "Berhasil menutup toko"]); 
      }
}
