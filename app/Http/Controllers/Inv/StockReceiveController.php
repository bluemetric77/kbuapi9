<?php

namespace App\Http\Controllers\Inv;

use App\Models\Inventory\StockTransfer1;
use App\Models\Inventory\StockTransfer2;
use App\Models\Inventory\StockRequest1;
use App\Models\Inventory\StockRequest2;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

class StockReceiveController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $warehouse=PagesHelp::Warehouse($pool_code);
        $date1 = $request->date1;
        $date2 = $request->date2;
        $data= StockTransfer1::select();
        $data=$data
           ->where('warehouse_src',$warehouse)
           ->where('ref_date','>=',$date1)
           ->where('ref_date','<=',$date2)
           ->where('transfer_type','IN');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('reference','like',$filter)
                   ->orwhere('received_doc_number','like',$filter);
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
    public function get(Request $request){
        $sysid=$request->sysid;
        $data['received']=false;
        $data['canceled']=false;
        $header=StockTransfer1::where('sysid',$sysid)->first();
        if (!($header==null)){
            if (!($header->received_sysid==-1)) {
                $data['message']='Tansfer stock barang sudah diterima ';
                $data['received']=true;
            }
            if ($header->is_canceled=='1') {
                $data['message']='Penerimaan tansfer stock barang dibatalkan ';
                $data['canceled']=true;
            }
            $data['header']=$header;
            $data['detail']=StockTransfer2::from('t_inventory_transfer2 as a')
            ->select('a.sysid','a.line_no','a.item_code','a.descriptions','a.qty_transfer','a.qty_item',
             'a.itemcost','a.line_cost','b.part_number','a.notes','a.mou_inventory')
            ->leftJoin('m_item as b','a.item_code','=','b.item_code')
            ->where('sysid',$sysid)->get();
            return response()->success('Success',$data);
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function destroy(Request $request){
        $sysid=$request->sysid;
        $data=StockTransfer1::where('sysid',$sysid)->first();
        if (!($data==null)) {
            if (!($data->received_sysid==-1)) {
                return response()->error('',201,'Transfer stock barang sudah diterima');
                exit;
            }
            if ($data->is_canceled=='1') {
                return response()->error('',201,'Penerimaan transfer stock barang sudah dibatalkan');
                exit;
            }
            DB::beginTransaction();
            try {
                StockTransfer1::where('sysid',$sysid)
                ->update([
                   'is_canceled'=>1,
                   'canceled_by'=>PagesHelp::UserID($request),
                   'canceled_date'=>now()
                ]);
                $respon=Inventory::ItemCard($sysid,'ITI','deleted',true,false,true);
                if ($respon['success']==true){
                    StockTransfer1::where('sysid',$data->sysid_request)
                    ->update([
                        'is_received'=>'0',
                        'received_sysid'=>'-1',
                        'received_doc_number'=>'',
                        'received_date'=>''
                    ]);
                    $transfers=StockTransfer2::selectRaw("item_code")
                    ->where('sysid',$sysid)->get();

                    foreach($transfers as $line) {
                        StockTransfer2::where('sysid',$data->sysid_request)
                        ->where('item_code',$line->item_code)
                        ->update([
                            'qty_receive'=>0,
                            'receive_date'=>null
                        ]);
                    }

                    $info=$this->build_jurnalvoid($sysid,$request);
                    if ($info['state']==true){
                        DB::commit();
                        return response()->success('Success','Teima transfer stock barang berhasil dibatalkan');
                    } else {
                        DB::rollback();
                        return response()->error('', 501, $info['message']);
                    }
                } else {
                    DB::rollback();
                    return response()->error('',501,$respon['message']);
                }
            } catch(Exception $e){
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
            return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $header=$data['header'];
        $detail=$data['detail'];
        $header['pool_code']=PagesHelp::PoolCode($request);
        $header['warehouse_src']=PagesHelp::Warehouse($header['pool_code']);
        $header['warehouse_dest']='-';
        $sysid=$header['sysid'];
        if ($opr=='updated'){
            $stock=StockTransfer1::where('sysid',$sysid)->first();
            if ($stock->is_canceled=='1') {
                return response()->error('',202,'Transfer stock barang sudah dibatalkan');
                exit;
            } else if (!($stock->received_sysid=='-1')) {
                return response()->error('',201,'Transfer stock barang sudah diterima');
                exit;
            }
        }
        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'warehouse_src'=>'bail|required',
            'warehouse_dest'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'warehouse_src.required'=>'Gudang asal harus diisi',
            'warehouse_dest.required'=>'Gudang tujuam harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.itemcost'=>'bail|required|numeric|min:1',
            '*.qty_item'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_item.min'=>'Jumlah stock harus diisi/lebih besar dari NOL',
            '*.itemcost.min'=>'Nilai stock harus lebih besar dari NOL'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $sysid=$header['sysid'];
            $header['update_userid'] = PagesHelp::UserID($request);
            $header['update_timestamp'] = Date('Y-m-d H:i:s');
            $out=StockTransfer1::selectRaw('sysid,doc_number,warehouse_dest,warehouse_src')->where('doc_number',$header['reference'])->first();
            if ($out) {
                $header['sysid_request']=$out->sysid;
                $header['warehouse_dest']=$out->warehouse_src;
            }
            unset($header['sysid']);
            if ($opr=='updated'){
                StockTransfer1::where($where)
                ->update($header);
                StockTransfer2::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $number=StockTransfer1::GenerateNumber2($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $header['transfer_type']='IN';
                $header['post_date']=Date('Y-m-d');
                $header['is_canceled']='0';
                $sysid=StockTransfer1::insertGetId($header);
            }
            foreach($detail as $record) {
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                $dtl['warehouse_src']=$header['warehouse_src'];
                $dtl['warehouse_dest']=$header['warehouse_dest'];
                $dtl['sysid_transfer']=$header['sysid_request'];
                $dtl['doc_number_transfer']=$header['reference'];
                unset($dtl['part_number']);
                StockTransfer2::insert($dtl);
                StockTransfer2::where('sysid',$header['sysid_request'])
                ->where('item_code',$dtl['item_code'])
                ->update([
                    'qty_receive'=>$dtl['qty_item'],
                    'receive_date'=>$header['ref_date']
                ]);
            }
            DB::update('UPDATE t_inventory_transfer1 a INNER JOIN
                (SELECT sysid,SUM(line_cost) as line_cost FROM t_inventory_transfer2
                WHERE sysid=? GROUP BY sysid) b ON a.sysid=b.sysid
                SET a.inventory_cost=b.line_cost WHERE a.sysid=?',[$sysid,$sysid]);

            DB::update("UPDATE t_inventory_transfer1 SET is_received=1,received_sysid=?,received_doc_number=?,
                received_date=?
                WHERE sysid=?",[$sysid,$header['doc_number'],$header['ref_date'],$header['sysid_request']]);

            $respon=Inventory::ItemCard($sysid,'ITI',$opr,false,false,true);

            if ($respon['success']==true){
                $info=$this->build_jurnal($sysid,$request);
                if ($info['state']==true){
                    DB::commit();
                    return response()->success('Success', 'Simpan data Berhasil');
                } else {
                    DB::rollback();
                    return response()->error('', 501, $info['message']);
                }
            } else {
                DB::rollback();
                return response()->error('',501,$respon['message']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function print(Request $request){
        $sysid=$request->sysid;
        $header=StockTransfer1::from('t_inventory_transfer1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.reference,a.pool_code,a.warehouse_dest,a.ref_date,a.notes,b.descriptions as warehouse_name,
                    c.descriptions as pool_name,d.line_no,d.item_code,d.descriptions,d.qty_transfer,d.mou_inventory,e.user_name,d.qty_item")
        ->leftJoin('m_warehouse as b','a.warehouse_dest','=','b.warehouse_id')
        ->leftJoin('m_pool as c','a.pool_code','=','c.pool_code')
        ->leftJoin('t_inventory_transfer2 as d','a.sysid','=','d.sysid')
        ->leftJoin('o_users as e','a.update_userid','=','e.user_id')
        ->where('a.sysid',$sysid)->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('inventory.StockReceive',['header'=>$header,'profile'=>$profile])->setPaper('A4','potriat');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function Transfer(Request $request) {
        $pool_code=PagesHelp::PoolCode($request);
        $warehouse=PagesHelp::Warehouse($pool_code);
        $data=StockRequest1::from('t_inventory_transfer1 as a')
        ->selectRaw("a.doc_number,CONCAT(a.doc_number,' [',DATE_FORMAT(a.ref_date,'%d-%m-%Y') ,'] ', b.descriptions) AS descriptions")
        ->leftjoin('m_warehouse as b','a.warehouse_src','=','b.warehouse_id')
        ->where('a.warehouse_dest',$warehouse)
        ->where('a.is_received',0)
        ->orderBy('ref_date','desc')
        ->offset(0)
        ->limit(50)
        ->get();
        return response()->success('Success',$data);
    }
    public function Transfer2(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $warehouse=PagesHelp::Warehouse($pool_code);
        $data= StockTransfer1::selectRaw('sysid,doc_number,reference,warehouse_src,warehouse_dest,ref_date,notes')
        ->where('warehouse_dest',$warehouse)
        ->where('is_received',0)
        ->where('transfer_type','OUT');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('reference','like',$filter);
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

    public function dtlTransfer(Request $request){
        $doc_number=$request->doc_number;
        $data=StockRequest1::from('t_inventory_transfer1 as a')
        ->selectRaw("b.item_code,c.part_number,b.descriptions,b.qty_item,b.qty_transfer,b.mou_inventory,
           b.itemcost,b.line_cost")
        ->leftJoin('t_inventory_transfer2 as b', 'a.sysid', '=', 'b.sysid')
        ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
        ->where('a.doc_number',$doc_number)
        ->get();
        return response()->success('Success',$data);
    }
    public static function build_jurnal($sysid,$request) {
        /* Inventory
             Inventory On Transfer
         */
        $ret['state']=true;
        $ret['message']='';
        $data=StockTransfer1::selectRaw('pool_code,reference,doc_number,ref_date,
        sysid_jurnal,trans_code,trans_series,warehouse_src,warehouse_dest')
        ->where('sysid',$sysid)->first();
        if ($data){
            $pool_code=$data->pool_code;
            $detail=StockTransfer2::from('t_inventory_transfer2 as a')
            ->selectRaw('a.item_code,c.item_group,a.descriptions,ABS(b.qty) as qty,b.item_cost,ABS(b.qty)*b.item_cost as line_cost,d.inv_account')
            ->join('t_item_price as b', function($join)
                {
                    $join->on('a.sysid', '=', 'b.sysid');
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.doc_type','=',DB::raw("'ITI'"));
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
                $series = Journal1::GenerateNumber('ITI',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'ITI',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'9',
                  'notes'=>'Terima Transfer stock. '.$data->reference.', dari '.$data->warehouse_dest
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
                  'transtype'=>'9',
                  'notes'=>'Terima Transfer stock. '.$data->reference.', dari '.$data->warehouse_dest
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
                    'line_memo'=>'Transfer stock '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>$row->line_cost,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $ontransfer = $ontransfer + floatval($row->line_cost);
            }
            $line++;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$inventory_ontransfer,
                'line_memo'=>'Transfer stock. '.$data->reference.', dari '.$data->warehouse_dest.' tujuan '.$data->warehouse_src,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->reference,
                'debit'=>0,
                'credit'=>$ontransfer,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                StockTransfer1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'ITI',
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
        $ret['state']=true;
        $ret['message']='';
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
                    $join->on('b.doc_type','=',DB::raw("'ITI'"));
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
                $series = Journal1::GenerateNumber('ITI',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'ITI',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'9',
                  'notes'=>'Void terima stock. '.$data->reference
              ]);
            } else {
                $sysid_jurnal=$data->sysid_void;
                $series=$data->trans_series_void;
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
                  'transtype'=>'9',
                  'notes'=>'Void terima stock. '.$data->reference
                ]);
            }
            $acc=Accounting::Config();
            $inventory_ontransfer=$acc->inventory_transfer_account;
            /* Inventory */
            $line=1;
            $ontransfer=0;
            foreach($detail as $row){
                $row->inv_account=Accounting::inventory_account($data->warehouse_src,$row->item_group)->inv_account;
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->inv_account,
                    'line_memo'=>'Void terima stock '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                             ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>0,
                    'credit'=>$row->line_cost,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $ontransfer = $ontransfer + floatval($row->line_cost);
            }
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$inventory_ontransfer,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->reference,
                'line_memo'=>'Void Terima stock. '.$data->reference.', dari '.$data->warehouse_dest,
                'debit'=>$ontransfer,
                'credit'=>0,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                StockTransfer1::where('sysid',$sysid)
                ->update(['sysid_void'=>$sysid_jurnal,
                'trans_code_void'=>'ITI',
                'trans_series_void'=>$series]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        } else {
            $ret['state']=false;
            $ret['message']='Data tidak ditemukan';
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
                    b.qty_request,b.qty_item,b.line_cost,b.qty_transfer,a.update_userid,a.update_timestamp")
        ->leftJoin('t_inventory_transfer2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_canceled', '0')
        ->where('a.transfer_type', 'IN');
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
                    b.qty_request,b.qty_item,b.line_cost,b.qty_transfer,a.update_userid,a.update_timestamp")
        ->leftJoin('t_inventory_transfer2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_canceled', '0')
        ->where('a.transfer_type', 'IN');
        if (!($warehouse_id=='ALL')){
            $data=$data->where('a.warehouse_src',$warehouse_id);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PENERIMAAN TRANSFER STOCK');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));

        $sheet->setCellValue('A5', 'No.Dokumen');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'Gudang Penerima');
        $sheet->setCellValue('D5', 'Gudang Asal');
        $sheet->setCellValue('E5', 'Kode Item');
        $sheet->setCellValue('F5', 'Part Number');
        $sheet->setCellValue('G5', 'Nama Barang/Item');
        $sheet->setCellValue('H5', 'Jml.Transgfer');
        $sheet->setCellValue('I5', 'Jml.Terima');
        $sheet->setCellValue('J5', 'Nilai Stock');
        $sheet->setCellValue('K5', 'User Input');
        $sheet->setCellValue('L5', 'Tgl.Input');
        $sheet->getStyle('A5:L5')->getAlignment()->setHorizontal('center');

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
            $sheet->setCellValue('H'.$idx, $row->qty_transfer);
            $sheet->setCellValue('I'.$idx, $row->qty_item);
            $sheet->setCellValue('J'.$idx, $row->line_cost);
            $sheet->setCellValue('K'.$idx, $row->update_userid);
            $sheet->setCellValue('L'.$idx, $row->update_timestamp);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('C6:G'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('H6:J'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('L6:L'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        // Formater
        $sheet->getStyle('A1:L5')->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:L'.$idx)->applyFromArray($styleArray);
        foreach(range('C','L') as $columnID) {
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
