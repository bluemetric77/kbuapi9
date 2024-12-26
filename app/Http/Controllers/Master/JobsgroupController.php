<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Jobsgroup;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobsgroupController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Jobsgroup::selectRaw("*");
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
        $id=$request->id;
        $data=Jobsgroup::where('sysid',$id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Jobsgroup::where('sysid',$id)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        try{
            if ($opr=='updated'){
                Jobsgroup::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Jobsgroup::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function getJobsgroup(Request $request){
        $pool=Jobsgroup::select('item_code',DB::raw('CONCAT(descriptions, " - ", item_code) AS descriptions'))
             ->where('is_active','1')
             ->orderby('descriptions','asc')
             ->get();
        return response()->success('Success',$pool);
    }

}
