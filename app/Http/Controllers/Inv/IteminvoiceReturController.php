<?php

namespace App\Http\Controllers\Inv;

use App\Models\Master\Partner;
use App\Models\Inventory\ItemInvoice1;
use App\Models\Inventory\ItemInvoice2;
use App\Models\Inventory\ItemPartner;
use App\Models\Purchase\PurchaseOrder1;
use App\Models\Purchase\PurchaseOrder2;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Config\Users;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Finance\CustomerAccount;
use PagesHelp;
use Inventory;
use Accounting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;

class IteminvoiceReturController extends Controller
{
    public function show(Request $request)
    {
        // Extract request parameters
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = $request->descending === "false" ? "asc" : "desc";
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);
        $date1 = $request->date1;
        $date2 = $request->date2;
        $all = $request->all ?? '0';  // Use null coalescing operator

        // Build the base query
        $query = ItemInvoice1::selectRaw("sysid, doc_number, ref_date, ref_document, order_document, partner_name, due_date, amount, discount1, discount2, payment_discount, tax,
                total, payment, unpaid, CONCAT(trans_code, '-', trans_series) AS voucher, sysid_jurnal, pool_code, warehouse_id, void_date, is_void, void_by, uuid_rec, IFNULL(doc_name, '') AS doc_name")
            ->whereBetween('ref_date', [$date1, $date2])
            ->where('is_credit_note','1');


        // Apply pool_code filter if not fetching all
        if ($all === '0') {
            $query->where('pool_code', $pool_code);
        }

        // Apply text filter if provided
        if (!empty($filter)) {
            $filter = '%' . trim($filter) . '%';
            $query->where(function($q) use ($filter) {
                $q->where('doc_number', 'like', $filter)
                ->orWhere('partner_name', 'like', $filter)
                ->orWhere('ref_document', 'like', $filter)
                ->orWhere('order_document', 'like', $filter);
            });
        }

        // Execute the query and paginate the results
        $data = $query->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }

    public function get(Request $request)
    {
        // Retrieve the UUID from the request
        $uuid = $request->uuid ?? '';

        // Fetch the header for the given UUID
        $header = ItemInvoice1::where('uuid_rec', $uuid)
            ->where('is_credit_note','1')
            ->first();

        // Check if the header exists
        if ($header) {
            $sysid = $header->sysid;

            // Fetch the details associated with the header
            $details = ItemInvoice2::selectRaw(
                    "a.sysid,
                    a.line_no,
                    a.item_code,
                    a.price_code,
                    b.part_number,
                    a.descriptions,
                    ABS(a.qty_order) as qty_order,
                    ABS(a.qty_invoice) as qty_invoice,
                    a.mou_purchase,
                    a.convertion,
                    a.mou_inventory,
                    a.purchase_price,
                    a.prc_discount1,
                    a.prc_discount2,
                    a.prc_tax,
                    ABS(a.total) as total"
                )
                ->from('t_item_invoice2 as a')
                ->leftJoin('m_item as b', 'a.item_code', '=', 'b.item_code')
                ->where('a.sysid', $sysid)
                ->get();

            // Prepare the response data
            $data = [
                'header' => $header,
                'detail' => $details
            ];

            return response()->success('Success', $data);
        } else {
            return response()->error('', 501, 'Data Tidak ditemukan');
        }
    }


