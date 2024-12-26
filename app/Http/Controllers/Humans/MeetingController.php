<?php

namespace App\Http\Controllers\Humans;

use App\Models\Humans\Meetings;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;
use PDF;


class MeetingController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Meetings::selectRaw('sysid,sysid,ref_date,ref_time,title,audiance');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('pool_code','like',$filter)
               ->orwhere('title','like',$filter);
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
        $data=Meetings::find($id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Meetings::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['pool_code']=PagesHelp::PoolCode($request);
        $validator=Validator::make($rec,[
            'pool_code'=>'bail|required',
            'title'=>'bail|required',
            'audiance'=>'bail|required'
        ],[
            'pool_code.required'=>'Kode Pool harus diisi',
            'title.required'=>'Agenda meeting harus diisi',
            'audiance.required'=>'Peserta meeting terkait harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        try{
            if ($opr=='updated'){
                Meetings::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                Meetings::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function print(Request $request){
        $sysid=$request->sysid;
        $data= Meetings::selectRaw('sysid,sysid,ref_date,ref_time,title,audiance,notes')->first();
        if ($data) {
            $data->ref_date=date_format(date_create($data->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('humans.meeting',['header'=>$data,'profile'=>$profile])->setPaper('A4','potriat');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

}
