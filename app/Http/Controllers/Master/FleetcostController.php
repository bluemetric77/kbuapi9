<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Fleetcost;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;

class FleetcostController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $active=isset($request->active) ? $request->active: false;
        $data= Fleetcost::select();
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('descriptions','like',$filter)
               ->orwhere('sortname','like',$filter);
            });
        }
        if ($active=='true'){
            $data=$data->where('is_active',1);
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
        $id=$request->id;
        $data=Fleetcost::find($id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Fleetcost::where('id',$id)
            ->leftJoin('m_account', 'm_fleet_cost.account_no', '=', 'm_account.account_no')
            ->select('m_fleet_cost.*','m_account.account_name')->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        try{
            unset($rec['account_name']);
            if ($opr=='updated'){
                Fleetcost::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Fleetcost::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function getCost(Request $request){
        $data=Fleetcost::select(DB::raw('id as line_no'),'id','descriptions',DB::raw('0 as fleet_cost'))
             ->where('is_active','1')
             ->orderBy('id','asc')
             ->get();
        return response()->success('Success',$data);
    }
    /*public function getPool2(Request $request){
        $pool=Fleetcost::select('pool_code',DB::raw('CONCAT(pool_code, " - ", descriptions) AS descriptions'))
             ->where('is_active','1')
             ->get();
        $bank=array();
        $bank['pool_code']='';
        $bank['descriptions']='N/A';
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }*/
}
