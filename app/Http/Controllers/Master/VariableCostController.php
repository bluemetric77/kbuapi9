<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\VariableCost;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;


class VariableCostController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= VariableCost::from('m_variable_cost as a')->select('a.line_no','a.cost_name','a.standar_cost','a.no_account','a.is_active','a.is_editable',
                DB::raw("CONCAT(a.no_account,'-',IFNULL(b.account_name,'N/A')) as descriptions"))
        ->leftjoin('m_account as b','a.no_account','=','b.account_no');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.cost_name','like',$filter);
        }
        if ($descending) {
            $data=$data->orderBy($sortBy,'desc')->paginate($limit);
        } else {
            $data=$data->orderBy($sortBy,'asc')->paginate($limit);
        }
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=VariableCost::find($id);
        if ($data) {
            DB::beginTransaction();
            try {
                VariableCost::find($id)->delete();
                PagesHelp::write_log($request,$data->line_no,'','Delete recods ['.$data->cost_name.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch(Exception $e) {
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data= VariableCost::from('m_variable_cost as a')->select('a.line_no','a.cost_name','a.standar_cost','a.no_account','a.is_active','a.is_editable',
                DB::raw("CONCAT(a.no_account,'-',IFNULL(b.account_name,'N/A')) as descriptions"))
        ->leftjoin('m_account as b','a.no_account','=','b.account_no')
        ->where('a.line_no',$id)
        ->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        $rec['update_userid'] = PagesHelp::UserID($request);
        $validator=Validator::make($rec,[
            'cost_name'=>'bail|required',
            'no_account'=>'bail|required'
        ],[
            'cost_name.required'=>'Nama biaya harus diisi',
            'no_account.required'=>'Akun biaya harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        try{
            $sysid=$rec['line_no'];
            unset($rec['descriptions']);
            unset($rec['line_no']);
            if ($opr=='updated'){
                VariableCost::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                VariableCost::insert($rec);
            }
            PagesHelp::write_log($request,$sysid,'','Add/Update record ['.$rec['cost_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getVariableCost(Request $request){
        $sysid=isset($request->sysid) ? $request->sysid :'-1';
        $data=VariableCost::from('m_variable_cost as a')
            ->selectRaw("1 as line_no,a.line_no AS cost_id,a.cost_name,IFNULL(b.cost,0) AS cost")
            ->leftJoin('m_vehicle_routepoint_cost as b', function($join)use($sysid)
            {
                $join->on('a.line_no', '=', 'b.cost_id');
                $join->on(DB::raw('b.sysid'), DB::raw('='),DB::raw($sysid));
            })->get();
        return response()->success('Success',$data);
    }

}
