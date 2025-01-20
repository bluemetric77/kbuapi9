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
use PDF;
use QrCode;
use PagesHelp;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;


class AttendancePrintController extends Controller
{
    public function print_attendance_log(Request $request) {
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
        ->where("att.AttTime","<=",$end_date)
        ->orderBy("att.Status","asc")
        ->get();

        if ($data){
            //$data->AttTime=date_format(date_create($data->AttTime),'d-m-Y H:i');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('humans.attendanceLog',['att'=>$data,'profile'=>$profile])->setPaper('legal','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }

    }
    public function download_attendance_log(Request $request) {
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
        ->where("att.AttTime","<=",$end_date)
        ->orderBy("att.Status","asc")
        ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LOG ABSENSI HARIAN');
        $sheet->setCellValue('A2', 'TANGGAL');
        $sheet->setCellValue('B2', ': '.date_format(date_create($date),"d-m-Y"));

        $sheet->setCellValue('A5', 'Pool');
        $sheet->setCellValue('B5', 'No.Pegawai');
        $sheet->setCellValue('C5', 'PIN');
        $sheet->setCellValue('D5', 'Nama Pegawai');
        $sheet->setCellValue('E5', 'Jam Abasensi');
        $sheet->setCellValue('F5', 'Status');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');
        $idx=5;
        foreach($data as $row) {

            $AttTime=  strtotime($row->AttTime);

            /*if ($AttTime !== false) {
             $line->AttTime= Date("d-m-Y H:i",$AttTime);
            }*/

            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->pool_code);
            $sheet->setCellValueExplicit('B' . $idx, $row->emp_id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $idx, $row->emp_id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D'.$idx, $row->emp_name);
            $sheet->setCellValue('E'.$idx, $row->AttTime);
            $sheet->setCellValue('F'.$idx, $row->Status);
        }

        // Formater
        $sheet->getStyle('A1:F5')->getFont()->setBold(true);
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A5:F' . ($idx + 1))->applyFromArray($styleArray);
        foreach(range('C','F') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="log_absensi_harian_".$date.".xlsx";
        PagesHelp::Response($response,$xls)->send();

    }

    public function print_attendance_daily(Request $request) {
        $date    = $request->date ?? '1899-01-901';
        $end_date = $date.' 23:59:59';

        $data= Attendance2::from("t_attendance2 as att")
        ->selectRaw("att.line_id, emp.pin, emp.emp_id, emp.emp_name, att.day_date, att.entry_date, att.leave_date,
        att.leave_status,att.leave_notes,emp.pool_code,
        TIME_FORMAT(SEC_TO_TIME(IFNULL(att.work_hour,0)),'%H:%i') as work_hour,
        CASE
        WHEN att.leave_status='A' THEN 'ALFA'
        WHEN att.leave_status='I' THEN 'IZIN/CUTI'
        WHEN att.leave_status='S' THEN 'SAKIT'
        WHEN att.leave_status='O' THEN 'DINAS LUAR'
        ELSE ''
        END as leave_status, att.leave_notes,DAYOFWEEK(att.day_date) AS dayweek,
        hd.notes,IF(hd.ref_date IS NULL, 0, 1) as is_holiday")
        ->join("m_employee as emp","att.emp_id","=","emp.emp_id")
        ->leftjoin("m_holidays as hd", "hd.ref_date","=","att.day_date")
        ->where("att.day_date",$date)
        ->get();

        if ($data){
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('humans.attendanceDaily',['att'=>$data,'profile'=>$profile])->setPaper('legal','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }

    }
    public function download_attendance_daily(Request $request) {
        $date    = $request->date ?? '1899-01-901';
        $end_date = $date.' 23:59:59';

        $data= Attendance2::from("t_attendance2 as att")
        ->selectRaw("att.line_id, emp.pin, emp.emp_id, emp.emp_name, att.day_date, att.entry_date, att.leave_date,
        att.leave_status,att.leave_notes,emp.pool_code,
        TIME_FORMAT(SEC_TO_TIME(IFNULL(att.work_hour,0)),'%H:%i') as work_hour,
        CASE
        WHEN att.leave_status='A' THEN 'ALFA'
        WHEN att.leave_status='I' THEN 'IZIN/CUTI'
        WHEN att.leave_status='S' THEN 'SAKIT'
        WHEN att.leave_status='O' THEN 'DINAS LUAR'
        ELSE ''
        END as leave_status, att.leave_notes,DAYOFWEEK(att.day_date) AS dayweek,
        hd.notes,IF(hd.ref_date IS NULL, 0, 1) as is_holiday")
        ->join("m_employee as emp","att.emp_id","=","emp.emp_id")
        ->leftjoin("m_holidays as hd", "hd.ref_date","=","att.day_date")
        ->where("att.day_date",$date)
        ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'ABSENSI HARIAN');
        $sheet->setCellValue('A2', 'TANGGAL');
        $sheet->setCellValue('B2', ': '.date_format(date_create($date),"d-m-Y"));

        $sheet->setCellValue('A5', 'Pool');
        $sheet->setCellValue('B5', 'No.Pegawai');
        $sheet->setCellValue('C5', 'PIN');
        $sheet->setCellValue('D5', 'Nama Pegawai');
        $sheet->setCellValue('E5', 'Masuk');
        $sheet->setCellValue('F5', 'Keluar');
        $sheet->setCellValue('G5', 'Status');
        $sheet->setCellValue('H5', 'Keterangan');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');
        $idx=5;
        foreach($data as $row) {

            $AttTime=  strtotime($row->AttTime);

            $day_date= new \DateTime($row->day_date);
            $row->day_date= $day_date->format('d-m-Y');

            if ($row->entry_date) {
                $entry= date_create($row->entry_date);
                $row->entry_date= $entry->format('H:i');
            }
            if ($row->leave_date) {
                $leave= date_create($row->leave_date);
                $row->leave_date= $leave->format('H:i');
            }

            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->pool_code);
            $sheet->setCellValueExplicit('B' . $idx, $row->emp_id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $idx, $row->emp_id, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D'.$idx, $row->emp_name);
            $sheet->setCellValue('E'.$idx, $row->entry_date);
            $sheet->setCellValue('F'.$idx, $row->leave_date);
            $sheet->setCellValue('G'.$idx, $row->leave_status);
            $sheet->setCellValue('H'.$idx, $row->leave_notes);
        }

        // Formater
        $sheet->getStyle('A1:H5')->getFont()->setBold(true);
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A5:H' . ($idx + 1))->applyFromArray($styleArray);
        foreach(range('C','H') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="absensi_harian_".$date.".xlsx";
        PagesHelp::Response($response,$xls)->send();

    }

    public function print_attendance_employee(Request $request) {
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

        if ($data){
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('humans.attendanceEmployee',['attendance'=>$att1,'details'=>$att2,'profile'=>$profile])->setPaper('legal','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }

    }
    public function download_attendance_employee(Request $request) {
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

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'ABSENSI HARIAN');
        $sheet->setCellValue('A2', 'NIP/KARYAWAN');
        $sheet->setCellValue('B2', ': '.$att1->emp_id.' '.$att1->emp_name);
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.$att1->month_period.' '.$att1->year_period);

        $sheet->setCellValue('A5', 'Hari');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'Masuk');
        $sheet->setCellValue('D5', 'Keluar');
        $sheet->setCellValue('E5', 'Status');
        $sheet->setCellValue('F5', 'Alasan');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');
        $idx=5;
        foreach($att2 as $row) {

            $AttTime=  strtotime($row->AttTime);

            $day_date= new \DateTime($row->day_date);
            $row->day_date= $day_date->format('d-m-Y');

            if ($row->entry_date) {
                $entry= date_create($row->entry_date);
                $row->entry_date= $entry->format('H:i');
            }
            if ($row->leave_date) {
                $leave= date_create($row->leave_date);
                $row->leave_date= $leave->format('H:i');
            }

            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->day_name);
            $sheet->setCellValue('B'.$idx, $row->day_date);
            $sheet->setCellValue('C'.$idx, $row->entry_date);
            $sheet->setCellValue('D'.$idx, $row->leave_date);
            $sheet->setCellValue('E'.$idx, $row->leave_status);
            $sheet->setCellValue('F'.$idx, $row->leave_notes);
        }

        // Formater
        $sheet->getStyle('A1:F5')->getFont()->setBold(true);
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A5:F' . ($idx + 1))->applyFromArray($styleArray);
        foreach(range('C','F') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="absensi_harian_".$att1->emp_id.".xlsx";
        PagesHelp::Response($response,$xls)->send();

    }

}
