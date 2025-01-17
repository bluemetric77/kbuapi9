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
        ->selectRaw("att.ID,att.PIN,att.AttTime,
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
                    ->orwhere('emp.emp_name','like',$filter);
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
        att.leave_status,att.leave_notes,
        TIME_FORMAT(SEC_TO_TIME(IFNULL(att.work_hour,0)),'%H:%i') as work_hour")
        ->join("m_employee as emp","att.emp_id","=","emp.emp_id")
        ->where("att.day_date",$date);

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('emp.pin','like',$filter)
                    ->orwhere('emp.emp_id','like',$filter)
                    ->orwhere('emp.emp_name','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }
}
