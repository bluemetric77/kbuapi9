<?php

namespace App\Http\Controllers\Inv;

use App\Models\Master\Partner;
use App\Models\Inventory\GoodsOut1;
use App\Models\Inventory\GoodsOut2;
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

class GoodsoutController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $date1 = $request->date1;
        $date2 = $request->date2;
        $data= GoodsOut1::select();
        $data=$data
           ->where('pool_code',$pool_code)
           ->where('ref_date','>=',$date1)
           ->where('ref_date','<=',$date2);
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
    public function get(Request $request){
        $sysid=$request->sysid;
        $header=GoodsOut1::where('sysid',$sysid)->first();
        if (!($header==null)){
            $data['header']=$header;
            $data['detail']=GoodsOut2::from('t_inventory_inout2 as a')
            ->select('a.sysid','a.line_no','a.item_code','b.part_number','a.descriptions','a.qty_item','a.mou_inventory',
             'a.itemcost','a.line_cost','a.notes')
            ->leftJoin('m_item as b','a.item_code','=','b.item_code')
            ->where('sysid',$sysid)->get();
            return response()->success('Success',$data);
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $header=$data['header'];
        $detail=$data['detail'];
        $header['pool_code']=PagesHelp::PoolCode($request);
        $header['warehouse_id']=PagesHelp::Warehouse($header['pool_code']);
        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'warehouse_id'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'warehouse_id.required'=>'Gudang harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|exists:m_item,item_code',
            '*.qty_item'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_item.min'=>'Jumlah invoice harus diisi/lebih besar dari NOL'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $sysid=$header['sysid'];
            $header['update_userid'] = PagesHelp::UserID($request);
            unset($header['sysid']);
            if ($opr=='updated'){
                GoodsOut1::where($where)
                ->update($header);
                GoodsOut2::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $number=GoodsOut1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $sysid=GoodsOut1::insertGetId($header);
            }
            $header['update_timestamp'] = Date('Y-m-d H:i:s');
            foreach($detail as $record) {
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                $dtl['warehouse_id']=$header['warehouse_id'];
                unset($dtl['part_number']);
                GoodsOut2::insert($dtl);
            }
            $respon=Inventory::ItemCard($sysid,'PBG',$opr,false,false);
            if ($respon['success']==true){
                $info=$this->build_jurnal($sysid,$request);
                if ($info['state']==true){
                    DB::update("UPDATE t_inventory_inout2 a INNER JOIN
                    (SELECT sysid,item_code,SUM(qty*item_cost) as line_cost FROM t_item_price
                    WHERE sysid=? AND doc_type='PBG' GROUP BY sysid,item_code) b ON a.sysid=b.sysid AND a.item_code=b.item_code
                    SET a.line_cost=ABS(b.line_cost),a.itemcost=ABS(b.line_cost/a.qty_item)
                    WHERE a.sysid=?",[$sysid,$sysid]);
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
    $item = array();
        $sysid=$request->sysid;
        $header=GoodsOut1::from('t_purchase_request1 as a')
        ->select('a.doc_number','a.ref_date','a.pool_code','a.warehouse_id','a.descriptions',
        DB::raw('b.descriptions as warehouse_name'),DB::raw('c.descriptions as pool_name'),'user_posted','posted_date')
        ->leftJoin('m_warehouse as b','a.warehouse_id','=','b.warehouse_id')
        ->leftJoin('m_pool as c','a.pool_code','=','c.pool_code')
        ->where('a.sysid',$sysid)->first();
        if (!($header==null)){
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $header->posted_date=date_format(date_create($header->posted_date),'d-m-Y h:i:s');
            $data['header']=$header;
            $data['detail']=GoodsOut2::where('sysid',$sysid)->get();
            $title = ['title' => 'Welcome to belajarphp.net'];
            $pdf = PDF::loadView('inventory.PurchaseOrder',['header'=>$header,'detail'=>$data['detail']])->setPaper('a4','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public static function build_jurnal($sysid,$request) {
        /* Inventory On Transfer
             Inventory
         */
        $ret['state']=true;
        $ret['message']='';
        $data=GoodsOut1::selectRaw('pool_code,reference,doc_number,ref_date,
        sysid_jurnal,trans_code,trans_series,warehouse_id')
        ->where('sysid',$sysid)->first();
        if ($data){
            $pool_code=$data->pool_code;
            $detail=GoodsOut2::from('t_inventory_inout2 as a')
            ->selectRaw('a.item_code,a.descriptions,ABS(b.qty) as qty,b.item_cost,ABS(b.qty)*b.item_cost as line_cost,d.inv_account,d.cost_account')
            ->join('t_item_price as b', function($join)
                {
                    $join->on('a.sysid', '=', 'b.sysid');
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.doc_type','=',DB::raw("'PBG'"));
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
                $series = Journal1::GenerateNumber('PBG',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'PBG',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'7',
                  'notes'=>'Pengeluran barang '.$data->reference.', dari '.$data->warehouse_src
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
                  'transtype'=>'7',
                  'notes'=>'Pengeluran barang '.$data->reference.', dari '.$data->warehouse_src
                ]);
            }
            /* Cost
                Inventory */
            $line=0;
            $ontransfer=0;
            foreach($detail as $row){
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->cost_account,
                    'line_memo'=>'Pengeluaran '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>$row->line_cost,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->inv_account,
                    'line_memo'=>'Pengeluaran '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>0,
                    'credit'=>$row->line_cost,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                GoodsOut1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'PBG',
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
}
