<?php

namespace App\Http\Controllers\Humans;

use App\Models\Humans\Device;
use App\Models\Humans\UserInfo;
use App\Models\Humans\TmpFP;
use App\Models\Humans\Employee;
use App\Models\Humans\DeviceCmds;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Constants\Commands;
use PagesHelp;


class MachineController extends Controller
{
    public function show(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy = $request->sortBy;
        $data= Device::selectRaw("ID,DevSN,DevName,ErrorDelay,Delay,DevIP,DevMac,DevFPVersion,DevFirmwareVersion,UserCount,AttCount,uuid_rec");

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('DevSN','like',$filter)
                    ->orwhere('a.DevName','like',$filter);

        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $uuid=$request->uuid_rec;
        $data=Device::find($id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $uuid=$request->uuid_rec;
        $data=Device::SelectRaw("ID,DevSN,DevName,ErrorDelay,Delay,DevIP,DevMac,DevFPVersion,DevFirmwareVersion,UserCount,AttCount,RealTime,TransInterval,uuid_rec")
        ->where("uuid_rec",$uuid)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $rec=$data['data'];
        try{
            $dev = Device::where("uuid_rec",$rec['uuid_rec']??"")->first();
            if (!$dev) {
                $dev = new Device();
                $dev->ATTLOGStamp=999;
                $dev->OPERLOGStamp=999;
                $dev->ATTPHOTOStamp=999;
                $dev->TransFlag=999;
                $dev->Encrypt=999;
                $dev->LastRequestTime=999;
                $dev->VendorName='ZK';
                $dev->IRTempDetectionFunOn=0;
                $dev->MaskDetectionFunOn=0;
                $dev->UserPicURLFunOn=0;
                $dev->TransFlag='TransData AttLog OpLog	AttPhoto	EnrollUser	ChgUser	EnrollFP 	ChgFP 	FPImag 	FACE	 UserPic 	WORKCODE	 BioPhoto';
                $dev->MultiBioDataSupport='0:0:1:0:0:0:0:0:0:1';
                $dev->MultiBioPhotoSupport='0:0:0:0:0:0:0:0:0:0';
                $dev->MultiBioVersion='0:10:58:0:0:0:0:3:5:0';
                $dev->MultiBioCount='0:0:0:0:0:0:0:0:0:0';
                $dev->MaxMultiBioDataCount='0:30:30000:0:0:0:0:10:0:0';
                $dev->MaxMultiBioPhotoCount='0:0:0:0:0:0:0:0:0:0';
                $dev->uuid_rec=Str::uuid();
            }
            $dev->DevSN         = $rec['DevSN'];
            $dev->DevName       = $rec['DevName'];
            $dev->ErrorDelay    = $rec['ErrorDelay'];
            $dev->Delay         = $rec['Delay'];
            $dev->Realtime      = $rec['Realtime'];
            $dev->TransInterval = $rec['TransInterval'];
            $dev->save();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }

    public function getdevice(Request $request){
        $data=Deicee::selectRaw("ID,DevSN,DevName")
             ->get();
        return response()->success('Success',$data);
    }

    public function show_user(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy  = $request->sortBy;
        $devID   = $request->device_id;
        $data= UserInfo::from("UserInfo as ui")
        ->selectRaw("ui.ID,ui.PIN,ui.UserName,emp.emp_id,emp.emp_name,emp.pool_code")
        ->leftjoin("m_employee as emp", "emp.pin","=","ui.PIN")
        ->where("ui.DevSN",$devID);

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('ui.PIN','like',$filter)
                    ->orwhere('ui.UserName','like',$filter);

        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function show_employee(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy  = $request->sortBy;
        $devID   = $request->device_id;

        $data= Employee::from("m_employee as emp")
        ->selectRaw("emp.emp_id,emp.emp_name,emp.pool_code,emp.pin,ui.ID,ui.PIN,ui.UserName")
        ->leftJoin('UserInfo as ui', function ($join) use ($devID){
            $join->on('emp.pin', '=', 'ui.PIN');
            $join->on('ui.DevSN', '=', DB::raw("'$devID'"));
         });

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('emp.emp_id','like',$filter)
                    ->orwhere('emp.emp_name','like',$filter);

        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function reboot(Request $request) {
       $sn =$request->device_id ?? '';
       $device= Device::where("DevSN",$sn)->first();

        if ($device) {
            DeviceCmds::insert([
                'DevSN'=>$device,
                'Content'=>Commands::COMMAND_CONTROL_REBOOT,
                'CommitTime'=>Date('Y-m-d H:i:s')
            ]);
        } else {
           return response()->error('',501,'Device '.$sn.' tidak ditemukan');
        }
        return response()->success('Success','Mesin absensi '.$sn.' akan di restart');
    }

    public function update_user(Request $request) {
        $pin = $request->pin;
        $sn  = $request->sn;

        $device   = Device::where("DevSN",$sn)->first();
        $employee = Employee::where("pin",$pin)->first();
        if (($employee) && ($device)){
          $cmd=PagesHelp::build_command(Commands::COMMAND_UPDATE_USER_INFO, [
            0 => $employee->pin,
            1 => $employee->emp_name,
            2 => '0',
            3 => '',
            4 => '',
            5 => '',
            6 => '000000100000000']);

            DeviceCmds::insert([
                'DevSN'=>$device->DevSN,
                'Content'=>$cmd,
                'CommitTime'=>Date('Y-m-d H:i:s')
            ]);

            $cmd2=PagesHelp::build_command(Commands::COMMAND_QUERY_USER_INFO, [
            0 => $employee->pin]);

            DeviceCmds::insert([
                'DevSN'=>$device->DevSN,
                'Content'=>$cmd2,
                'CommitTime'=>Date('Y-m-d H:i:s')
            ]);
            return response()->success('Success','Perintah update data user dikirim');
        } else {
            return response()->error('',501,'Device '.$sn.' tidak ditemukan');
        }
    }

    public function send_user(Request $request) {
        $pin = $request->pin;
        $sn  = $request->sn;

        $device   = Device::where("DevSN",$sn)->first();
        $employee = Employee::where("pin",$pin)->first();
        if (($employee) && ($device)){
          $cmd=PagesHelp::build_command(Commands::COMMAND_UPDATE_USER_INFO, [
            0 => $employee->pin,
            1 => $employee->emp_name,
            2 => '0',
            3 => '',
            4 => '',
            5 => '',
            6 => '000000100000000']);
            DeviceCmds::insert([
                'DevSN'=>$device->DevSN,
                'Content'=>$cmd,
                'CommitTime'=>Date('Y-m-d H:i:s')
            ]);

            $cmd2=PagesHelp::build_command(Commands::COMMAND_QUERY_USER_INFO, [
            0 => $employee->pin]);

            DeviceCmds::insert([
                'DevSN'=>$device->DevSN,
                'Content'=>$cmd2,
                'CommitTime'=>Date('Y-m-d H:i:s')
            ]);

            return response()->success('Success','Perintah tambah data user dikirim');

        } else {
            return response()->error('',501,'Device '.$sn.' tidak ditemukan');
        }
    }
}
