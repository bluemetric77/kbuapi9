<?php

namespace App\Http\Controllers\Humans;

use App\Models\Humans\Department;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Department::select('sysid','descriptions','is_active');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('descriptions','like',$filter);
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
        $data=Department::find($id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Department::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        try{
            if ($opr=='updated'){
                Department::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                Department::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function getDepartment(Request $request){
        $data=Department::select('sysid','descriptions')
             ->where('is_active','1')
             ->get();
        return response()->success('Success',$data);
    }
}
