<?php

namespace App\Http\Controllers\Humans;

use App\Models\Humans\Device;
use App\Models\Humans\AttLog;
use App\Models\Humans\Attendance1;
use App\Models\Humans\Attendance2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    public function show(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy  = $request->sortBy;
        $date    = $request->date ?? '1899-01-901';
        $end_date = $date.' 23:59:59';

        $data= AttLog::from("AttLog as att")
        ->selectRaw("att.ID,att.PIN,att.AttTime,emp.pool_code,
        CASE att.Status
            WHEN '0' THEN 'Masuk'
            WHEN '1' THEN 'Keluar'
            WHEN '255' THEN 'Otomatis'
            ELSE 'Unknown' END as Status,
        att.DeviceID,IFNULL(emp.emp_id,'-') as emp_id,IFNULL(emp.emp_name,'N/A') as emp_name")
        ->leftjoin("m_employee as emp","att.PIN","=","emp.pin")
        ->where("att.AttTime",">=",$date)
        ->where("att.AttTime","<=",$end_date);

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('att.PIN','like',$filter)
                    ->orwhere('emp.emp_id','like',$filter)
                    ->orwhere('emp.emp_name','like',$filter)
                    ->orwhere('emp.pool_code','like',$filter);

            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function show_daily(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy  = $request->sortBy;
        $date    = $request->date ?? '1899-01-01';

        $data= Attendance2::from("t_attendance2 as att")
        ->selectRaw("att.line_id, emp.pin, emp.emp_id, emp.emp_name, att.day_date, att.entry_date, att.leave_date,
        att.leave_status,att.leave_notes,emp.pool_code,
        TIME_FORMAT(SEC_TO_TIME(IFNULL(att.work_hour,0)),'%H:%i') as work_hour")
        ->join("m_employee as emp","att.emp_id","=","emp.emp_id")
        ->where("att.day_date",$date);

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('emp.pin','like',$filter)
                    ->orwhere('emp.emp_id','like',$filter)
                    ->orwhere('emp.emp_name','like',$filter)
                    ->orwhere('emp.pool_code','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function attendance(Request $request){
        $uuid_rec  = $request->uuid_rec;
        $month     = $request->month;
        $year      = $request->year;
        $att1=Attendance1::from("t_attendance1 as att1")
        ->selectRaw("att1.sysid,emp.emp_id, emp.emp_name, emp.pin, att1.month_period, att1.year_period, emp.uuid_rec")
        ->join("m_employee as emp","att1.emp_id","=","emp.emp_id")
        ->where("att1.year_period",$year)
        ->where("att1.month_period",$month)
        ->where("emp.uuid_rec",$uuid_rec)
        ->first();


        $att2= Attendance2::from("t_attendance2 as att")
        ->selectRaw("att.line_id,
        CASE
        WHEN DAYOFWEEK(att.day_date)=1 Then 'Minggu'
        WHEN DAYOFWEEK(att.day_date)=2 Then 'Senin'
        WHEN DAYOFWEEK(att.day_date)=3 Then 'Selasa'
        WHEN DAYOFWEEK(att.day_date)=4 Then 'Rabu'
        WHEN DAYOFWEEK(att.day_date)=5 Then 'Kamis'
        WHEN DAYOFWEEK(att.day_date)=6 Then 'Jum''at'
        WHEN DAYOFWEEK(att.day_date)=7 Then 'Sabtu'
        END as  day_name, att.day_date, att.entry_date, att.leave_date,
        CASE
        WHEN att.leave_status='A' THEN 'ALFA'
        WHEN att.leave_status='I' THEN 'IZIN/CUTI'
        WHEN att.leave_status='S' THEN 'SAKIT'
        WHEN att.leave_status='O' THEN 'DINAS LUAR'
        ELSE ''
        END as leave_status, att.leave_notes,DAYOFWEEK(att.day_date) AS dayweek,
        TIME_FORMAT(SEC_TO_TIME(IFNULL(att.work_hour,0)),'%H:%i') as work_hour,
        hd.notes,IF(hd.ref_date IS NULL, 0, 1) as is_holiday, att.uuid_rec")
        ->leftjoin("m_holidays as hd", "hd.ref_date","=","att.day_date")
        ->where("att.sysid",$att1->sysid ?? -1)
        ->get();

        $data=[
            'attendance' => $att1,
            'details' => $att2
        ];
        return response()->success('Success',$data);
    }

    public function attendance_summary(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy  = $request->sortBy;

        $month     = $request->month;
        $year      = $request->year;
        $data=Attendance1::from("t_attendance1 as att1")
        ->selectRaw("att1.sysid,emp.emp_id, emp.emp_name, emp.pin, att1.month_period, att1.year_period, emp.uuid_rec,emp.pool_code")
        ->join("m_employee as emp","att1.emp_id","=","emp.emp_id")
        ->where("att1.year_period",$year)
        ->where("att1.month_period",$month);

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('att.PIN','like',$filter)
                    ->orwhere('emp.emp_id','like',$filter)
                    ->orwhere('emp.emp_name','like',$filter)
                    ->orwhere('emp.pool_code','like',$filter);

            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);

        $data=$data->toArray();
        $rows=array();
        foreach($data['data'] as $row){
            $atts=Attendance2::selectRaw("DAY(day_date) as day,
            TIME_FORMAT(SEC_TO_TIME(IFNULL(work_hour,0)),'%H:%i') as work_hour,
            CASE
            WHEN leave_status='A' THEN 'ALFA'
            WHEN leave_status='I' THEN 'IZIN/CUTI'
            WHEN leave_status='S' THEN 'SAKIT'
            WHEN leave_status='O' THEN 'DINAS LUAR'
            ELSE ''
            END as leave_status")
            ->where('sysid',$row['sysid'])
            ->orderby("day_date","asc")
            ->get();
            foreach($atts as $att) {
                if ($att->leave_status!=='') {
                    $row['date_'.$att->day]=$att->leave_status;
                } else {
                    $row['date_'.$att->day]=($att->work_hour==="00:00") ? "" :$att->work_hour;
                }
            }
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success',$data);
    }

    public function get_attendance(Request $request) {
        $uuid=$request->uuid_rec;
        $data=Attendance2::selectRaw("
        uuid_rec,
        day_date,
        DATE(entry_date) as entry_date,
        TIME(entry_date) as entry_time,
        DATE(leave_date) as leave_date,
        TIME(leave_date) as leave_time,
        IFNULL(leave_status,'') as leave_status	,
        IFNULL(leave_notes,'') as leave_notes
        ")
        ->where('uuid_rec',$uuid)->first();

        return response()->success('Success',$data);
    }
    public function post_attendance(Request $request) {
        $data = $request->json()->all();
        $rec  = $data['data'];

        $validator=Validator::make($rec,[
            'uuid_rec'=>'bail|required|exists:t_attendance2,uuid_rec',
        ],[
            'uuid_rec.required'=>'UUID absensi harus diisi',
            'uuid_rec.exists'=>'Data absensi tidak ditemukan',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            if (isset($rec['entry_date']) && $rec['entry_time']!='') {
                $rec['entry_date'] = $rec['entry_date'].' '.$rec['entry_time'];
            } else {
                $rec['entry_date'] = null;
            }

            if (isset($rec['leave_date']) && $rec['leave_time']!='') {
                $rec['leave_date'] = $rec['leave_date'].' '.$rec['leave_time'];
            } else {
                $rec['leave_date'] = null;
            }

            if ($rec['leave_status']!="") {
               $rec['entry_date'] = null;
               $rec['leave_date'] = null;
            }

            Attendance2::where('uuid_rec',$rec['uuid_rec'])
            ->update([
                'leave_status'=> $rec['leave_status'],
                'leave_notes' => $rec['leave_notes'],
                'entry_date'  => $rec['entry_date'],
                'leave_date'  => $rec['leave_date'],
                'work_hour'   => 0
            ]);

            DB::update("UPDATE t_attendance2 SET work_hour=TIMESTAMPDIFF(SECOND,entry_date,leave_date)
                        WHERE uuid_rec=? AND leave_date IS NOT NULL AND entry_date IS NOT NULL",
                [$rec['uuid_rec']
            ]);

            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollBack();
            return response()->error('',501,$e);
        }

    }

}
