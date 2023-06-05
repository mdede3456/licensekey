<?php

namespace Mdhpos\Licensekey\Controllers;

use App\Helper;
use App\Models\Admin\Customer;
use App\Models\Admin\Store;
use App\Models\Product\Stock;
use App\Models\Product\Supplier;
use App\Models\Transaction\CreditPriodeList;
use App\Models\Transaction\Purchase;
use App\Models\Transaction\ReturnDetail;
use App\Models\Transaction\SalesReturn;
use App\Models\Transaction\Sell;
use App\Models\Transaction\SellPurchase;
use App\Models\Transaction\ShiftRegister;
use App\Models\Transaction\ShiftRegisterTransaction;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionPayment;
use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
      public function sale(Request $request)
      {
            if (!Auth::user()->can('Daftar Penjualan')) {
                  abort(403, 'Unauthorized action.');
            }

            $user = User::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
            })->get();

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            $payment = [
                  'paid'  => 'Terbayar',
                  'due'   => 'Hutang',
            ];

            $status = [
                  'due'       => __('general.sell_due'),
                  'transit'   => 'Dalam Pengiriman',
                  'ordered'   => 'Sedang Dikemas',
                  "paid"  => __('general.paid'),
                  'final' => __('general.paid')
            ];


            if ($request->ajax()) {
                  $data = Transaction::where('type', 'sell')->where("status", "!=", "hold")
                        ->where(function ($query) use ($request) {
                              return $request->store ? $query->where('store_id', $request->store) : '';
                        })->where(function ($query) use ($request) {
                              return $request->payment ?  $query->where('payment_status', $request->payment) : '';
                        })->where(function ($query) use ($request) {
                              return $request->createdby ?  $query->where('created_by', $request->createdby) : '';
                        })->where(function ($query) use ($request) {
                              if ($request->end_date && $request->start_date) {
                                    return $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                              }
                              if ($request->date_now) {
                                    return $query->whereDate('created_at', $request->date_now);
                              }
                        })->where(function ($query) {
                              return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        })
                        ->orderBy('id', 'desc');

                  return DataTables::of($data)
                        ->addColumn(
                              'action',
                              function ($row) {
                                    $html = '<a href="' . route('m.sale_invoice', $row->id) . '" style="text-decoration:none;"><span class="badge bg-primary text-white">Klik To Detail</span></a>';
                                    return $html;
                              }
                        )->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->store->name ?? '';
                        })->addColumn('my_cystomer', function ($row) {
                              return  $row->customer->name ?? '';
                        })->addColumn(
                              'my_status',
                              function ($row) use ($status) {
                                    $html =  '<b>' . $status[$row->status] . '</b>';
                                    return $html;
                              }
                        )->addColumn(
                              'my_sale',
                              function ($row) use ($status) {
                                    return count($row->sell);
                              }
                        )->addColumn(
                              'qty_sale',
                              function ($row) use ($status) {
                                    $qtysell = $row->qty_sell;
                                    return $qtysell;
                              }
                        )->addColumn(
                              'qty_return',
                              function ($row) use ($status) {
                                    $returnsell = 0;
                                    $returnqty = $row->sell()->get()->sum('qty_return');
                                    if ($returnqty > 0) {
                                          $returnsell = $returnqty;
                                    }
                                    return $returnsell;
                              }
                        )->editColumn('final_total', function ($row) {
                              return number_format($row->final_total);
                        })->addColumn('due_total', function ($row) {
                              return number_format($row->due_total);
                        })->addColumn('total_pay', function ($row) {
                              return $row->pay_total;
                        })->addColumn('profit', function ($row) {
                              return number_format($row->profit);
                        })->addColumn('created_by', function ($row) {
                              return $row->createdby->name ?? '';
                        })
                        ->rawColumns(['action',  'mydate', 'my_store', 'my_cystomer', 'my_status', 'my_sale', 'qty_sale', 'qty_return', 'final_total', 'total_pay', 'due_total', 'profit', 'created_by'])
                        ->make(true);
            }

            return view('vendor.mobile.transaction.sale', ['page' => __('sidebar.sell_report')], compact('store', 'payment', 'user', 'status'));
      }

      public function saleInvoice($id)
      {
            if (!Auth::user()->can('Daftar Penjualan')) {
                  abort(403, 'Unauthorized action.');
            }

            $data = Transaction::findOrFail($id);
            $status = [
                  'due'   => __('general.sell_due'),
                  "paid"  => __('general.paid'),
                  'final' => __('general.paid')
            ];

            return view('vendor.mobile.transaction.sell_detail', ['page' => __('report.sell_detail')], compact('data', 'status'));
      }

      public function saleDetail(Request $request)
      {

            if (!Auth::user()->can('Daftar Penjualan')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            if ($request->ajax()) {

                  $data = Sell::with("transaction", "product", "variation")->where(function ($q) use ($request) {
                        if ($request->end_date && $request->start_date) {
                              return $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
                        }
                        if ($request->date_now) {
                              return $q->whereDate('created_at', $request->date_now);
                        }
                  })->where(function ($q) {
                        return $q->whereHas('transaction', function ($query) {
                              $query->where('status', "final");
                        });
                  })->where(function ($q) {
                        return $q->whereHas('transaction', function ($query) {
                              Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('product', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        })->orWhereHas('variation', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->store ? $query->where('store_id', $request->store) : '';
                        });
                  })->orderBy("id", "desc");

                  return DataTables::of($data)
                        ->addColumn(
                              'action',
                              function ($row) {
                                    $html = '';
                                    if ($row->qty > $row->qty_return) {
                                          $html = '<a href="javascript:void(0)" id="' . $row->id . '"  onclick="getreturnmodal(this.id)" style="text-decoration:none;"><span class="badge bg-primary text-white">Return Penjualan</span></a>';
                                    }
                                    return $html;
                              }
                        )->addColumn("name", function ($row) {
                              $name = $row->product->name ?? '';
                              $variation = $row->variation->name ?? '';
                              return  $name . ' ' . $variation;
                        })
                        ->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10) . '<input type="hidden" id="idpo" value="' . $row->id . '">';
                        })->addColumn('my_store', function ($row) {
                              return  $row->transaction->store->name ?? '';
                        })->addColumn('my_cystomer', function ($row) {
                              return  $row->transaction->customer->name ?? '';
                        })->addColumn(
                              'qty_sale',
                              function ($row) {
                                    return number_format($row->qty);
                              }
                        )->addColumn(
                              'qty_return',
                              function ($row) {
                                    return number_format($row->qty_return);
                              }
                        )->addColumn('satuan', function ($row) {
                              return number_format($row->unit_price);
                        })->addColumn('subtotal', function ($row) {
                              $allqty     = (int)$row->qty - (int)$row->qty_return;
                              $subtotal   = (int)$row->unit_price_before_disc * (int)$allqty;
                              return number_format($subtotal);
                        })->addColumn('profit', function ($row) {
                              return number_format($row->profit_sales);
                        })->addColumn('created_by', function ($row) {
                              return $row->transaction->createdby->name ?? '';
                        })
                        ->rawColumns(['satuan', 'name',  'mydate', 'my_store', 'my_cystomer', 'subtotal',  'qty_sale', 'qty_return', 'profit', 'created_by', 'action'])
                        ->make(true);
            }

            return view("vendor.mobile.transaction.sale_detail", ["page" => "Laporan Detail Penjualan"], compact("store"));
      }

      public function createReturn(Request $request)
      {

            if ($request->qty_return != 0) {
                  $data = new Transaction();
                  $data->store_id = Session::get('mystore');
                  $data->type     = 'sales_return';
                  $data->status   = 'final';
                  $data->payment_status = 'paid';

                  $getTransaction = Transaction::findOrFail($request->transaction_id);

                  $data->return_parent = $getTransaction->id;
                  $data->created_by   = Auth()->user()->id;
                  $data->invoice_no   = rand();
                  $data->ref_no       = "RETURNSELL-" . rand();
                  $data->transaction_date = date('Y-m-d');
                  $data->customer_id  = $getTransaction->customer_id;
                  $data->total_before_tax = Helper::fresh_aprice($request->subtotal_return);
                  $data->final_total = Helper::fresh_aprice($request->subtotal_return);
                  $data->save();

                  $sell = Sell::findOrFail($request->sell_id);
                  $sell->qty_return       = $sell->qty_return + $request->qty_return;
                  $sell->save();

                  $sellpurchase = SellPurchase::where("sell_id", $request->sell_id)->first();
                  $sellpurchase->qty_return = $sellpurchase->qty_return + $request->qty_return;
                  $sellpurchase->save();

                  if ($request->condition == 'good') {
                        $purchase = Purchase::where("id", $sellpurchase->purchase_id)->first();
                        $purchase->qty_return = $purchase->qty_return - $request->qty_return;
                        $purchase->save();

                        $stock = Stock::where("product_id", $request->product_id)->where("variation_id", $request->variation_id)->where("store_id", Session::get('mystore'))->first();
                        $stock->qty_available = $stock->qty_available + $request->qty_return;
                        $stock->save();
                  }

                  $return = new SalesReturn();
                  $return->transaction_id = $data->id;
                  $return->sell_id = $sell->id;
                  $return->return_qty = $request->qty_return;
                  $return->condition = $request->condition;
                  $return->save();

                  $storeSett = Store::findOrFail(Session::get('mystore'));

                  if ($storeSett->shift_register == 'active') {

                        $getShift = ShiftRegister::whereYear("created_at", date('Y'))
                              ->whereMonth("created_at", date('m'))
                              ->whereDay("created_at", date('d'))
                              ->where("status", "open")
                              ->where("store_id", Session::get('mystore'))
                              ->first();

                        if ($getShift != null) {
                              $shift = new ShiftRegisterTransaction();
                              $shift->shift_register_id = $getShift->id;
                              $shift->amount = $request->amount_total;
                              $method = 'cash';
                              $shift->pay_method = $method;
                              $shift->transaction_type = 'refund';
                              $shift->transaction_id = $data->id;
                              $shift->save();
                        }
                  }
            }
      }

      public function saleReturn(Request $request)
      {

            if (!Auth::user()->can('Return Penjualan')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            if ($request->ajax()) {

                  $data = SalesReturn::with("transaction", "sell", "sell.product", "sell.variation")->where(function ($q) use ($request) {
                        if ($request->end_date && $request->start_date) {
                              return $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
                        }
                        if ($request->date_now) {
                              return $q->whereDate('created_at', $request->date_now);
                        }
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('sell.product', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        })->orWhereHas('sell.variation', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        });
                  })->where(function ($q) {
                        return $q->whereHas('transaction', function ($query) {
                              Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->store ? $query->where('store_id', $request->store) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->customer ? $query->where('customer_id', $request->customer) : '';
                        });
                  })->orderBy("id", "desc");

                  return DataTables::of($data)
                        ->addColumn("name", function ($row) {
                              $name = $row->sell->product->name ?? '';
                              $variation = $row->sell->variation->name ?? '';
                              return  $name . ' ' . $variation;
                        })
                        ->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->transaction->store->name ?? '';
                        })->addColumn('my_cystomer', function ($row) {
                              return  $row->transaction->customer->name ?? '';
                        })->addColumn(
                              'qty_return',
                              function ($row) {
                                    return number_format($row->return_qty);
                              }
                        )->addColumn('satuan', function ($row) {
                              return number_format($row->sell->unit_price);
                        })->addColumn('condition', function ($row) {
                              if ($row->condition == 'good') {
                                    return "Baik / Masih Bagus";
                              } else {
                                    return "Sudah Rusak";
                              }
                        })->addColumn('subtotal', function ($row) {
                              $allqty =  $row->return_qty;
                              $subtotal = $row->sell->unit_price * $allqty;
                              return number_format($subtotal);
                        })->addColumn('created_by', function ($row) {
                              return $row->transaction->createdby->name ?? '';
                        })
                        ->rawColumns(['satuan', 'name',  'mydate', 'my_store', 'my_cystomer', 'subtotal',  'qty_return', 'created_by', 'condition'])
                        ->make(true);
            }

            return view("vendor.mobile.transaction.return_sales", ["page" => "Laporan Detail Return Penjualan"], compact("store"));
      }

      public function purchase(Request $request)
      {
            if (!Auth::user()->can('Laporan Purchase')) {
                  abort(403, 'Unauthorized action.');
            }

            $status = [
                  'received'      => __('purchase.received'),
                  'ordered'       => __('purchase.ordered'),
                  'pending'       => __('purchase.pending')
            ];

            $payment = [
                  'due'   => __('general.po_due'),
                  'paid'  => __('general.paid')
            ];


            if ($request->ajax()) {
                  $data = Transaction::where('type', 'purchase')->orderBy('id', 'desc')
                        ->where(function ($query) use ($request) {
                              return $request->store ?  $query->where('store_id', $request->store) : '';
                        })->where(function ($query) use ($request) {
                              return $request->supplier ? $query->where('supplier_id', $request->supplier) : '';
                        })->where(function ($query) use ($request) {
                              return $request->payment ? $query->where('payment_status', $request->payment) : '';
                        })->where(function ($query) use ($request) {
                              if ($request->date_now) {
                                    return $query->whereDate('created_at', $request->date_now);
                              }
                        })->where(function ($query) {
                              return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        })->orderBy("id", "desc");

                  return DataTables::of($data)
                        ->addColumn(
                              'action',
                              function ($row) {
                                    $html = '<a href="' . route('m.purchase_invoice', $row->id) . '" style="text-decoration:none;"><span class="badge bg-primary text-white">Klik To Detail</span></a>';
                                    return $html;
                              }
                        )->addColumn('identity', function ($row) {
                              return  $row->id;
                        })->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->store->name ?? '';
                        })->addColumn('my_supplier', function ($row) {
                              return  $row->supplier->name ?? '';
                        })->addColumn('my_product', function ($row) {
                              return  count($row->purchase);
                        })->addColumn('my_qty', function ($row) {
                              return  $row->qty_purchase;
                        })->editColumn('final_total', function ($row) {
                              return  number_format($row->final_total);
                        })->addColumn(
                              'my_status',
                              function ($row) use ($status) {
                                    $html =  $status[$row->status];
                                    return $html;
                              }
                        )->addColumn(
                              'my_return',
                              function ($row) {
                                    $returned = 0;
                                    $returnqty = $row->purchase()->get()->sum('qty_return');
                                    if ($returnqty > 0) {
                                          $returned  = $returnqty;
                                    }
                                    return $returned;
                              }
                        )->addColumn(
                              'my_payment_status',
                              function ($row) {
                                    $payment = [
                                          'due'   => __('general.po_due'),
                                          'paid'  => __('general.paid')
                                    ];
                                    $html =  '<span class=" badge bg-primary text-white">' . $payment[$row->payment_status] . '</span>';
                                    return $html;
                              }
                        )->addColumn('total_pay', function ($row) {
                              return $row->pay_total;
                        })->addColumn('due_total', function ($row) {
                              return number_format($row->due_total_po ?? $row->final_total);
                        })
                        ->rawColumns(['action', 'identity', 'mydate', 'my_store', 'my_supplier', 'my_status', 'my_payment_status', 'total_pay', 'due_total', 'my_return', 'my_product', 'my_qty', 'final_total'])
                        ->make(true);
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            $supplier = Supplier::all();

            return view('vendor.mobile.transaction.purchase', ['page' => __('sidebar.purchase_report')], compact(
                  'store',
                  'supplier',
                  'status',
                  'payment'
            ));
      }

      public function purchaseInvoice($id)
      {
            $status = [
                  'received'      => __('purchase.received'),
                  'ordered'       => __('purchase.ordered'),
                  'pending'       => __('purchase.pending')
            ];

            $payment = [
                  'due'   => __('general.po_due'),
                  'paid'  => __('general.paid')
            ];

            $purchase = Transaction::findOrFail($id);
            $getDetail = Purchase::where('transaction_id', $id)->get();
            return view('vendor.mobile.transaction.po_detail', ['page' => __('purchase.detail')], compact('getDetail', 'purchase', 'status', 'payment'));
      }

      public function purchaseDetail(Request $request)
      {
            if (!Auth::user()->can('Laporan Purchase')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            $supplier = Supplier::all();

            if ($request->ajax()) {

                  $data = Purchase::with("transaction", "product", "variation")->where(function ($q) use ($request) {
                        if ($request->end_date && $request->start_date) {
                              return $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
                        }
                        if ($request->date_now) {
                              return $q->whereDate('created_at', $request->date_now);
                        }
                  })->where(function ($q) {
                        return $q->whereHas('transaction', function ($query) {
                              Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->store ? $query->where('store_id', $request->store) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->supplier ? $query->where('supplier_id', $request->supplier) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('product', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        })->orWhereHas('variation', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        });
                  })->orderBy("id", "desc");

                  return DataTables::of($data)
                        ->addColumn(
                              'action',
                              function ($row) {
                                    $html = '';
                                    if ($row->transaction->type == 'purchase') {
                                          $total = ($row->qty_sold + $row->qty_adjusted) + ($row->qty_return + $row->qty_transfer) + $row->qty_expire;
                                          if ($total < $row->quantity) {
                                                $html = '<a href="javascript:void(0)" id="' . $row->id . '"  onclick="getreturnmodal(this.id)" style="text-decoration:none;"><span class="badge bg-primary text-white">Return Pembelian</span></a>';
                                          }
                                    }

                                    return $html;
                              }
                        )->addColumn("name", function ($row) {
                              $name = $row->product->name ?? '';
                              $variation = $row->variation->name ?? '';
                              return  $name . ' ' . $variation;
                        })
                        ->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->transaction->store->name ?? '';
                        })->addColumn(
                              'qty_po',
                              function ($row) {
                                    return number_format($row->quantity);
                              }
                        )->addColumn('satuan', function ($row) {
                              return number_format($row->purchase_price);
                        })->addColumn('subtotal', function ($row) {
                              $allqty = $row->quantity - $row->qty_return;
                              $subtotal = $row->purchase_price * $allqty;
                              return number_format($subtotal);
                        })->addColumn('qty_sold', function ($row) {
                              return number_format($row->qty_sold);
                        })->addColumn('created_by', function ($row) {
                              return $row->transaction->createdby->name ?? '';
                        })->addColumn('expire', function ($row) {
                              if ($row->expire_date != null) {
                                    return $row->expire_date;
                              } else {
                                    return '';
                              }
                        })->addColumn('batch', function ($row) {
                              if ($row->no_batch != null) {
                                    return $row->no_batch;
                              } else {
                                    return '';
                              }
                        })->editColumn('qty_return', function ($row) {
                              return number_format($row->qty_return);
                        })->editColumn('qty_adjusted', function ($row) {
                              return number_format($row->qty_adjusted);
                        })->editColumn('qty_transfer', function ($row) {
                              return number_format($row->qty_transfer);
                        })->editColumn('qty_expire', function ($row) {
                              return number_format($row->qty_expire);
                        })->addColumn('my_supplier', function ($row) {
                              return $row->transaction->supplier->name ?? '';
                        })
                        ->rawColumns(['satuan', 'my_supplier', 'name', 'expire', 'batch', 'action', 'mydate', 'my_store', 'subtotal',  'qty_po', 'qty_sold', 'created_by', 'action'])
                        ->make(true);
            }

            return view("vendor.mobile.transaction.purchase_detail", ["page" => "Laporan Detail PO / Pembelian"], compact("store", "supplier"));
      }

      public function domPoItem($id)
      {
            $data = Purchase::findOrFail($id);
            $name = $data->product->name ?? '';
            $variation = $data->variation->name ?? '';
            $available_stock = $data->quantity - (($data->qty_sold + $data->qty_adjusted) + ($data->qty_return + $data->qty_transfer) + $data->qty_expire);
            return response()->json([
                  'product' => [
                        'id_transaksi'  => $data->transaction_id,
                        'name'      => $name . ' ' . $variation,
                        'po_id'   => $data->id,
                        'product_id' => $data->product->id,
                        'var_id'    => $data->variation->id,
                        's_price'   => number_format($data->purchase_price_inc_tax),
                        'price'     => $data->purchase_price_inc_tax,
                        'stock'     => $available_stock,
                  ]
            ]);
      }

      public function poreturnStore(Request $request)
      {
            if ($request->qty_return != 0) {
                  $data = new Transaction();
                  $data->store_id = Session::get('mystore');
                  $data->type     = 'purchase_return';
                  $data->status   = 'final';
                  $data->payment_status = 'due';

                  $getTransaction = Transaction::findOrFail($request->transaction_id);

                  $data->supplier_id  = $getTransaction->supplier_id;
                  $data->return_parent = $getTransaction->id;
                  $data->created_by   = Auth()->user()->id;
                  $data->invoice_no   = rand();
                  $data->ref_no       = "RETURN-" . rand();
                  $data->transaction_date = date('Y-m-d');

                  $data->total_before_tax = Helper::fresh_aprice($request->subtotal_return);
                  $data->final_total = Helper::fresh_aprice($request->subtotal_return);
                  $data->save();

                  $purchase = Purchase::findOrFail($request->po_id);
                  $purchase->qty_return       = $purchase->qty_return + $request->qty_return;
                  $purchase->save();

                  if ($getTransaction->status == 'received') {
                        $CheckSkus = Stock::where('product_id', $purchase->product_id)->where('variation_id', $purchase->variation_id)->where('store_id', $purchase->store_id)->first();
                        $skus = Stock::findOrFail($CheckSkus->id);
                        $skus->qty_available  = $skus->qty_available -  $request->qty_return;
                        $skus->save();
                  }

                  $return = new ReturnDetail();
                  $return->transaction_id = $data->id;
                  $return->purchase_id = $purchase->id;
                  $return->return_qty = $request->qty_return;
                  $return->save();
            }
      }

      public function returnPoDetail(Request $request)
      {
            if (!Auth::user()->can('Laporan Return')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            if ($request->ajax()) {

                  $data = ReturnDetail::with("transaction", "purchase", "purchase.product", "purchase.variation")->where(function ($q) use ($request) {
                        if ($request->end_date && $request->start_date) {
                              return $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
                        }
                        if ($request->date_now) {
                              return $q->whereDate('created_at', $request->date_now);
                        }
                  })->where(function ($q) {
                        return $q->whereHas('transaction', function ($query) {
                              Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->store ? $query->where('store_id', $request->store) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('transaction', function ($query) use ($request) {
                              $request->supplier ? $query->where('supplier_id', $request->supplier) : '';
                        });
                  })->where(function ($q) use ($request) {
                        return $q->whereHas('purchase.product', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        })->orWhereHas('purchase.variation', function ($query) use ($request) {
                              $request->name ? $query->where('name', 'like', '%' . $request->name . '%') : '';
                        });
                  })->where("return_qty", ">", 0)->orderBy("id", "desc");

                  return DataTables::of($data)
                        ->addColumn("name", function ($row) {
                              $name = $row->purchase->product->name ?? '';
                              $variation = $row->purchase->variation->name ?? '';
                              return  $name . ' ' . $variation;
                        })
                        ->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->transaction->store->name ?? '';
                        })->addColumn('my_supplier', function ($row) {
                              return  $row->transaction->supplier->name ?? '';
                        })->addColumn(
                              'qty_return',
                              function ($row) {
                                    return number_format($row->return_qty);
                              }
                        )->addColumn('satuan', function ($row) {
                              return number_format($row->purchase->purchase_price);
                        })->addColumn('subtotal', function ($row) {
                              $allqty = $row->return_qty;
                              $subtotal = $row->purchase->purchase_price * $allqty;
                              return number_format($subtotal);
                        })->addColumn('created_by', function ($row) {
                              return $row->transaction->createdby->name ?? '';
                        })
                        ->rawColumns(['satuan', 'name', 'mydate', 'my_store', 'my_supplier', 'subtotal', 'qty_return', 'created_by'])
                        ->make(true);
            }

            return view("vendor.mobile.transaction.return_po", ["page" => "Laporan Detail Return PO / Pembelian"], compact("store"));
      }

      public function dueCustomer(Request $request)
      {
            if (!Auth::user()->can('Laporan Hutang')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            $customer = Customer::all();

            if ($request->ajax()) {
                  $data = Transaction::where('type', 'sell')->where('payment_status', 'due')->where('status', 'final')
                        ->where(function ($query) use ($request) {
                              return $request->store ? $query->where('store_id', $request->store) : '';
                        })->where(function ($query) use ($request) {
                              return $request->customer ? $query->where('customer_id', $request->customer) : '';
                        })->where(function ($query) use ($request) {
                              if ($request->end_date && $request->start_date) {
                                    return $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                              }
                              if ($request->date_now) {
                                    return $query->whereDate('created_at', $request->date_now);
                              }
                        })->where(function ($query) {
                              return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                        })->orderBy('id', 'desc');

                  return DataTables::of($data)
                        ->addColumn(
                              'action',
                              function ($row) {
                                    $html = '<a href="' . route('m.due_detail', $row->id) . '" style="text-decoration:none;"><span class="badge bg-primary text-white">Klik To Detail</span></a>';
                                    return $html;
                              }
                        )->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->store->name ?? '';
                        })->addColumn('my_customer', function ($row) {
                              return  $row->customer->name ?? '';
                        })->editColumn('final_total', function ($row) {
                              return number_format($row->final_total);
                        })->addColumn('pay_total', function ($row) {
                              return $row->pay_total;
                        })->addColumn('due_total', function ($row) {
                              return  number_format($row->due_total ?? $row->final_total);
                        })->rawColumns(['action', 'mydate', 'my_store', 'my_customer', 'final_total', 'pay_total', 'due_total'])
                        ->make(true);
            }
            return view('vendor.mobile.transaction.due', ['page' => __('sidebar.debt_book')], compact(
                  'store',
                  'customer',
            ));
      }

      public function dueDetail(Request $request, $id)
      {
            $data       = Transaction::findOrFail($id);
            $payment    = TransactionPayment::where('transaction_id', $data->id)->orderBy('id', 'desc')->get();
            $cicilan    = CreditPriodeList::where("transaction_id", $id)->where("status", "due")->get(['id', "credit_priode"]);

            $status = [
                  'due'   => __('general.sell_due'),
                  "paid"  => __('general.paid'),
                  'final' => __('general.paid')
            ];

            if ($request->start_date) {
                  $payment = TransactionPayment::where(function ($query) use ($request) {
                        return $request->start_date ?
                              $query->whereBetween('created_at', [$request->start_date, $request->end_date]) : '';
                  })->where('transaction_id', $data->id)->orderBy('id', 'desc')->get();
            }

            return view('vendor.mobile.transaction.due_detail', ['page' => __('report.due_detail')], compact('data', 'payment', 'status', 'cicilan'));
      }

      public function listCicilan($id)
      {
            $data = Transaction::find($id);
            return view("vendor.mobile.transaction.cicilan", ["page" => "Cicilan Customer"], compact("data"));
      }


      public function addPay(Request $request)
      {

            $validator = Validator::make($request->all(), [
                  'payment_amount'        => 'required',
                  'transaction_id'        => 'required'
            ]);

            if ($validator->fails()) {
                  return redirect()->back()->withErrors($validator)->withInput();
            }

            $getTransaction = Transaction::findOrFail($request->transaction_id);

            if ($getTransaction->type == 'sell') {
                  $condition = $getTransaction->due_total;
                  $dbtOrCrd  = 'debit';
                  if ($getTransaction->due_total == 0) {
                        return response()->json([
                              'errors' => "Tidak Ada Tunggakan Untuk Transaksi ini",
                              'message' => 'nothing'
                        ]);
                  }
            } else if ($getTransaction->type == 'purchase') {
                  $dbtOrCrd  = 'credit';
                  $condition = $getTransaction->due_total_po;
            } else if ($getTransaction->type == 'purchase_return') {
                  $dbtOrCrd  = 'debit';
                  $condition = $getTransaction->due_total_return;
            } else if ($getTransaction->type == 'sales_return') {
                  $dbtOrCrd  = 'credit';
                  $condition = $getTransaction->due_total_return_sell;
            }

            $payment = new TransactionPayment();
            $payment->transaction_id    = $request->transaction_id;

            if ($request->cicilan_id) {
                  $getCicilan = CreditPriodeList::find($request->cicilan_id);
                  $total = $getCicilan->paid_nominal + Helper::fresh_aprice($request->payment_amount);

                  if ($total >= $getCicilan->nominal) {
                        $pay    = $getCicilan->nominal;
                  } else {
                        $pay = $total;
                  }
            } else {
                  if ($condition >= Helper::fresh_aprice($request->payment_amount)) {
                        $pay    = Helper::fresh_aprice($request->payment_amount);
                  } else {
                        $pay    = $condition;
                  }
            }


            if ($condition == $pay) {
                  $getTransaction->payment_status = 'paid';
                  $getTransaction->save();
            }
            $payment->created_by        = Auth::user()->id;
            $payment->amount            = $pay;
            $payment->method            = $request->payment_method;
            $payment->transaction_type  = 'transaction';
            $request->payment_note ? $payment->note = $request->payment_note : null;
            $request->account_id ? $payment->account_id = $request->account_id : null;
            if ($request->payment_method == 'bank_transfer') {
                  $request->no_rek ? $payment->no_rek = $request->no_rek : null;
                  $request->an ? $payment->an = $request->an : null;
                  $request->bank_id ? $payment->bank_id = $request->bank_id : null;
            } else if ($request->payment_method == 'card') {
                  $request->card_number ? $payment->card_number = $request->card_number : null;
                  $request->card_holder_name ? $payment->card_holder_name = $request->card_holder_name : null;
                  $request->card_transaction_number ? $payment->card_transaction_number = $request->card_transaction_number : null;
                  $request->card_type ? $payment->card_type = $request->card_type : null;
                  $request->card_month ? $payment->card_month = $request->card_month : null;
                  $request->card_year ? $payment->card_year = $request->card_year : null;
                  $request->card_security ? $payment->card_security = $request->card_security : null;
            }



            if ($getTransaction->type == 'sell') {
                  $storeSett = Store::findOrFail(Session::get('mystore'));
                  if ($storeSett->shift_register == 'active') {

                        $getShift = ShiftRegister::whereYear("created_at", date('Y'))
                              ->whereMonth("created_at", date('m'))
                              ->whereDay("created_at", date('d'))
                              ->where("status", "open")
                              ->where("store_id", Session::get('mystore'))
                              ->first();

                        if ($getShift != null) {
                              $shift = new ShiftRegisterTransaction();
                              $shift->shift_register_id = $getShift->id;
                              $shift->amount = $pay;

                              $method = 'other';

                              if ($payment->method == 'cash') {
                                    $method = 'cash';
                              }

                              if ($payment->method == 'bank_transfer') {
                                    $method = 'bank';
                              }

                              $shift->pay_method = $method;
                              $shift->transaction_type = 'sell';
                              $shift->transaction_id = $getTransaction->id;
                              $shift->save();
                        }
                  }
            }

            if (!empty($request->cicilan_id)) {
                  if ($request->cicilan_id != null) {
                        $payment->cicilan_id = $request->cicilan_id;

                        if ($total >= $getCicilan->nominal) {
                              $getCicilan->paid_nominal = $getCicilan->nominal;
                        } else {
                              $getCicilan->paid_nominal = $total;
                        }

                        if ($total == $getCicilan->nominal || $total >= $getCicilan->nominal) {
                              $getCicilan->status = 'paid';
                        }
                        $getCicilan->save();
                  }
            }

            $payment->save();

            if ($request->account_id) {
                  if ($request->payment_amount > 0) {
                        Helper::createAccount($dbtOrCrd, $request, $payment);
                  }
            }
            return redirect()->back()->with(['flash' => "Pembayaran Berhasil Ditambahkan"]);
      }

      public function updatePay(Request $request)
      {
            $validator = Validator::make($request->all(), [
                  'status'        => 'required'
            ]);

            if ($validator->fails()) {
                  return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = Transaction::findOrFail($request->transaction_id);
            $data->status = $request->status;
            $data->payment_status = 'paid';
            $data->save();

            return redirect()->back()->with(['flash' => "Status Berhasil Diubah"]);
      }

      public function transfer(Request $request)
      {
            if (!Auth::user()->can('Laporan Stock Transfer')) {
                  abort(403, 'Unauthorized action.');
            }

            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('id', Auth::user()->store_id) : '';
            })->get();

            $status = [
                  'transit'   => __('transfer.transit'),
                  'complete'  => __('transfer.complete'),
                  'pending'   => __('transfer.pending')
            ];

            if ($request->ajax()) {
                  $data = Transaction::where('type', 'stock_transfer')
                        ->where(function ($query) use ($request) {
                              if ($request->end_date && $request->start_date) {
                                    return $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                              }
                              if ($request->date_now) {
                                    return $query->whereDate('created_at', $request->date_now);
                              }
                        })->where(function ($query) {
                              return Auth::user()->store_id != 0 ? $query->where('from', Auth::user()->store_id) : '';
                        })->orWhere(function ($query) {
                              return Auth::user()->store_id != 0 ? $query->where('to', Auth::user()->store_id) : '';
                        })->orderBy('id', 'desc');
                  return DataTables::of($data)
                        ->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('from_store', function ($row) {
                              return  $row->transfer->fromstore->name ?? '';
                        })->addColumn('my_product', function ($row) {
                              return  count($row->manytransfer);
                        })->addColumn('my_qty', function ($row) {
                              return  $row->transfer_qty;
                        })->addColumn('to_store', function ($row) {
                              return  $row->transfer->tostore->name ?? '';
                        })->editColumn('shipping_charges', function ($row) {
                              return  number_format($row->shipping_charges);
                        })->editColumn('final_total', function ($row) {
                              return  number_format($row->final_total);
                        })->addColumn(
                              'this_status',
                              function ($row) use ($status) {
                                    return $status[$row->status];
                              }
                        )->addColumn(
                              'action',
                              function ($row) use ($status) {
                                    $html = '<a href="' . route('m.transfer_detail', $row->id) . '" style="text-decoration:none;"><span class="badge bg-primary text-white">Klik To Detail</span></a>';
                                    return $html;
                              }
                        )
                        ->rawColumns(['action', 'mydate', 'from_store', 'to_store', 'shipping_charges', 'final_total', 'this_status', 'my_product', 'my_qty'])
                        ->make(true);
            }
            return view('vendor.mobile.transaction.transfer', ['page' => __('sidebar.r_stock_transfer')], compact('store', 'status'));
      }

      public function transferDetail($id)
      {
            $data = Transaction::findOrFail($id);
            return view('vendor.mobile.transaction.transfer_detail', ['page' => __('sidebar.stock_transfer')], compact('data'));
      }

      public function adjustment(Request $request)
      {

            if (!Auth::user()->can('Laporan Stock Adjustment')) {
                  abort(403, 'Unauthorized action.');
            }

            if ($request->ajax()) {
                  $data = Transaction::where(function ($query) use ($request) {
                        if ($request->end_date && $request->start_date) {
                              return $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                        }
                        if ($request->date_now) {
                              return $query->whereDate('created_at', $request->date_now);
                        }
                  })->where(function ($query) use ($request) {
                        return $request->store ? $query->where('store_id', $request->store) : "";
                  })->where(function ($query) {
                        return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
                  })->where('type', 'stock_adjustment')->orderBy('id', 'desc');

                  return DataTables::of($data)
                        ->addColumn('mydate', function ($row) {
                              return  substr($row->created_at, 0, 10);
                        })->addColumn('my_store', function ($row) {
                              return  $row->store->name ?? '';
                        })->addColumn('my_product', function ($row) {
                              return   count($row->adjustment);
                        })->addColumn('my_qty', function ($row) {
                              return  $row->adjustment_qty;
                        })->editColumn('final_total', function ($row) {
                              return  number_format($row->final_total);
                        })->editColumn('total_amount_recovered', function ($row) {
                              return  number_format($row->total_amount_recovered);
                        })->addColumn(
                              'action',
                              function ($row) {
                                    $html = '<a href="' . route('m.adjustment_detail', $row->id) . '" style="text-decoration:none;"><span class="badge bg-primary text-white">Klik To Detail</span></a>';
                                    return $html;
                              }
                        )->rawColumns(['action', 'mydate', 'my_store', 'final_total', 'total_amount_recovered', 'my_product', 'my_qty'])
                        ->make(true);
            }


            $store = Store::where(function ($query) {
                  return Auth::user()->store_id != 0 ? $query->where('store_id', Auth::user()->store_id) : '';
            })->get();


            return view('vendor.mobile.transaction.adjustment', ['page' => __('sidebar.stock_adjs')], compact('store'));
      }

      public function adjustmentDetail($id)
      {
            $data = Transaction::findOrFail($id);
            return view('vendor.mobile.transaction.adjustment_detail', ['page' => __('adjustment.detail')], compact('data'));
      }
}
