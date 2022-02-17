<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Helper;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategory;
use App\Models\Admin\Store;
use App\Models\Transaction\ShiftRegister;
use App\Models\Transaction\ShiftRegisterTransaction;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
      public function index(Request $request)
      {
            if (!Auth::user()->can('Daftar Laporan Pengeluaran')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
            })->get();

            $category = ExpenseCategory::orderBy('name', 'asc')->get();

            if ($request->ajax()) {
                  $data = Expense::orderBy('id', 'desc')
                        ->where(function ($query) use ($request) {
                              if ($request->end_date && $request->start_date) {
                                    return $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                              }
                              if ($request->date_now) {
                                    return $query->whereDate('created_at', $request->date_now);
                              }
                        })->where(function ($query) use ($request) {
                              return $request->category ? $query->where('category_id', $request->category) : '';
                        })->where(function ($query) use ($request) {
                              return $request->store ? $query->where('store_id', $request->store) : '';
                        })->where(function ($query) use ($request) {
                              return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        })->orderBy("id", 'desc');

                  return DataTables::of($data)
                        ->addColumn(
                              'refund',
                              function ($row) {
                                    $refund = '';
                                    if ($row->refund == 'yes') {
                                          $refund = 'Iya';
                                    } else {
                                          $refund = 'Bukan';
                                    }
                                    return $refund;
                              }
                        )->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->store->name ?? '';
                        })->addColumn('category', function ($row) {
                              return  $row->category->name ?? '';
                        })->editColumn('amount', function ($row) {
                              return number_format($row->amount);
                        })->addColumn(
                              'edit',
                              function ($row) {
                                    $html = '<a href="' . route('m.expense.update', $row->id) . '" style="text-decoration:none;"><span class="badge bg-warning text-white">Edit Data</span></a>';
                                    return $html;
                              }
                        )->addColumn(
                              'delete',
                              function ($row) {
                                    $html = '<a class="deleteExpense" href="' . route('m.expense.delete', $row->id) . '" style="text-decoration:none;"><span class="badge bg-danger text-white">Delete Data</span></a>';
                                    return $html;
                              }
                        )
                        ->rawColumns(['refund', 'mydate', 'my_store', 'category', 'amount', 'edit', 'delete'])
                        ->make(true);
            }
            return view('vendor.mobile.expense.index', ['page' => __('sidebar.expense')], compact('category', 'store'));
      }

      public function create()
      {
            if (!Auth::user()->can('Tambah Pengeluaran')) {
                  abort(403, 'Unauthorized action.');
            }

            $data = ExpenseCategory::where('parent_id', null)->orderBy('name', 'asc')->get();
            return view('vendor.mobile.expense.create', ['page' => __('sidebar.add_expense')], compact('data'));
      }

      public function update($id)
      {
            if (!Auth::user()->can('Update Pengeluaran')) {
                  abort(403, 'Unauthorized action.');
            }
            $data = ExpenseCategory::where('parent_id', null)->orderBy('name', 'asc')->get();
            $expense = Expense::findOrFail($id);
            return view('vendor.mobile.expense.update', ['page' => "Edit Pengeluaran"], compact('data', 'expense'));
      }

      public function store(Request $request, $condition)
      {

            if (!Auth::user()->can('Tambah Pengeluaran')) {
                  abort(403, 'Unauthorized action.');
            }
            
            $validator = Validator::make($request->all(), [
                  'category'      => 'required',
                  'name'      => 'required',
                  'amount'    => 'required',
                  'document'  => 'mimes:jpg,jpeg,png,gif,pdf'
            ]);

            if ($validator->fails()) {
                  if ($request->ajax()) {
                        return response()->json([
                              'errors' => $validator->errors(),
                              'message' => 'error'
                        ]);
                  }
            }

            if ($request->shift_register == 'yes') {
                  $getStore = Store::findOrFail(Session::get('mystore'));
                  $getShift = ShiftRegister::whereYear("created_at", date('Y'))
                        ->whereMonth("created_at", date('m'))
                        ->whereDay("created_at", date('d'))
                        ->where("status", "open")
                        ->where("store_id", Session::get('mystore'))
                        ->first();

                  if ($getStore->shift_register == 'no') {
                        return response()->json([
                              'errors' => "Fitur Shift Register Di toko ini sedang tidak aktif, silahkan aktifkan terlebih dahulu di menu update toko",
                              'message' => 'shift_error'
                        ]);
                  }

                  if ($getShift == null) {
                        return response()->json([
                              'errors' => "Shift Register Tidak Ditemukan, silahkan untuk membuka shift register terlebih dahulu",
                              'message' => 'shift_error'
                        ]);
                  }
            }

            $condition == 'create' ? $data = new Expense : $data = Expense::findOrFail($request->id);
            $data->ref_no = rand();
            $data->store_id = Session::get('mystore');
            $request->shift_register ? $data->shift_register = $request->shift_register : true;
            $request->subcategory ? $data->category_id = $request->subcategory : $data->category_id = $request->category;
            $data->name = $request->name;
            $data->refund = 'no';
            $data->amount = Helper::fresh_aprice($request->amount);
            $request->detail ? $data->detail = $request->detail : null;
            $request->document ? $data->document = $this->uploadImage($request, 'document', 'expense') : null;
            $data->save();

            if ($condition == 'create') {
                  if ($request->shift_register == 'yes') {
                        $shift = new ShiftRegisterTransaction();
                        $shift->shift_register_id = $getShift->id;
                        $shift->amount = Helper::fresh_aprice($request->amount);
                        $shift->pay_method = 'cash';
                        $shift->transaction_type = 'expense';
                        $shift->transaction_id = $data->id;
                        $shift->save();
                  }
            }

            if ($condition == 'update') {
                  if ($request->shift_register == 'yes') {
                        $getShiftTransaction = ShiftRegisterTransaction::where("transaction_id", $data->id)->where("transaction_type", "expense")->first();
                        if ($getShiftTransaction == null) {
                              $shift = new ShiftRegisterTransaction();
                        } else {
                              $shift = ShiftRegisterTransaction::findOrFail($getShiftTransaction->id);
                        }
                        $shift->shift_register_id = $getShift->id;
                        $shift->amount = Helper::fresh_aprice($request->amount);
                        $shift->pay_method = 'cash';
                        $shift->transaction_type = 'expense';
                        $shift->transaction_id = $data->id;
                        $shift->save();
                  }
            }
      }

      public function uploadImage(Request $request, $name, $path)
      {
            if ($request->hasFile($name)) {
                  return $request->file($name)->store('uploads/' . $path . '');
            }
      }

      public function delete($id)
      {
            if (!Auth::user()->can('Hapus Pengeluaran')) {
                  abort(403, 'Unauthorized action.');
            }
            $data = Expense::findOrFail($id);
            return $this->deleteData($data, $id);
      }

      public function deleteData($data, $id)
      {
            if ($data->delete($id)) {
                  return back()->with(['flash' => __('success')]);
            } else {
                  return back()->with(['gagal' => __('error')]);
            }
      }
}