    public function post(Request $request){
        $data= $request->json()->all();
        $header=$data['header'];
        $detail=$data['detail'];
        $session =PagesHelp::Session();

        $header['pool_code']=$session->pool_code;

        $validator=Validator::make($header,[
            'ref_document'=>'bail|required',
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'warehouse_id'=>'bail|required',
            'partner_code'=>'required'
        ],[
            'ref_document.required'=>'Nomor invoice supplier harus diisi',
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'warehouse_id.required'=>'Gudang harus diisi',
            'partner_code.required'=>'Supplier harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.price_code'=>'bail|required|distinct|exists:m_item_stock_price,price_code',
            '*.qty_invoice'=>'bail|required|numeric|min:1',
            '*.purchase_price'=>'bail|required|numeric|min:1',
            '*.prc_discount1'=>'bail|required|numeric|min:0|max:100',
            '*.prc_discount2'=>'bail|required|numeric|min:0|max:100',
            '*.prc_tax'=>'bail|required|numeric|min:0|max:100'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.price_code.required'=>'Kode harga barang harus diisi',
            '*.price_code.exists'=>'Kode harga :input tidak ditemukan dimaster',
            '*.qty_invoice.min'=>'Jumlah invoice harus diisi/lebih besar dari NOL',
            '*.purchase_price.min'=>'Harga pembelian tidak boleh NOL',
            '*.item_code.distinct'=>'Kode barang :input terduplikasi (terinput lebih dari 1)',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        foreach($detail as $row){
            if ((floatval($row['qty_invoice']) > floatval($row['qty_order']))
                &&
                (floatval($row['qty_order'])<>0)) {
            return response()->error('',501,'Jumlah penerimaan lebih besar dari order');
            }
        }

        $header['pool_code']=($header['warehouse_id']=='HO') ? 'HO' :$header['pool_code'];

        $uuid=$header['uuid_rec'] ?? '';

        $invoice=ItemInvoice1::selectRaw('sysid,doc_number,ref_date,is_void,payment')
        ->where('uuid_rec',$uuid)
        ->first();

        if ($invoice) {
            $sysid=$invoice->sysid;
            if ($invoice->is_credit_note=='0') {
                return response()->error('',201,'Bukan invoice retur pembelian');
            } else if ($invoice->ref_date>$header['ref_date']){
                return response()->error('Gagal',501,'Tanggal transaksi tidak bisa mundur');
            }
        } else {
            $cur_date=Date('Y-m-d');
            if ($header['ref_date']<$cur_date) {
                return response()->error('Gagal',501,'Tanggal transaksi tidak bisa backdate (Mundur)');
            }
        }

        DB::beginTransaction();
        try{
            $Inv = ItemInvoice1::where('sysid',$header['order_sysid'])
            ->where('is_credit_note',0)
            ->first();

            if (!$Inv) {
                DB::rollback();
                return response()->error('Gagal',501,'Invoice pembelian yang akan diretur tidak ditemukan');
            }

            $invoice=ItemInvoice1::where('uuid_rec',$uuid)->first();
            if (!$invoice) {
                $invoice= new ItemInvoice1();
                $invoice->uuid_rec  =  Str::uuid();
                $invoice->doc_number= ItemInvoice1::GenerateRetur($header['pool_code'],$header['ref_date']);
                $opr='inserted';
            } else {
                $opr='updated';

                $return=ItemInvoice1::selectRaw("SUM(ABS(total)) as total")
                ->where('sysid',$invoice->order_sysid)
                ->where('is_credit_note',1)
                ->first();

                $total = isset($return->total) ? floatval($return->total) : 0;
                $Inv->payment = $Inv->payment - $total;
                $Inv->save();

                DB::update("UPDATE t_customer_account a
                INNER JOIN t_item_invoice1 b ON a.sysid=b.sysid
                SET a.total_paid=b.payment
                WHERE ref_sysid=?  AND doc_source=?",[$Inv->sysid,'LPB']);

                DB::update("UPDATE t_item_invoice2 SET qty_retur=0
                WHERE sysid=?",[$invoice->sysid]);

                ItemInvoice2::where('sysid',$invoice->sysid)->delete();
            }
            $Partner=Partner::select('partner_name')
            ->where('partner_id',$header['partner_code'])->first();

            $invoice->ref_document  = $header['ref_document'];
            $invoice->ref_date      = $header['ref_date'];
            $invoice->due_date      = $header['due_date'];
            $invoice->top           = $header['top'];
            $invoice->warehouse_id  = $header['warehouse_id'];
            $invoice->pool_code     = $header['pool_code'];
            $invoice->partner_code  = $header['partner_code'];
            $invoice->partner_name  = $Partner->partner_name ?? '';
            $invoice->order_sysid   = $header['order_sysid'];
            $invoice->order_document= $header['order_document'];
            $invoice->amount        = - $header['amount'];
            $invoice->discount1     = - $header['discount1'];
            $invoice->discount2     = - $header['discount2'];
            $invoice->tax           = - $header['tax'];
            $invoice->total         = - $header['total'];
            $invoice->is_credit_note= '1';
            $invoice->update_userid = $session->user_id;
            $invoice->update_timestamp = Date('Y-m-d H:i:s');
            $invoice->save();


            $sysid=$invoice->sysid;
            $order_sysid=$invoice->order_sysid;

            foreach($detail as $rec) {
                $rec['inventory_update'] = (float)$rec['qty_invoice'] * (float)$rec['convertion'];
                $rec['price_cost']       = (float)$rec['total'] /(float)$rec['inventory_update'];
                $amount=floatval($rec['qty_invoice'])*floatval($rec['purchase_price']);
                $rec['amount_discount1'] = $amount*(floatval($rec['prc_discount1'])/100);
                $amount= $amount - $rec['amount_discount1'];
                $rec['amount_discount2'] = $amount*(floatval($rec['prc_discount2'])/100);
                $amount= floatval($rec['qty_invoice'])*floatval($rec['purchase_price']) - ($rec['amount_discount1']+$rec['amount_discount2']);
                $rec['amount_tax']    = $amount*(floatval($rec['prc_tax'])/100);
                $rec['price_code']    = ($rec['price_code']=='') ?  Inventory::getPriceCode() :$rec['price_code'];

                ItemInvoice2::insert([
                    'sysid'=>$invoice->sysid,
                    'line_no'      => $rec['line_no'],
                    'item_code'    => $rec['item_code'],
                    'price_code'   => $rec['price_code'],
                    'descriptions' => $rec['descriptions'],
                    'mou_purchase' => $rec['mou_purchase'] ?? '',
                    'mou_inventory'=> $rec['mou_inventory'] ?? '',
                    'convertion'   => $rec['convertion'],
                    'qty_order'    => $rec['qty_order'],
                    'qty_invoice'  => - $rec['qty_invoice'],
                    'purchase_price' => $rec['purchase_price'],
                    'prc_discount1'  => $rec['prc_discount1'],
                    'prc_discount2'  => $rec['prc_discount2'],
                    'prc_tax'        => $rec['prc_tax'],
                    'total'          => - $rec['total'],
                    'order_sysid'    => $invoice->order_sysid,
                    'order_number'   => $invoice->order_document,
                    'inventory_update' => - $rec['inventory_update'],
                    'price_cost'       => - $rec['price_cost'],
                    'amount_discount1' => - $rec['amount_discount1'],
                    'amount_discount2' => - $rec['amount_discount2'],
                    'amount_tax'       => - $rec['amount_tax'],
                    'warehouse_id'=>$invoice->warehouse_id,
                    'is_cn'=>'1'
                ]);
            }

            DB::update("UPDATE t_item_invoice2 a
            INNER JOIN (SELECT order_sysid,item_code,SUM(qty_retur) as qty_retur FROM t_item_invoice2
            WHERE order_sysid=? AND is_cn=1
            GROUP BY order_sysid,item_code) b ON a.sysid=b.order_sysid AND a.item_code=b.item_code
            SET a.qty_retur=-b.qty_retur
            WHERE a.sysid=?",
            [$invoice->order_sysid,$invoice->sysid]);


            DB::UPDATE("UPDATE t_item_invoice1 a
                INNER JOIN
                (SELECT sysid,SUM(qty_invoice*purchase_price) AS amount,SUM(amount_discount1) AS discount1,SUM(amount_discount2) AS discount2,SUM(amount_tax) AS tax,SUM(total) AS total
                FROM t_item_invoice2
                WHERE sysid=? GROUP BY sysid)  b ON a.sysid=b.sysid
                SET a.amount=b.amount,a.discount1=b.discount1,a.discount2= b.discount2,a.tax= b.tax,a.total= b.total,a.payment=b.total,a.unpaid=0
                WHERE a.sysid=?",[$invoice->sysid,$invoice->sysid]);

            $return=ItemInvoice1::selectRaw("SUM(ABS(total)) as total")
            ->where('sysid',$invoice->order_sysid)
            ->where('is_credit_note',1)
            ->first();

            $total = isset($return->total) ? floatval($return->total) : 0;
            $Inv->payment = $Inv->payment + $total;
            $Inv->save();

            DB::update("UPDATE t_customer_account as a
            INNER JOIN t_item_invoice1 b ON a.sysid=b.sysid
            SET a.total_paid=b.payment
            WHERE ref_sysid=?  AND doc_source=?",[$Inv->sysid,'LPB']);

            $respon=Inventory::ItemCard($sysid,'RPB',$opr,true,false,true);

            if (!($respon['success']==true)){
                DB::rollback();
                return response()->error('',501,$respon['message']);
            }

            $info=$this->build_jurnal($sysid,$request);
            if (!($info['state']==true)){
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }

            $acc=Accounting::Config();
            $ap_invoice=$acc->ap_invoice;
            Accounting::create_customer_account($sysid,'LPB',$ap_invoice);
            DB::commit();
            $respon=[
                'uuid'=>$invoice->uuid_rec,
                'message'=>"Simpan data berhasil"
            ];
            return response()->success('Success',$respon);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e->getMessage());
        }
    }

    public function print(Request $request){
        $item = array();
        $uuid=$request->uuid ??'';

        $header=ItemInvoice1::from('t_item_invoice1 as a')
        ->selectRaw("
            a.sysid,
            a.doc_number,
            a.ref_document,
            a.order_document,
            a.partner_name,
            a.ref_date,
            a.due_date,
            ABS(a.amount) as amount,
            ABS(a.discount1) as discount1,
            ABS(a.tax) as tax,
            ABS(a.total) as net_total,
            concat(a.trans_code,'-',a.trans_series) as voucher,
            a.pool_code,
            a.update_userid,
            a.update_timestamp,
            IFNULL(b.descriptions,'') as warehouse_name,
            c.user_name")
        ->leftjoin('m_warehouse as b','a.warehouse_id','=','b.warehouse_id')
        ->leftJoin('o_users as c','a.update_userid','=','c.user_id')
        ->where('a.uuid_rec',$uuid)->first();

        if (!$header) {
            return response()->error('', 501, 'Data Tidak ditemukan');
        }

        $sysid=$header->sysid;
        $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
        $header->due_date=date_format(date_create($header->due_date),'d-m-Y');
        $sign=array();
        $sign['user_sign']='';

        $user=Users::select('sign')->where('user_id',$header->update_userid)->first();
        $sign['user_sign']=isset($user->sign) ? storage_path().'/app/'.$user->sign :'';

        $detail=ItemInvoice2::from('t_item_invoice2 as a')
        ->selectRaw("
            a.line_no,
            a.item_code,
            a.descriptions,
            a.mou_purchase,
            ABS(a.qty_invoice) as qty_invoice,
            a.purchase_price,
            a.prc_discount1,
            a.prc_tax,
            ABS(a.total) as total,
            IFNULL(b.part_number,'') as part_number")
        ->leftJoin('m_item as b','a.item_code','=','b.item_code')
        ->orderby('a.line_no','asc')
        ->where('a.sysid',$sysid)->get();

        $profile=PagesHelp::Profile();
        $pdf = PDF::loadView('finance.invoice-retur',[
            'header'=>$header,
            'detail'=>$detail,
            'profile'=>$profile,
            'sign'=>$sign])
        ->setPaper('A4','potriat');
        return $pdf->stream();
    }

public function upload_document(Request $request) {
    $uuid = $request->uuid ?? '';
    $uploadedFile = $request->file('file');

    if (!$uploadedFile || !$uuid) {
        return response()->error('', 400, 'File or UUID is missing');
    }

    $originalFile = $uploadedFile->getClientOriginalName();
    $timestampedFile = date('Ymd-His') . "-" . $originalFile;

    $doc = ItemInvoice1::selectRaw('sysid, ref_date')
        ->where('uuid_rec', $uuid)
        ->first();

    if ($doc) {
        $sysid = $doc->sysid;
        $directory = "public/invoice/" . substr($doc->ref_date, 0, 4);
        $path = $uploadedFile->storeAs($directory, $timestampedFile);

        ItemInvoice1::where('sysid', $sysid)
            ->update(['invoice_path' => $path, 'doc_name' => $originalFile]);

        CustomerAccount::where('uuid_invoice', $uuid)
            ->update(['invoice_path' => $path, 'doc_name' => $originalFile]);

        return response()->success('success', [
            'path_file' => $path,
            'message' => 'Upload dokumen berhasil'
        ]);
    } else {
        return response()->error('', 501, [
            'path_file' => '',
            'message' => 'Data tidak ditemukan'
        ]);
    }
}


    public function download_document(Request $request)
    {
        $uuid  = isset($request->uuid) ? $request->uuid : '';
        $doc=ItemInvoice1::selectRaw('sysid,doc_name,invoice_path,ref_date')
        ->where('uuid_rec',$uuid)->first();
        if ($doc) {
            $file=$doc->invoice_path;
            $publicPath = \Storage::url($file);
            $backfile =$doc->doc_name;
            return Storage::download($file, $backfile,[]);
        } else {
            return response()->error('',301,'Dokumen tidak ditemukan');
        }
    }

    public function getInvoice(Request $request){
        $partner_code = $request->partner_code ?? '-';
        $filter       = $request->filter;
        $limit        = $request->limit;
        $sorting      = $request->descending === "false" ? "asc" : "desc";
        $sortBy       = $request->sortBy;

        $data=ItemInvoice1::selectRaw(
            "sysid,
            doc_number,
            ref_date,
            due_date,
            ref_document,
            order_document,
            partner_code,
            partner_name,
            amount,
            total,
            warehouse_id"
        )
        ->where('partner_code',$partner_code)
        ->whereRaw('IFNULL(payment,0)=0')
        ->where('is_void','0')
        ->where('is_credit_note','0');

        if (!empty($filter)) {
            $filter = '%' . trim($filter) . '%';
            $data->where(function($q) use ($filter) {
                $q->where('doc_number', 'like', $filter)
                ->orWhere('ref_document', 'like', $filter)
                ->orWhere('order_document', 'like', $filter);
            });
        }

        // Execute the query and paginate the results
        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);


        return response()->success('Success',$data);
    }

    public function getdtlinvoice(Request $request){
        $doc_number=$request->doc_number ?? '';
        $data=ItemInvoice1::from('t_item_invoice1 as a')
        ->selectRaw("
            b.sysid,
            b.line_no,
            b.item_code,
            b.price_code,
            c.part_number,
            b.descriptions,
            b.mou_purchase,
            b.convertion,
            b.mou_inventory,
            b.qty_invoice-b.qty_retur as qty_invoice,
            b.purchase_price,
            b.prc_discount1,
            b.prc_discount2,
            b.prc_tax,
            b.total"
        )
        ->leftJoin('t_item_invoice2 as b', 'b.sysid', '=', 'a.sysid')
        ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
        ->where('a.doc_number',$doc_number)
        ->get();
        return response()->success('Success',$data);
    }

    public static function build_jurnal($sysid,$request) {
        /* Hutang
             Persediaan
         */
        $ret= [
            'state'=> true,
            'message' => ''
            ];

        $data=ItemInvoice1::selectRaw('pool_code,ref_document,doc_number,partner_name,ref_date,
        sysid_jurnal,trans_code,trans_series')
        ->where('sysid',$sysid)
        ->first();

        if ($data){
            $pool_code=$data->pool_code;
            $detail=ItemInvoice2::from('t_item_invoice2 as a')
            ->selectRaw("
                a.item_code,
                a.descriptions,
                a.price_cost,
                c.inv_account,
                a.qty_invoice,
                a.purchase_price,
                a.total,
                IFNULL(b.item_group, '') as item_group,
                a.warehouse_id
            ")
            ->leftjoin('m_item as b','a.item_code','=','b.item_code')
            ->leftJoin('m_item_group_account as c', function($join) use($pool_code)
                {
                    $join->on('b.item_group', '=', 'c.item_group');
                    $join->on('c.pool_code','=',DB::raw("'$pool_code'"));
                })
            ->where('a.sysid',$sysid)
            ->get();

            $realdate     = date_create($data->ref_date);
            $year_period  = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            $jurnal=Journal1::where('sysid',$data->sysid_jurnal)->first();
            if ((!$jurnal) || ($data->sysid_jurnal==-1)) {
                $jurnal=new Journal1();
                $jurnal->trans_code   = 'INV';
                $jurnal->trans_series = Journal1::GenerateNumber('INV',$data->ref_date);
            } else {
                Accounting::rollback($data->sysid_jurnal);
            }
            $jurnal->fill([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->ref_document,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'6',
                  'notes'=>'RETUR PEMBELIAN BARANG. '.$data->ref_document.' '.$data->partner_name
            ]);
            $jurnal->save();
            $sysid_jurnal=$jurnal->sysid;

            $acc=Accounting::Config();
            $ap_invoice=$acc->ap_purchase_account;
            /* Inventory */
            $line =1;
            $AccountPayable  = 0;
            foreach($detail as $row){
                $line++;
                $account = Accounting::inventory_account($row->warehouse_id,$row->item_group)->inv_account;
                Journal2::insert([
                    'sysid'     => $sysid_jurnal,
                    'line_no'   => $line,
                    'no_account'=> $account,
                    'line_memo' => $row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty_invoice,2,",",".").
                                ', Harga : '.number_format($row->purchase_price,2,",",".").')',
                    'reference1'=> $data->doc_number,
                    'reference2'=> $data->ref_document,
                    'debit'     => 0,
                    'credit'    => $row->total,
                    'project'   => '00'
                ]);
                $AccountPayable = $AccountPayable + floatval($row->total);
            }

            Journal2::insert([
                'sysid'     =>$sysid_jurnal,
                'line_no'   =>1,
                'no_account'=>isset($ap_invoice) ? $ap_invoice:'-',
                'line_memo' =>'Retur Pembelian Barang. '.$data->ref_document.' '.$data->partner_name,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->ref_document,
                'debit'     =>$AccountPayable,
                'credit'    =>0,
                'project'   =>'00'
            ]);


            $info=Accounting::Posting($sysid_jurnal,$request);
            if ($info['state']==true){
                ItemInvoice1::where('sysid',$sysid)
                ->update([
                    'sysid_jurnal'=>$sysid_jurnal,
                    'trans_code'=>$jurnal->trans_code,
                    'trans_series'=>$jurnal->trans_series
                ]);
            }
            $ret = [
                'state'=>$info['state'],
                'message'=>$info['message']
            ];
        } else {
            $ret = [
                'state'=>false,
                'message'=>'Data Tidak Ditemukan'
            ];
        }
        return $ret;
    }

    public function query(Request $request)
    {
        $filter  = $request->filter;
        $limit   = $request->limit ?? 50;
        $sorting = $request->descending == "false" ?'asc' :'desc';
        $sortBy  = $request->sortBy ?? 'doc_number';
        $start_date   = $request->start_date;
        $end_date     = $request->end_date;
        $warehouse_id = $request->warehouse_id;
        $data= ItemInvoice1::from('t_item_invoice1 as a')
        ->selectRaw("(a.sysid*10000)+b.line_no as _index,a.doc_number,a.ref_date,d.ref_date AS order_date,a.order_document,a.ref_document,
                    a.partner_code,a.partner_name,b.item_code,b.descriptions,c.part_number,b.qty_order,b.qty_invoice,b.mou_purchase,
                    b.purchase_price,b.prc_discount1,b.prc_tax,b.total,a.pool_code,a.update_userid,a.update_timestamp")
        ->leftJoin('t_item_invoice2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->leftJoin('t_purchase_order1 as d','a.order_sysid','=','d.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_void','0');
        if (!($warehouse_id=='ALL')){
            $data=$data->where('a.warehouse_id',$warehouse_id);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
            $q->where('a.doc_number', 'like', $filter)
                ->orwhere('a.order_document', 'like', $filter)
                ->orwhere('a.partner_code', 'like', $filter)
                ->orwhere('a.partner_name', 'like', $filter)
                ->orwhere('a.item_code', 'like', $filter)
                ->orwhere('a.part_number', 'like', $filter)
                ->orwhere('a.pool_code', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }

    public function report(Request $request)
    {
        // Validate incoming request parameters
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'nullable|string',
        ]);

        // Extract parameters
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $warehouse_id = $request->warehouse_id;

        // Build the query
        $query = ItemInvoice1::from('t_item_invoice1 as a')
            ->selectRaw("a.sysid + b.line_no as _index, a.doc_number, a.ref_date, d.ref_date AS order_date,
                        a.order_document, a.ref_document, a.partner_code, a.partner_name,
                        b.item_code, c.part_number, b.descriptions, b.qty_order, b.qty_invoice,
                        b.mou_purchase, b.purchase_price, b.prc_discount1, b.prc_tax, b.total,
                        a.pool_code, a.update_userid, a.update_timestamp, a.warehouse_id")
            ->leftJoin('t_item_invoice2 as b', 'a.sysid', '=', 'b.sysid')
            ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
            ->leftJoin('t_purchase_order1 as d', 'a.order_sysid', '=', 'd.sysid')
            ->where('a.ref_date', '>=', $start_date)
            ->where('a.ref_date', '<=', $end_date)
            ->where('a.is_void', '0');

        // Apply warehouse filter if specified
        if ($warehouse_id !== 'ALL') {
            $query->where('a.warehouse_id', $warehouse_id);
        }

        // Retrieve the data
        $data = $query->get();

        // Create the spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        // Set report header
        $sheet->setCellValue('A1', 'LAPORAN DETAIL PEMBELIAN')
            ->setCellValue('A2', 'PERIODE')
            ->setCellValue('B2', ': ' . date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)))
            ->setCellValue('A3', 'GUDANG')
            ->setCellValue('B3', ': ' . ($warehouse_id === 'ALL' ? 'SEMUA GUDANG' : $warehouse_id))
            ->setCellValue('A5', 'No.Bukti')
            ->setCellValue('B5', 'Tanggal')
            ->setCellValue('C5', 'Tgl.Order')
            ->setCellValue('D5', 'No.Order')
            ->setCellValue('E5', 'Invoice Supplier')
            ->setCellValue('F5', 'Nama Supplier')
            ->setCellValue('G5', 'Kode Item')
            ->setCellValue('H5', 'No. Part')
            ->setCellValue('I5', 'Nama Barang')
            ->setCellValue('J5', 'Jml.Order')
            ->setCellValue('K5', 'Jml.Invoice')
            ->setCellValue('L5', 'Satuan')
            ->setCellValue('M5', 'Harga')
            ->setCellValue('N5', 'Diskon')
            ->setCellValue('O5', 'PPN')
            ->setCellValue('P5', 'Total')
            ->setCellValue('Q5', 'Gudang')
            ->setCellValue('R5', 'User Input')
            ->setCellValue('S5', 'Tgl.Input')
            ->getStyle('A5:S5')->getAlignment()->setHorizontal('center');

        // Fill in the data
        foreach ($data as $idx => $row) {
            $idx += 6; // Start from row 6
            $sheet->setCellValue('A' . $idx, $row->doc_number)
                ->setCellValue('B' . $idx, $row->ref_date)
                ->setCellValue('C' . $idx, $row->order_date)
                ->setCellValue('D' . $idx, $row->order_document)
                ->setCellValueExplicit('E' . $idx, $row->ref_document, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                ->setCellValue('F' . $idx, $row->partner_name)
                ->setCellValueExplicit('G' . $idx, $row->item_code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                ->setCellValueExplicit('H' . $idx, $row->part_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                ->setCellValue('I' . $idx, $row->descriptions)
                ->setCellValue('J' . $idx, $row->qty_order)
                ->setCellValue('K' . $idx, $row->qty_invoice)
                ->setCellValue('L' . $idx, $row->mou_purchase)
                ->setCellValue('M' . $idx, $row->purchase_price)
                ->setCellValue('N' . $idx, $row->prc_discount1)
                ->setCellValue('O' . $idx, $row->prc_tax)
                ->setCellValue('P' . $idx, $row->total)
                ->setCellValue('Q' . $idx, $row->warehouse_id)
                ->setCellValue('R' . $idx, $row->update_userid)
                ->setCellValue('S' . $idx, $row->update_timestamp);
        }

        // Apply number formatting
        $sheet->getStyle('B6:B' . ($idx + 5))->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('C6:C' . ($idx + 5))->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('S6:S' . ($idx + 5))->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');

        // Calculate totals
        $lastRow = $idx + 5; // Adjust for total row
        $sheet->setCellValue('O' . ($idx + 6), "TOTAL")
            ->setCellValue('P' . ($idx + 6), "=SUM(P6:P$lastRow)");

        // Set number formats for totals
        $sheet->getStyle('J6:M' . ($idx + 6))->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('N6:O' . ($idx + 6))->getNumberFormat()->setFormatCode('#,##0.00;[RED](#,##0.00)');
        $sheet->getStyle('P6:P' . ($idx + 6))->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');

        // Formatting styles
        $sheet->getStyle('A1:Q5')->getFont()->setBold(true);
        $sheet->getStyle('A' . ($idx + 6) . ':S' . ($idx + 6))->getFont()->setBold(true);

        // Apply borders
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A5:S' . ($idx + 6))->applyFromArray($styleArray);

        // Auto size columns
        foreach (range('C', 'S') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(12);

        ob_end_clean(); // Clear the output buffer

        // Stream the download response
        return response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        }, 'laporan_penerimaan_barang_' . date('Ymd', strtotime($start_date)) . '_' . date('Ymd', strtotime($end_date)) . '.xlsx');
    }

}
