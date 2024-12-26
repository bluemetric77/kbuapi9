<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Items;
use App\Models\Master\ItemsJob;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;


class ItemjobController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= items::selectRaw('item_code,descriptions,estimate_time,estimate_cost,is_active')->where('item_group','400');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('item_code','like',$filter)
               ->orwhere('descriptions','like',$filter);
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

    public function destroy(Request $request){
        $itemcode=$request->itemcode;
        $data=Items::where('item_code',$itemcode)
         ->where('item_group','400')->first();
        if ($data) {
            DB::beginTransaction();
            try{
                PagesHelp::write_log($request,-1,$data->item_code,'Delete recods ['.$data->item_code.'-'.$data->descriptions.']');
                Items::where('item_code',$itemcode)->delete();
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch (Exception $e) {
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }

    public function get(Request $request){
        $itemcode=$request->itemcode;
        $data['header']=items::selectRaw('item_code,descriptions,estimate_time,estimate_cost,is_active')
        ->where('item_code',$itemcode)->first();
        $data['detail']=ItemsJob::from('m_item_job as a')
        ->selectRaw("a.item_job,a.line_no,a.item_code,b.part_number,b.descriptions,b.mou_warehouse,a.qty,a.price,a.total")
        ->leftjoin('m_item as b','a.item_code','=','b.item_code')
        ->where('a.item_job',$itemcode)->get();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $detail=$data['detail'];
        $validator=Validator::make($rec,[
            'item_code'=>'bail|required',
            'descriptions'=>'bail|required',
        ],[
            'item_code.required'=>'Kode pekerjaan harus diisi',
            'descriptions.required'=>'Keteragan pekerjaan harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.qty'=>'bail|required|numeric|min:1',
            '*.price'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty.min'=>'Jumlah harus diisi/lebih besar dari NOL',
            '*.price.min'=>'Estimasi harga tidak boleh NOL',
            '*.item_code.distinct'=>'Kode barang :input terduplikasi (terinput lebih dari 1)',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $rec['item_group']='400';
            $rec['mou_purchase']='PCS';
            $rec['convertion']='1';
            $rec['mou_warehouse']='PCS';
            if ($opr=='updated'){
                Items::where($where)->update($rec);
                ItemsJob::where('item_job',$rec['item_code'])->delete();
            } else if ($opr='inserted'){
                Items::insert($rec);
            }
            foreach($detail as $record) {
                $dtl=(array)$record;
                $dtl['item_job']=$rec['item_code'];
                unset($dtl['part_number']);
                unset($dtl['descriptions']);
                unset($dtl['mou_warehouse']);
                ItemsJob::insert($dtl);
            }
            PagesHelp::write_log($request,-1,$rec['item_code'],'Add/Update recods ['.$rec['item_code'].'-'.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success', 'Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getItems(Request $request){
        $data=Items::select('item_code',DB::raw('CONCAT(item_code, " - ", descriptions) AS descriptions'))
             ->where('is_active','1')
             ->where('item_group','400')
             ->get();
        return response()->success('Success',$data);
    }

    public function lookup(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy =isset($request->sortBy) ? $request->sortBy :'';
        $data= Itemsjob::from('m_item as a')
            ->select('a.item_code','a.descriptions');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('a.item_code','like',$filter)
                    ->orwhere('a.descriptions','like',$filter);
            });
        }
        $data=$data->where('a.is_active',1);
        if ($sortBy<>''){
            if ($descending) {
                $data=$data->orderBy('a.'.$sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy('a.'.$sortBy,'asc')->paginate($limit);
            }
        }
        return response()->success('Success',$data);
    }

}
