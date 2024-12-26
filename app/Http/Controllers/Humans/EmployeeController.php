<?php

namespace App\Http\Controllers\Humans;

use App\Models\Humans\Employee;
use App\Models\General\Documents;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;


class EmployeeController extends Controller
{
    public function show(Request $request){
        $filter = isset($request->filter) ? $request->filter :'';
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;

        $data= Employee::from('m_employee as a')
        ->selectRaw('a.pool_code,a.sysid,a.emp_id,a.emp_name,a.hire_date,a.pob,dob,b.descriptions as department,
        a.current_address,a.phone_number1,a.phone_number2,a.email,a.is_active')
        ->leftjoin('m_department as b','a.dept_id','=','b.sysid');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.emp_name','like',$filter)
               ->orwhere('a.current_address','like',$filter)
               ->orwhere('a.phone_number1','like',$filter)
               ->orwhere('a.citizen_number','like',$filter);
        }
        if ($descending) {
            $data=$data->orderBy($sortBy,'desc')->paginate($limit);
        } else {
            $data=$data->orderBy($sortBy,'asc')->paginate($limit);
        }
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $sysid=$request->sysid;
        $data=Employee::where('sysid',$sysid)->first();
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $personal_id=$request->id;
        $data=Employee::find($personal_id);
        return response()->success('Success',$data);
    }
    public function document(Request $request){
        $id=$request->emp_id;
        $data=Documents::where('doc_number',$id)
        ->where('doc_type','EMP')
        ->where('is_deleted','0')
        ->get();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['pool_code']=PagesHelp::PoolCode($request);
        unset($rec['photo']);
        $validator=Validator::make($rec,[
            'emp_id'=>'bail|required',
            'emp_id'=>'bail|required',
            'current_address'=>'bail|required',
            'phone_number1'=>'bail|required'
        ],[
            'emp_id.required'=>'ID karyawan harus diisi',
            'emp_id.required'=>'Nama karaywan harus diisi',
            'current_address.required'=>'Alamat harus diisi',
            'phone_number1.required'=>'No. Telepon 1 harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        try{
            if ($opr=='updated'){
                Employee::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                Employee::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function change_pool(Request $request){
        $data= $request->json()->all();
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'sysid'=>'bail|required',
            'pool_code'=>'bail|required'
        ],[
            'sysid.required'=>'ID personal harus diisi',
            'emp_name.required'=>'Nama harus diisi',
            'pool_code.required'=>'Pool tujuan harus diisi harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if ($rec['pool_code']=='-'){
            return response()->error('',501,'Pool tujuan belum dipilih');
        }
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        try{
            Employee::where($where)
                ->update(['pool_code'=>$rec['pool_code']]);
            return response()->success('Success','Perpindahan ke pool tujuan berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function uploadfoto(Request $request)
    {
        $sysid  = isset($request->sysid) ? $request->sysid : '-1';
        $uploadedFile = $request->file('file');
        $originalFile = $uploadedFile->getClientOriginalName();
        $originalFile = Date('Ymd-His')."-".$originalFile;
        $directory="public/employee";
        $path = $uploadedFile->storeAs($directory,$originalFile);
        Employee::where('sysid',$sysid)
        ->update(['photo'=>$path]);
        $respon['path_file']=$path;
        $respon['message']='Upload foto berhasil';
        return response()->success('success',$respon);
    }

    public function downloadfoto(Request $request)
    {
       // tampung berkas yang sudah diunggah ke variabel aru
        // 'file' merupakan nama input yang ada pada form
        $sysid   = isset($request->sysid) ? $request->sysid : '-1';
        $data=Employee::selectRaw("IFNULL(photo,'') as photo")
            ->where('sysid',$sysid)
            ->first();
        if ($data) {
            $file=$data->photo;
            $publicPath = \Storage::url($file);
            $backfile =$data->file_name;
            $headers = array('Content-Type: application/jpeg');
            return Storage::download($file, $backfile,$headers);
        } else {
            return null;
        }
    }
    public function deletefoto(Request $request)
    {
       // tampung berkas yang sudah diunggah ke variabel aru
        // 'file' merupakan nama input yang ada pada form
        $sysid   = isset($request->sysid) ? $request->sysid : '';
        Employee::where('sysid',$sysid)
            ->update(['photo'=>'-']);
        return response()->success('Success','Hapus data berhasil');
    }
}