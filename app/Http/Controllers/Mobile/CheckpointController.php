<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Ops\Operation;
use App\Models\Ops\OperationRoute;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PagesHelp;

class CheckpointController extends Controller{
  public function show(Request $request){
      $checkpoint=isset($request->id) ? $request->id : -1;
      $data =DB::table('t_operation as a')
      ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.vehicle_no,a.police_no,IFNULL(c.personal_name,'NA') AS personal_name,d.route_name")
      ->join("t_operation_route as b","a.sysid","=","b.sysid")
      ->leftjoin("m_personal as c","a.driver_id","=","c.employee_id")
      ->leftjoin("m_bus_route as d","a.route_id","=","d.sysid")
      ->where('a.is_closed',0)
      ->where('b.checkpoint_sysid',$checkpoint)
      ->get();
      if ($data) {
        return response()->success('success',$data);
      } else {
        return response()->error('',501,'Data Kosong');
      }
  }

  public function go_route(Request $request)
  {
      $id = $request->id;
      $data=OperationRoute::select('line_id','flag_route','checkpoint_sysid','checkpoint_name','passenger',
          'checkpoint_date','checkpoint_time','photo','checker_name','confirm_name','is_confirm')
          ->where('sysid',$id)
          ->where('flag_route','GO')
          ->get();
       $route=array();
       $server=PagesHelp::my_server_url();
       foreach($data as $row) {
            if (!($row['photo']=='')) {
                $row['photo']=$server.Storage::url($row['photo']);
            }
            $route[]=$row;
       }
      return response()->success('Success', $route);
  }
  public function back_route(Request $request)
  {
      $id = $request->id;
      $data=OperationRoute::select('line_id','flag_route','checkpoint_sysid','checkpoint_name','passenger',
          'checkpoint_date','checkpoint_time','photo','checker_name','confirm_name','is_confirm')
          ->where('sysid',$id)
          ->where('flag_route','BACK')
          ->get();
       $route=array();
       $server=PagesHelp::my_server_url();
       foreach($data as $row) {
            if (!($row['photo']=='')) {
                $row['photo']=$server.Storage::url($row['photo']);
            }
            $route[]=$row;
       }
       return response()->success('Success', $route);
  }

  public function savecheckpoint(Request $request)
  {
      $data=json_decode($request->data,true);
      $sysid=isset($data['sysid']) ? $data['sysid'] :'-1';
      $flag=isset($data['flag_route']) ? $data['flag_route'] :'-';
      $lineid=isset($data['line_id']) ? $data['line_id'] :'-1';
      $cheker=isset($data['checker_name']) ? $data['checker_name'] :'';
      $passenger=isset($data['passenger']) ? $data['passenger'] :'0';
      $info=OperationRoute::select('checkpoint_name','is_confirm','passenger')
      ->where('sysid',$sysid)
      ->where('flag_route',$flag)
      ->where('line_id',$lineid)->first();
      if ($info){
        if ($info->is_confirm) {
            return response()->error('',501,'Data sudah terkonfirmasi tidak bisa diubah');
        }
        $date=Date('Y-m-d');
        $time=Date('H:i');
        OperationRoute::where('sysid',$sysid)
        ->where('flag_route',$flag)
        ->where('line_id',$lineid)
        ->update(['passenger'=>$passenger,
                  'checkpoint_date'=>$date,
                  'checkpoint_time'=>$time,
                  'checker_name'=>$cheker]);
        $uploadedFile = $request->file('image');
        if ($uploadedFile){
          $originalFile = $sysid.$flag.$lineid.'-'.$uploadedFile->getClientOriginalName();
          $directory="public/checker";
          $path = $uploadedFile->storeAs($directory,$originalFile);
          OperationRoute::where('sysid',$sysid)
          ->where('flag_route',$flag)
          ->where('line_id',$lineid)
          ->update(['photo'=>$path]);
        }
        return response()->success('Success', 'Berhasil');
      } else {
            return response()->error('',501,'Data tidak ditemukan');
      }
  }
  public function confirmcheckpoint(Request $request)
  {
      $data=$request->json()->all();
      $sysid=isset($data['sysid']) ? $data['sysid'] :'-1';
      $flag=isset($data['flag_route']) ? $data['flag_route'] :'-';
      $lineid=isset($data['line_id']) ? $data['line_id'] :'-1';
      $confirm=isset($data['confirm_name']) ? $data['confirm_name'] :'';
      $isconfirm='1';
      $info=OperationRoute::select('checkpoint_name','is_confirm','passenger')
      ->where('sysid',$sysid)
      ->where('flag_route',$flag)
      ->where('line_id',$lineid)->first();
      if ($info){
        if ($info->is_confirm) {
            $isconfirm="0";
        }
        $date=Date('Y-m-d');
        $time=Date('H:i');
        OperationRoute::where('sysid',$sysid)
        ->where('flag_route',$flag)
        ->where('line_id',$lineid)
        ->update(['confirm_name'=>$confirm,
                  'is_confirm'=>$isconfirm]);
        return response()->success('Success', 'Data terkonfirmasi');
      } else {
        return response()->error('',501,'Data tidak ditemukan');
      }
  }

  public function lastunit(Request $request){
      $id=isset($request->id) ? $request->id : '-1';
      $personal=DB::table('m_personal')
        ->select('employee_id')
        ->where('personal_id',$id)
        ->first();
      if ($personal) {
        $empid=$personal->employee_id;
        $data =DB::table('t_operation as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.vehicle_no,a.police_no,
        IFNULL(c.personal_name,'NA') AS driver,
        IFNULL(d.personal_name,'NA') AS helper,
        IFNULL(e.personal_name,'NA') AS conductor,
        f.route_name")
        ->join("t_operation_route as b","a.sysid","=","b.sysid")
        ->leftjoin("m_personal as c","a.driver_id","=","c.employee_id")
        ->leftjoin("m_personal as d","a.helper_id","=","d.employee_id")
        ->leftjoin("m_personal as e","a.conductor_id","=","e.employee_id")
        ->leftjoin("m_bus_route as f","a.route_id","=","f.sysid")
        ->where('a.driver_id',$empid)
        ->orwhere('a.helper_id',$empid)
        ->orwhere('a.conductor_id',$empid)
        ->orderby('a.sysid','desc')
        ->first();
       if ($data) {
          return response()->success('success',$data);
        } else {
          return response()->error('',501,'Data Kosong');
        }
      } else {
          return response()->error('',501,'User tidak link');
      }
  }
}
