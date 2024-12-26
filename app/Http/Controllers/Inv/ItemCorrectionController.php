<?php

namespace App\Http\Controllers\Inv;

use App\Models\Master\Partner;
use App\Models\Inventory\ItemCorrection1;
use App\Models\Inventory\ItemCorrection2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Inventory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;

class ItemCorrectionController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $date1 = $request->date1;
        $date2 = $request->date2;
        $data= ItemCorrection1::select();
        $data=$data
           ->where('pool_code',$pool_code)
           ->where('ref_date','>=',$date1)
           ->where('ref_date','<=',$date2);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter);
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
        $header=ItemCorrection1::where('sysid',$sysid)->first();
        if (!($header==null)){
            $data['header']=$header;
            $data['detail']=ItemCorrection2::from('t_inventory_correction2 as a')
            ->select('a.sysid','a.line_no','a.item_code','a.price_code','b.part_number','a.descriptions','a.mou_inventory',
             'a.current_stock','a.opname_stock','a.adjustment_stock','a.end_stock','a.cost_current','a.cost_adjustment','a.final_adjustment')
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
            'warehouse_id.required'=>'Gudang harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->all());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|exists:m_item,item_code',
            '*.opname_stock'=>'bail|required|numeric|min:0',
            '*.cost_adjustment'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.opname_stock.min'=>'Jumlah opname harus diisi/lebih besar dari NOL',
            '*.cost_adjustment.min'=>'Harga inventory tidak boleh NOL',
            '*.price_code.distinct'=>'Kode harga :input terduplikasi (terinput lebih dari 1)',
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
                ItemCorrection1::where($where)
                ->update($header);
                ItemCorrection2::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $number=ItemCorrection1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $sysid=ItemCorrection1::insertGetId($header);
            }
            $header['update_timestamp'] = Date('Y-m-d H:i:s');
            foreach($detail as $record) {
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                $dtl['warehouse_id']=$header['warehouse_id'];
                $dtl['warehouse_id']=$header['warehouse_id'];
                $price_code=DB::table('m_item_stock_price')
                ->where('item_code',$dtl['item_code'])
                ->where('price_code',$dtl['price_code'])
                ->first();
                if (!($price_code)){
                    $dtl['price_code']=Inventory::getPriceCode();
                }
                unset($dtl['part_number']);
                ItemCorrection2::insert($dtl);
            }
            $respon=Inventory::ItemCard($sysid,'ISO',$opr,false,true);
            if ($respon['success']==true){
                DB::commit();
                return response()->success('Success','Simpan data Berhasil');
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
        $header=ItemCorrection1::from('t_inventory_correction1 as a')
        ->select('a.doc_number','a.ref_date','a.pool_code','a.warehouse_id',
                DB::raw('b.descriptions as warehouse_name'),DB::raw('c.descriptions as pool_name'),'a.update_userid','a.update_timestamp',
                'd.line_no','d.item_code','d.descriptions','d.current_stock','d.opname_stock','d.cost_adjustment','d.end_stock','d.mou_inventory',
                'e.user_name')
        ->leftJoin('m_warehouse as b','a.warehouse_id','=','b.warehouse_id')
        ->leftJoin('m_pool as c','a.pool_code','=','c.pool_code')
        ->leftJoin('t_inventory_correction2 as d','a.sysid','=','d.sysid')
        ->leftJoin('o_users as e','a.update_userid','=','e.user_id')
        ->where('a.sysid',$sysid)->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('inventory.StockOpname',['header'=>$header,'profile'=>$profile])->setPaper('A4','potriat');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
}
