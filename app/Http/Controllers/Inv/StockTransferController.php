<?php

namespace App\Http\Controllers\Inv;

use App\Models\Inventory\StockTransfer1;
use App\Models\Inventory\StockTransfer2;
use App\Models\Inventory\StockRequest1;
use App\Models\Inventory\StockRequest2;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Master\Itemgroups;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PagesHelp;
use Inventory;
use Accounting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;

class StockTransferController extends Controller
{
    public function show(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy  = $request->sortBy;
        $warehouse=isset($request->warehouse_id) ? $request->warehouse_id:'';
        $date1 = $request->date1;
        $date2 = $request->date2;
        $data= StockTransfer1::selectRaw(
            "sysid,
            doc_number,
            reference,
            ref_date,
            warehouse_src,
            warehouse_dest,
            received_sysid,
            received_doc_number,
            received_date,
            notes,
            is_canceled,
            canceled_date,
            canceled_by,
            inventory_cost,
            sysid_jurnal,
            CONCAT(trans_code,'-',trans_series) as voucher,
            uuid_rec"
        );
        $data=$data
           ->where('warehouse_src',$warehouse)
           ->where('ref_date','>=',$date1)
           ->where('ref_date','<=',$date2)
           ->where('transfer_type','OUT');

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('reference','like',$filter)
                   ->orwhere('received_doc_number','like',$filter);
            });
        }

        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }
    public function get(Request $request){
        $uuid=$request->uuid ?? '';
        $header=StockTransfer1::selectRaw(
            "sysid,
            doc_number,
            reference,
            ref_date,
            warehouse_src,
            warehouse_dest,
            received_sysid,
            received_doc_number,
            received_date,
            notes,
            is_canceled,
            canceled_date,
            canceled_by,
            inventory_cost,
            sysid_jurnal,
            CONCAT(trans_code,'-',trans_series) as voucher,
            uuid_rec")->where('uuid_rec',$uuid)->first();

        if (!$header){
            return response()->error('',501,'Data Tidak ditemukan');
        }

        $received=false;
        $canceled=false;

        if (!($header->received_sysid==-1)) {
            $message='Tansfer stock barang sudah diterima ';
            $received=true;
        }
        if ($header->is_canceled=='1') {
            $message ='Tansfer stock barang dibatalkan ';
            $canceled=true;
        }

        $detail=StockTransfer2::from('t_inventory_transfer2 as a')
        ->select('a.sysid','a.line_no','a.item_code','a.descriptions','a.qty_request','a.qty_item',
            'a.itemcost','a.line_cost','b.part_number','a.notes','a.mou_inventory')
        ->leftJoin('m_item as b','a.item_code','=','b.item_code')
        ->where('sysid',$header->sysid)->get();

        $data=[
            'header'=>$header,
            'detail'=>$detail ,
            'message'=>$message ??'',
            'canceled'=>$canceled ?? false,
            'received'=>$received ?? false
        ];
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $uuid=$request->uuid ?? '';
        $data=StockTransfer1::where('uuid_rec',$uuid)->first();
        if (!$data) {
            return response()->error('',501,'Data tidak ditemukan');
        }
        if (!($data->received_sysid==-1)) {
            return response()->error('',201,'Transfer stock barang sudah diterima');
            exit;
        }
        if ($data->is_canceled=='1') {
            return response()->error('',201,'Transfer stock barang sudah dibatalkan');
            exit;
        }

        DB::beginTransaction();
        try {
            StockTransfer1::where('sysid',$data->sysid)
            ->update([
                'is_canceled'=>1,
                'canceled_by'=>PagesHelp::UserID($request),
                'canceled_date'=>now()
            ]);

            $respon=Inventory::ItemCard($sysid,'ITO','deleted',true,false,true);
            if ($respon['success']<>true){
                DB::rollback();
                return response()->error('',501,$respon['message']);
            }

            $info=$this->build_jurnalvoid($sysid,$request);
            if ($info['state']==true){
                DB::commit();
                return response()->success('Success','Transfer stock barang berhasil dibatalkan');
            } else {
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }
        } catch(Exception $e){
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function post(Request $request){
        $data   = $request->json()->all();
        $header = $data['header'];
        $detail = $data['detail'];
        $header['pool_code']=PagesHelp::PoolCode($request);

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'warehouse_src'=>'bail|required',
            'warehouse_dest'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'warehouse_src.required'=>'Gudang asal harus diisi',
            'warehouse_dest.required'=>'Gudang tujuan harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if ($header['warehouse_src']==$header['warehouse_dest']){
            return response()->error('',501,'Asal : '.$header['warehouse_src'].', Tujuan :'.$header['warehouse_dest'].' stock tidak boleh sama');
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.qty_item'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_item.min'=>'Jumlah stock harus diisi/lebih besar dari NOL'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $transfer1=StockTransfer1::where('uuid_rec',$header['uuid_rec'] ?? '')->first();
            if (!$transfer1) {
                $transfer1= new StockTransfer1();
                $transfer1->uuid_rec     = Str::uuid();
                $transfer1->doc_number   = StockTransfer1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $transfer1->transfer_type= 'OUT';
                $transfer1->post_date    = Date('Y-m-d');
                $transfer1->is_canceled  = '0';
                $opr='inserted';
            } else {
                if ($transfer1->is_canceled=='1') {
                    return response()->error('',202,'Transfer stock barang sudah dibatalkan');
                    exit;
                } else if (!($transfer1->received_sysid=='-1')) {
                    return response()->error('',201,'Transfer stock barang sudah diterima');
                    exit;
                }
                $opr='updated';
                StockTransfer2::where('sysid',$transfer1->sysid)->delete();
            }
            $transfer1->fill([
                'pool_code'=>$header['pool_code'],
                'ref_date'=>$header['ref_date'],
                'reference'=>$header['reference']??'-',
                'warehouse_src'=>$header['warehouse_src'],
                'warehouse_dest'=>$header['warehouse_dest'],
                'received_sysid'=>$header['received_sysid']?? -1,
                'received_doc_number'=>$header['received_doc_number'] ?? '',
                'update_userid'=>PagesHelp::Session()->user_id,
                'update_timestamp'=>Date('Y-m-d H:i:s')
            ]);
            $transfer1->save();
            $sysid = $transfer1->sysid;

            foreach($detail as $line) {
                StockTransfer2::insert([
                    'sysid'=>$sysid,
                    'line_no'=>$line['line_no'],
                    'item_code'=>$line['item_code'],
                    'descriptions'=>$line['descriptions'],
                    'mou_inventory'=>$line['mou_inventory'],
                    'qty_request'=>$line['qty_request'],
                    'qty_item'=>$line['qty_item'],
                    'itemcost'=>0,
                    'line_cost'=>0,
                    'warehouse_src'=>$transfer1->warehouse_src,
                    'warehouse_dest'=>$transfer1->warehouse_dest,
                ]);
            }

            if (!($header['reference']=='-')) {
                DB::update(
                    "UPDATE t_inventory_transfer1 a
                    INNER JOIN t_inventory_request1 b ON a.reference=b.doc_number
                    SET a.sysid_request=b.sysid
                    WHERE a.sysid=?",
                    [$sysid]);

                DB::update(
                    "UPDATE t_inventory_request1
                    SET is_processed=1
                    WHERE doc_number=?",
                    [$transfer1->reference]);
            }

            $respon=Inventory::ItemCard($sysid,'ITO',$opr,false,false);

            if ($respon['success']<>true){
                DB::rollback();
                return response()->error('',501,$respon['message']);
            }
            DB::update(
                "UPDATE t_inventory_transfer2 a
                INNER JOIN
                (SELECT sysid,item_code,SUM(qty*item_cost) as line_cost FROM t_item_price
                WHERE sysid=? AND doc_type='ITO' GROUP BY sysid,item_code) b ON a.sysid=b.sysid AND a.item_code=b.item_code
                SET
                a.line_cost=ABS(b.line_cost),
                a.itemcost=ABS(b.line_cost/a.qty_item)
                WHERE a.sysid=?",
                [$sysid,$sysid]);

            DB::update(
                "UPDATE t_inventory_transfer1 a INNER JOIN
                    (SELECT sysid,SUM(line_cost) as line_cost FROM t_inventory_transfer2
                    WHERE sysid=? GROUP BY sysid) b ON a.sysid=b.sysid
                SET
                a.inventory_cost=b.line_cost
                WHERE a.sysid=?",[$sysid,$sysid]);

            $info=$this->build_jurnal($sysid,$request);
            if ($info['state']==true){
                DB::commit();
                return response()->success('Success', 'Simpan data Berhasil');
            } else {
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function print(Request $request){
        $uuid=$request->uuid ?? '';

        $header=StockTransfer1::from('t_inventory_transfer1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.pool_code,a.warehouse_dest,a.ref_date,a.notes,b.descriptions as warehouse_name,
                    c.descriptions as warehouse_source,e.user_name,a.reference")
        ->leftJoin('m_warehouse as b','a.warehouse_dest','=','b.warehouse_id')
        ->leftJoin('m_warehouse as c','a.warehouse_src','=','c.warehouse_id')
        ->leftJoin('o_users as e','a.update_userid','=','e.user_id')
        ->where('a.uuid_rec',$uuid)->first();

        if (!$header) {
            return response()->error('',501,'Data Tidak ditemukan');
        }

        $detail=StockTransfer1::from('t_inventory_transfer2 as a')
        ->selectRaw("a.line_no,a.item_code,a.descriptions,a.qty_request,a.mou_inventory,
                    a.qty_item,b.part_number")
        ->leftjoin("m_item as b",'a.item_code','=','b.item_code')
        ->where("a.sysid",$header->sysid)
        ->orderBy("a.line_no","asc")
        ->get();

        $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
        $profile=PagesHelp::Profile();
        $pdf = PDF::loadView('inventory.StockTransfer',
        ['header'=>$header,
        'detail'=>$detail,
        'profile'=>$profile])
        ->setPaper('A4','potriat');
        return $pdf->stream();
    }

    public function Request(Request $request) {
        $pool_code=PagesHelp::PoolCode($request);
        $warehouse=isset($request->warehouse_id) ? $request->warehouse_id:'';
        $data=StockRequest1::from('t_inventory_request1 as a')
        ->selectRaw("a.doc_number,CONCAT(a.doc_number,' [',DATE_FORMAT(a.ref_date,'%d-%m-%Y') ,'] ', b.descriptions) AS descriptions")
        ->leftjoin('m_warehouse as b','a.warehouse_request','=','b.warehouse_id')
        ->where('a.warehouse_id',$warehouse)
        ->where('a.is_processed',0)
        ->where('a.is_canceled',0)
        ->where('a.is_transferstock',1)
        ->orderBy('ref_date','desc')
        ->offset(0)
        ->limit(100)
        ->get();
        return response()->success('Success',$data);
    }

    public function request2(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $warehouse=isset($request->warehouse_id) ? $request->warehouse_id:'';
        $data= StockRequest1::selectRaw('sysid,doc_number,pool_code,ref_document,ref_date,
            warehouse_id,warehouse_request,service_no,notes,created_by,created_date,is_authorize,
            authorize_date,authorize_by,is_processed,priority,is_canceled,canceled_date,canceled_by')
            ->where('warehouse_id',$warehouse)
            ->where('is_processed',0)
            ->where('is_canceled',0)
            ->where('is_transferstock',1);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('notes','like',$filter);
            });
        }
        if (!($sortBy=='')) {
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        } else {
            $data=$data->paginate($limit);
        }
        return response()->success('Success',$data);
    }
    public function dtlRequest(Request $request){
        $doc_number=$request->doc_number;
        $data=StockRequest1::from('t_inventory_request1 as a')
        ->selectRaw("b.item_code,c.part_number,b.descriptions,b.qty_request,b.qty_stock,b.qty_supply,b.qty_cancel,b.mou_inventory,IFNULL(d.on_hand,0) as on_hand")
        ->leftJoin('t_inventory_request2 as b', 'a.sysid', '=', 'b.sysid')
        ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
        ->leftjoin("m_item_stock as d", function($join){
            $join->on("b.item_code","=","d.item_code");
            $join->on("b.warehouse_id","=","d.warehouse_id");
        })
        ->where('a.doc_number',$doc_number)
        ->get();
        return response()->success('Success',$data);
    }
    public static function build_jurnal($sysid,$request) {
        /* Inventory On Transfer
             Inventory
         */
        $ret=[
            'state'=>true,
            'message'=>''
        ];
        $data=StockTransfer1::selectRaw('pool_code,reference,doc_number,ref_date,
        sysid_jurnal,trans_code,trans_series,warehouse_src,warehouse_dest')
        ->where('sysid',$sysid)->first();

        if ($data){
            $pool_code = $data->pool_code;
            $detail=StockTransfer2::from('t_inventory_transfer2 as a')
            ->selectRaw('a.item_code,c.item_group,a.descriptions,ABS(b.qty) as qty,b.item_cost,ABS(b.qty)*b.item_cost as line_cost,d.inv_account')
            ->join('t_item_price as b', function($join)
                {
                    $join->on('a.sysid', '=', 'b.sysid');
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.doc_type','=',DB::raw("'ITO'"));
                    $join->on('b.is_deleted','=',DB::raw("0"));
                })
            ->leftjoin('m_item as c','a.item_code','=','c.item_code')
            ->leftJoin('m_item_group_account as d', function($join) use($pool_code)
                {
                    $join->on('c.item_group', '=', 'd.item_group');
                    $join->on('d.pool_code','=',DB::raw("'$pool_code'"));
                })
            ->where('a.sysid',$sysid)
            ->get();

            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            if ($data->sysid_jurnal==-1){
                $series = Journal1::GenerateNumber('ITO',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'8',
                  'trans_code'=>'ITO',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'8',
                  'notes'=>'Transfer stock. '.$data->reference.', dari '.$data->warehouse_src.' tujuan '.$data->warehouse_dest
              ]);
            } else {
                $sysid_jurnal=$data->sysid_jurnal;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'8',
                  'notes'=>'Void Transfer stock. '.$data->reference.', tujuan '.$data->warehouse_dest
                ]);
            }
            $acc=Accounting::Config();
            $inventory_ontransfer=$acc->inventory_transfer_account;
            /* Inventory */
            $line=1;
            $ontransfer   = 0;
            $project_code = PagesHelp::Project($data->pool_code);

            foreach($detail as $row){
                $row->inv_account=Accounting::inventory_account($data->warehouse_src,$row->item_group)->inv_account;
                $line++;
                Journal2::insert([
                    'sysid'     => $sysid_jurnal,
                    'line_no'   => $line,
                    'no_account'=> $row->inv_account,
                    'line_memo' => 'Transfer stock '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=> $data->doc_number,
                    'reference2'=> $data->reference,
                    'debit'     => 0,
                    'credit'    => $row->line_cost,
                    'project'   => $project_code,
                ]);
                $ontransfer = $ontransfer + floatval($row->line_cost);
            }
            $line=1;
            Journal2::insert([
                'sysid'     => $sysid_jurnal,
                'line_no'   => $line,
                'no_account'=> $inventory_ontransfer,
                'line_memo' => 'Transfer stock. '.$data->reference.', tujuan '.$data->warehouse_dest,
                'reference1'=> $data->doc_number,
                'reference2'=> $data->reference,
                'debit'    => $ontransfer,
                'credit'   => 0,
                'project'  => $project_code
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                StockTransfer1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'ITO',
                'trans_series'=>$series]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        } else {
            $ret['state']=false;
            $ret['message']='Data tidak ditemukan';
        }
        return $ret;
    }
    public static function build_jurnalvoid($sysid,$request) {
        /* Inventory On Transfer
             Inventory
         */
        $ret= [
            'state'=>true,
            'message'=>''
        ];

        $data=StockTransfer1::selectRaw('pool_code,reference,doc_number,DATE(canceled_date) as ref_date,
        sysid_void,trans_code_void,trans_series_void,warehouse_src,warehouse_dest')
        ->where('sysid',$sysid)->first();
        if ($data){
            $pool_code=$data->pool_code;
            $detail=StockTransfer2::from('t_inventory_transfer2 as a')
            ->selectRaw('a.item_code,c.item_group,a.descriptions,ABS(b.qty) as qty,b.item_cost,ABS(b.qty)*b.item_cost as line_cost,d.inv_account')
            ->join('t_item_price as b', function($join)
                {
                    $join->on('a.sysid', '=', 'b.sysid');
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.doc_type','=',DB::raw("'ITO'"));
                    $join->on('b.is_deleted','=',DB::raw("0"));
                })
            ->leftjoin('m_item as c','a.item_code','=','c.item_code')
            ->leftJoin('m_item_group_account as d', function($join) use($pool_code)
                {
                    $join->on('c.item_group', '=', 'd.item_group');
                    $join->on('d.pool_code','=',DB::raw("'$pool_code'"));
                })
            ->where('a.sysid',$sysid)
            ->get();
            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_void==-1){
                $series = Journal1::GenerateNumber('ITO',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'    => $data->ref_date,
                  'pool_code'   => $data->pool_code,
                  'reference1'  => $data->doc_number,
                  'reference2'  => $data->reference,
                  'posting_date'=> $data->ref_date,
                  'is_posted'   => '8',
                  'trans_code'  => 'ITO',
                  'trans_series'=> $series,
                  'fiscal_year' => $year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'   => '8',
                  'notes'=>'Void Transfer stock. '.$data->reference.', tujuan '.$data->warehouse_dest
              ]);
            } else {
                $sysid_jurnal=$data->sysid_void;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'    => $data->ref_date,
                  'pool_code'   => $data->pool_code,
                  'reference1'  => $data->doc_number,
                  'reference2'  => $data->reference,
                  'posting_date'=> $data->ref_date,
                  'is_posted'   => '1',
                  'fiscal_year' => $year_period,
                  'fiscal_month'=> $month_period,
                  'transtype'   => '8',
                  'notes'=>'Void Transfer stock. '.$data->reference.', tujuan '.$data->warehouse_dest
                ]);
            }
            $acc=Accounting::Config();
            $inventory_ontransfer=$acc->inventory_transfer_account;
            /* Inventory */
            $line=0;
            $ontransfer=0;
            foreach($detail as $row){
                $row->inv_account=Accounting::inventory_account($data->warehouse_src,$row->item_group)->inv_account;
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->inv_account,
                    'line_memo'=>'Void Transfer stock '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>$row->line_cost,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $ontransfer = $ontransfer + floatval($row->line_cost);
            }
            $line=$line+1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$inventory_ontransfer,
                'line_memo'=>'Void Transfer stock. '.$data->reference.', tujuan '.$data->warehouse_dest,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->reference,
                'debit'=>0,
                'credit'=>$ontransfer,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                StockTransfer1::where('sysid',$sysid)
                ->update([
                    'sysid_void'=>$sysid_jurnal,
                    'trans_code_void'=>'ITO',
                    'trans_series_void'=>$series
                ]);
            }
            $ret =[
                'state'=>$info['state'],
                'message'=>$info['message']
            ];
        } else {
            $ret =[
                'state'=>false,
                'message'=>'Data tidak ditemukan'
            ];
        }
        return $ret;
    }
    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $warehouse_id = $request->warehouse_id;
        $data= StockTransfer1::from('t_inventory_transfer1 as a')
        ->selectRaw("(a.sysid * 10000)+b.line_no AS _index,a.doc_number,a.ref_date,
                    a.warehouse_src,a.warehouse_dest, a.reference,b.item_code,c.part_number,b.descriptions,
                    b.qty_request,b.qty_item,b.line_cost,b.qty_transfer,a.update_userid,a.update_timestamp,
                    b.doc_number_transfer,b.qty_receive,b.receive_date")
        ->leftJoin('t_inventory_transfer2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_canceled', '0')
        ->where('a.transfer_type', 'OUT');
        if (!($warehouse_id=='ALL')){
            $data=$data->where('a.warehouse_src',$warehouse_id);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.warehouse_src', 'like', $filter)
                    ->orwhere('a.warehouse_dest', 'like', $filter)
                    ->orwhere('c.part_number', 'like', $filter)
                    ->orwhere('b.item_code', 'like', $filter);
            });
        }
        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
    public function report(Request $request)
    {
        $warehouse_id = $request->warehouse_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $data= StockTransfer1::from('t_inventory_transfer1 as a')
        ->selectRaw("(a.sysid * 10000)+b.line_no AS _index,a.doc_number,a.ref_date,
                    a.warehouse_src,a.warehouse_dest, a.reference,b.item_code,c.part_number,b.descriptions,
                    b.qty_request,b.qty_item,b.line_cost,b.qty_transfer,a.update_userid,a.update_timestamp,
                    b.doc_number_transfer,b.qty_receive,b.receive_date")
        ->leftJoin('t_inventory_transfer2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_canceled', '0')
        ->where('a.transfer_type', 'OUT');
        if (!($warehouse_id=='ALL')){
            $data=$data->where('a.warehouse_src',$warehouse_id);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN TRANSFER STOCK');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));

        $sheet->setCellValue('A5', 'No.Dokumen');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'Gudang Asal');
        $sheet->setCellValue('D5', 'Gudang Tujuan');
        $sheet->setCellValue('E5', 'Kode Item');
        $sheet->setCellValue('F5', 'Part Number');
        $sheet->setCellValue('G5', 'Nama Barang/Item');
        $sheet->setCellValue('H5', 'Jml.Permintaan');
        $sheet->setCellValue('I5', 'Jml.Transfer');
        $sheet->setCellValue('J5', 'Nilai Stock');
        $sheet->setCellValue('K5', 'No.Penerimaan');
        $sheet->setCellValue('L5', 'Tanggal');
        $sheet->setCellValue('M5', 'Jumlah Terima');
        $sheet->setCellValue('N5', 'User Input');
        $sheet->setCellValue('O5', 'Tgl.Input');
        $sheet->getStyle('A5:O5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->warehouse_src);
            $sheet->setCellValue('D'.$idx, $row->warehouse_dest);
            $sheet->setCellValue('E'.$idx, $row->item_code);
            $sheet->setCellValue('F'.$idx, $row->part_number);
            $sheet->setCellValue('G'.$idx, $row->descriptions);
            $sheet->setCellValue('H'.$idx, $row->qty_request);
            $sheet->setCellValue('I'.$idx, $row->qty_item);
            $sheet->setCellValue('J'.$idx, $row->line_cost);
            $sheet->setCellValue('K'.$idx, $row->doc_number_transfer);
            $sheet->setCellValue('L'.$idx, $row->receive_date);
            $sheet->setCellValue('M'.$idx, $row->qty_receive);
            $sheet->setCellValue('N'.$idx, $row->update_userid);
            $sheet->setCellValue('O'.$idx, $row->update_timestamp);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('C6:G'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('H6:J'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('O6:O'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        // Formater
        $sheet->getStyle('A1:O5')->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:O'.$idx)->applyFromArray($styleArray);
        foreach(range('C','O') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_transferstock_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

}
