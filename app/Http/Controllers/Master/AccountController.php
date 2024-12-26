<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Account;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Accounting\Journal1;
use PagesHelp;
use Accounting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PDF;


class AccountController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $accgroup=$request->group_account;
        $accgrp=1;
        if ($accgroup=='AKTIVA') {
          $accgrp=1;
        } elseif ($accgroup=='PASSIVA') {
          $accgrp=2;
        } elseif ($accgroup=='MODAL') {
          $accgrp=3;
        } elseif ($accgroup=='PENDAPATAN') {
          $accgrp=4;
        } elseif ($accgroup=='BIAYA OPERASIONAL') {
          $accgrp=5;
        } elseif ($accgroup=='BIAYA MARKETING') {
          $accgrp=6;
        } elseif ($accgroup=='BIAYA UMUM') {
          $accgrp=7;
        } elseif ($accgroup=='BIAYA & PENDAPATAN LAIN') {
          $accgrp=8;
        } elseif ($accgroup=='PAJAK') {
          $accgrp=9;
        }
        $descending = $request->descending=="true";
        $sortBy =isset($request->sortBy) ? $request->sortBy :'';
        $data= Account::selectRaw("account_no,account_name,account_header,account_group,level_account,
        is_header,enum_drcr,IFNULL(is_cash_bank,0) as is_cash_bank,is_active,IFNULL(mandatory_division,0) as mandatory_division,
        IFNULL(mandatory_unit,0) as mandatory_unit,intransit,IFNULL(voucher_in,0) as voucher_in,IFNULL(voucher_out,0) as voucher_out,
        is_posted")
        ->where('account_group',$accgrp);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('account_no','like',$filter)
                    ->orwhere('account_name','like',$filter);
            });
        }
        $data=$data->orderBy('account_no','asc')->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $account_no=$request->account_no;
        $data=Account::where('account_no',$account_no)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                Account::where('account_no',$account_no)->delete();
                PagesHelp::write_log($request,'',$account_no,'','Delete recods ['.$account_no.'-'.$data->account_name.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch(Exception $e){
                DB:roolback();
               return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $account_no=$request->account_no;
        $data= Account::selectRaw("account_no,account_name,account_header,account_group,level_account,
        is_header,enum_drcr,IFNULL(is_cash_bank,0) as is_cash_bank,is_active,IFNULL(mandatory_division,0) as mandatory_division,
        IFNULL(mandatory_unit,0) as mandatory_unit,intransit,IFNULL(voucher_in,0) as voucher_in,IFNULL(voucher_out,0) as voucher_out,
        is_posted")
        ->where('account_no',$account_no)->first();
        return response()->success('Success',$data);
    }

    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['update_userid']=PagesHelp::UserID($request);
        $validator=Validator::make($rec,[
            'account_no'=>'bail|required',
            'account_name'=>'bail|required',
            'account_group'=>'bail|required',
            'level_account'=>'bail|required',
            'is_posted'=>'bail|required',
            'is_cash_bank'=>'bail|required',
            'enum_drcr'=>'bail|required',
        ],[
            'account_no.required'=>'No akun/perkiraan harus diisi',
            'account_name.required'=>'Nama akun harus diisi',
            'account_group.required'=>'grup akun harus diisi',
            'level_account.required'=>'level akun harus diisi',
            'is_posted.required'=>'Flag posting harus diisi',
            'is_cash_bank.required'=>'Tipe akun kas/bank harus diisi',
            'enum_drcr.required'=>'Flag Dr/Cr harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Account::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Account::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['account_no'],'Add/Update recod ['.$rec['account_no'].'-'.$rec['account_name'].']');
            DB::commit();
            return response()->success('Success',"Simpan data Berhasil");
		} catch (\Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getAccount(Request $request){
        $account=Account::select('account_no',DB::raw('CONCAT(account_no, " - ", account_name) AS account_name'))
             ->where('is_header','0')
             ->where(\DB::raw('substr(account_no, 1, 1)'), '<=','2')
             ->get();
        return response()->success('Success',$account);
    }
    public function getAccountHeader(Request $request){
        $group=$request->account_group;
        $account=Account::select('account_no','level_account','enum_drcr',DB::raw('CONCAT(account_no, " - ", account_name) AS account_name'))
             ->where('is_header','1')
             ->where('account_group','1')
             ->where('account_group',$group)
             ->get();
        return response()->success('Success',$account);
    }

    public function lookup(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy =isset($request->sortBy) ? $request->sortBy :'';
        $data= Account::selectRaw("account_no,account_name,enum_drcr,IFNULL(is_cash_bank,'') as is_cash_bank,is_posted,is_active")
        ->where('is_active','1');

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('account_no','like',$filter)
                    ->orwhere('account_name','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public static function Getjurnaltype(){
        $data=DB::table('m_jurnal_type')->select('jurnal_type','descriptions')->get();
        return response()->success('Success',$data);
    }

    public static function verified(Request $request){
        $data= $request->json()->all();
        $jurnal=Journal1::selectRaw("sysid")->where("uuid_rec",$data['uuid'] ??'')->first();
        if (!$jurnal) {
            return response()->error('', 501, "Jurnal tidak ditemukan");
        }
        Accounting::Verified($jurnal->sysid,$request);
        return response()->success('Success','Verifikasi jurnal berhasil');
    }
    public static function unverified(Request $request){
        $data= $request->json()->all();
        $jurnal=Journal1::selectRaw("sysid")->where("uuid_rec",$data['uuid'] ??'')->first();
        if (!$jurnal) {
            return response()->error('', 501, "Jurnal tidak ditemukan");
        }
        Accounting::UnVerified($jurnal->sysid);
        return response()->success('Success','Batal verifikasi erhasil');
    }

    public static function GeneralLedger(Request $request){
        $limit = $request->limit;
        $no_account= isset($request->no_account) ? $request->no_account :'';
        $project= isset($request->project) ? $request->project :'00';
        $date1= $request->date1 ?? '1899-31-12';
        $date2= $request->date2 ?? '1899-31-12';
        $data=Accounting::GeneralLedger($no_account,$project,$date1,$date2,$limit);
        return response()->success('Success',$data);
    }

    public static function Mutation(Request $request){
        $month= isset($request->month) ? $request->month : 0;
        $year= isset($request->year) ? $request->year : 0;
        $model= isset($request->model) ? $request->model : 'neraca';
        $oneyear= isset($request->oneyear) ? $request->oneyear : '0';

        $project_code = isset($request->project_code) ? $request->project_code : '00';
        $project_code = ($model=='neraca') ? '00' : $project_code;

        $data=Accounting::Mutation($month,$year,$model,$project_code,$oneyear);

        return response()->success('Success',$data);
    }

    public static function MutationXLS(Request $request){
        $month= isset($request->month) ? $request->month : 0;
        $year= isset($request->year) ? $request->year : 0;
        $model= isset($request->model) ? $request->model : 'neraca';
        $oneyear= isset($request->oneyear) ? $request->oneyear : '0';
        $project_code= isset($request->project_code) ? $request->project_code : '0';
        $data=Accounting::Mutation($month,$year,$model,$project_code,$oneyear);
        $project_name=DB::table('m_project')->where('project_code',$project_code)->value("project_name");
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'MUTASI AKUN/TRIAL BALANCE');
        $sheet->setCellValue('A3', 'PROYEK');
        $sheet->setCellValue('B3', ': '.$project_code.' - '.$project_name);
        $sheet->setCellValue('A4', 'PERIODE');
        if ($oneyear=='0'){
            $sheet->setCellValue('B4', ': '.$month.'-'.$year);
        } else {
            $sheet->setCellValue('B4', ': '.$year);
        }

        $sheet->setCellValue('A5', 'No. Akun');
        $sheet->setCellValue('B5', 'Nama Akun');
        $sheet->setCellValue('C5', 'Saldo Awal');
        $sheet->setCellValue('D5', 'Mutasi Debit');
        $sheet->setCellValue('E5', 'Mutasi Kredit');
        $sheet->setCellValue('F5', 'Saldo Akhir');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row['account_no']);
            $sheet->setCellValue('B'.$idx, $row['account_name']);
            $sheet->setCellValue('C'.$idx, $row['reversed1']);
            $sheet->setCellValue('D'.$idx, $row['reversed2']);
            $sheet->setCellValue('E'.$idx, $row['reversed3']);
            $sheet->setCellValue('F'.$idx, $row['reversed4']);
        }

        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('C'.$idx, "=SUM(C6:C$last)");
        $sheet->setCellValue('D'.$idx, "=SUM(D6:D$last)");
        $sheet->setCellValue('E'.$idx, "=SUM(E6:E$last)");
        $sheet->setCellValue('F'.$idx, "=SUM(E6:F$last)");
        $sheet->getStyle('C6:F'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        // Formater
        $sheet->getStyle('A1:F5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'F'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:F'.$idx)->applyFromArray($styleArray);
        foreach(range('C','F') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(50);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="trialbalance_gl.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public static function MutationRL(Request $request){
        $month= isset($request->month) ? $request->month : 0;
        $year= isset($request->year) ? $request->year : 0;
        $model= isset($request->model) ? $request->model : 'labarugi';
        $oneyear= isset($request->oneyear) ? $request->oneyear : '0';
        $project_code= isset($request->project_code) ? $request->project_code : '0';
        $data=Accounting::Mutation($month,$year,$model,$project_code,$oneyear);
        return response()->success('Success',$data);
    }

    public static function MutationRLXLS(Request $request){
        $month= isset($request->month) ? $request->month : 0;
        $year= isset($request->year) ? $request->year : 0;
        $model= isset($request->model) ? $request->model : 'labarugi';
        $oneyear= isset($request->oneyear) ? $request->oneyear : '0';
        $project_code= isset($request->project_code) ? $request->project_code : '0';
        $data=Accounting::Mutation($month,$year,$model,$project_code,$oneyear);
        $project_name=DB::table('m_project')->where('project_code',$project_code)->value("project_name");
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'MUTASI AKUN/TRIAL BALANCE');
        $sheet->setCellValue('A3', 'PROYEK');
        $sheet->setCellValue('B3', ': '.$project_code.' - '.$project_name);
        $sheet->setCellValue('A4', 'PERIODE');
        if ($oneyear=='0'){
            $sheet->setCellValue('B4', ': '.$month.'-'.$year);
        } else {
            $sheet->setCellValue('B4', ': '.$year);
        }

        $sheet->setCellValue('A5', 'No. Akun');
        $sheet->setCellValue('B5', 'Nama Akun');
        $sheet->setCellValue('C5', 'Saldo Awal');
        $sheet->setCellValue('D5', 'Mutasi Debit');
        $sheet->setCellValue('E5', 'Mutasi Kredit');
        $sheet->setCellValue('F5', 'Saldo Akhir');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row['account_no']);
            $sheet->setCellValue('B'.$idx, $row['account_name']);
            $sheet->setCellValue('C'.$idx, $row['reversed1']);
            $sheet->setCellValue('D'.$idx, $row['reversed2']);
            $sheet->setCellValue('E'.$idx, $row['reversed3']);
            $sheet->setCellValue('F'.$idx, $row['reversed4']);
        }

        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('C'.$idx, "=SUM(C6:C$last)");
        $sheet->setCellValue('D'.$idx, "=SUM(D6:D$last)");
        $sheet->setCellValue('E'.$idx, "=SUM(E6:E$last)");
        $sheet->setCellValue('F'.$idx, "=SUM(E6:F$last)");
        $sheet->getStyle('C6:F'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        // Formater
        $sheet->getStyle('A1:F5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'F'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:F'.$idx)->applyFromArray($styleArray);
        foreach(range('C','F') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(50);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="trialbalance_rl.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public static function GeneralLedgerXLS(Request $request){
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        $limit = $request->limit;
        $no_account= isset($request->no_account) ? $request->no_account :'';
        $project= isset($request->project) ? $request->project :'00';
        $start_date= $request->date1;
        $end_date= $request->date2;

        $data=Accounting::GeneralLedger($no_account,$project,$start_date,$end_date,$limit,true);
        $acc=DB::table('m_account')->select('account_name')->where('account_no',$no_account)->first();
        $account_name = $acc->account_name ?? '';

        $prj=DB::table('m_project')->select('project_name')->where('project_code',$project)->first();
        $project_name=$prj->project_name ?? '';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'BUKU BESAR');
        $sheet->setCellValue('A2', 'AKUN');
        $sheet->setCellValue('B2', ': '.$no_account.' - '.$account_name);
        $sheet->setCellValue('A3', 'PROYEK');
        $sheet->setCellValue('B3', ': '.$project.' - '.$project_name);
        $sheet->setCellValue('A4', 'PERIODE');
        $sheet->setCellValue('B4', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));

        $sheet->setCellValue('A5', 'Pool');
        $sheet->setCellValue('B5', 'Voucher');
        $sheet->setCellValue('C5', 'Tanggal');
        $sheet->setCellValue('D5', 'Proyek');
        $sheet->setCellValue('E5', 'Keterangan');
        $sheet->setCellValue('F5', 'Referensi 1');
        $sheet->setCellValue('G5', 'Referensi 2');
        $sheet->setCellValue('H5', 'Saldo Awal');
        $sheet->setCellValue('I5', 'Debit');
        $sheet->setCellValue('J5', 'Kredit');
        $sheet->setCellValue('K5', 'Saldo Akhir');
        $sheet->setCellValue('L5', 'Void');
        $sheet->setCellValue('M5', 'Terverif');
        $sheet->getStyle('A5:M5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row['pool_code']);
            $sheet->setCellValue('B'.$idx, $row['voucher']);
            $sheet->setCellValue('C'.$idx, $row['ref_date']);
            $sheet->setCellValue('D'.$idx, $row['project']);
            $sheet->setCellValue('E'.$idx, $row['line_memo']);
            $sheet->setCellValue('F'.$idx, $row['reference1']);
            $sheet->setCellValue('G'.$idx, $row['reference2']);
            $sheet->setCellValue('H'.$idx, $row['begining']);
            $sheet->setCellValue('I'.$idx, $row['debit']);
            $sheet->setCellValue('J'.$idx, $row['credit']);
            $sheet->setCellValue('K'.$idx, $row['last']);
            $sheet->setCellValue('L'.$idx, $row['is_void']);
            $sheet->setCellValue('M'.$idx, $row['is_verified']);
        }

        $sheet->getStyle('C6:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D6:G'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('I'.$idx, "=SUM(H6:H$last)");
        $sheet->setCellValue('J'.$idx, "=SUM(I6:I$last)");
        $sheet->getStyle('H6:K'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        // Formater
        $sheet->getStyle('A1:M5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'M'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:L'.$idx)->applyFromArray($styleArray);
        foreach(range('C','L') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="buku_besar_".$no_account.'_'.$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public static function GeneralLedger_all(Request $request){
        $isgl= isset($request->isgl) ? $request->isgl :'1';
        $single_year=isset($request->single_year) ? $request->single_year :'0';
        $project= isset($request->project) ? $request->project :'00';
        $start_date=$request->year_period.'-'.$request->month_period.'-01';
        $end_date= date("Y-m-t", strtotime($start_date));
        if ($single_year=='1') {
            $start_date=$request->year_period.'-01-01';
            $end_date= $request->year_period.'-12-31';
        }
        $account=Account::selectRaw("account_no,enum_drcr,account_name,account_group")
        ->where('is_posted','1')
        ->where('is_active','1');

        if ($isgl=='1'){
            $account=$account->whereIn('account_group',[1,2,3]);
        } else {
            $account=$account->whereNotIn('account_group',[1,2,3]);
        }

        $account=$account->orderBy('account_no','asc')->get();
        $index=-1;
        $spreadsheet = new Spreadsheet();
        $prj=DB::table('m_project')->select('project_name')->where('project_code',$project)->first();
        if ($prj){
            $project_name=$prj->project_name;
        } else {
            $project_name='';
        }

        foreach($account as $line) {
            $index=$index+1;
            $data=Accounting::GeneralLedger($line->account_no,$project,$start_date,$end_date,0,true,$single_year);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($line['account_no']);
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

            $sheet->setCellValue('A1', 'BUKU BESAR');
            $sheet->setCellValue('A2', 'AKUN');
            $sheet->setCellValue('B2', ': '.$line['account_no'].' - '.$line['account_name']);
            $sheet->setCellValue('A3', 'PROYEK');
            $sheet->setCellValue('B3', ': '.$project.' - '.$project_name);
            $sheet->setCellValue('A4', 'PERIODE');
            $sheet->setCellValue('B4', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));

            $sheet->setCellValue('A5', 'Pool');
            $sheet->setCellValue('B5', 'Voucher');
            $sheet->setCellValue('C5', 'Tanggal');
            $sheet->setCellValue('D5', 'Proyek');
            $sheet->setCellValue('E5', 'Keterangan');
            $sheet->setCellValue('F5', 'Referensi 1');
            $sheet->setCellValue('G5', 'Referensi 2');
            $sheet->setCellValue('H5', 'Saldo Awal');
            $sheet->setCellValue('I5', 'Debit');
            $sheet->setCellValue('J5', 'Kredit');
            $sheet->setCellValue('K5', 'Saldo Akhir');
            $sheet->setCellValue('L5', 'Void');
            $sheet->setCellValue('M5', 'Terverif');
            $sheet->getStyle('A5:M5')->getAlignment()->setHorizontal('center');

            $idx=5;
            $last=0;
            foreach($data as $row){
                $idx=$idx+1;
                $sheet->setCellValue('A'.$idx, $row['pool_code']);
                $sheet->setCellValue('B'.$idx, $row['voucher']);
                $sheet->setCellValue('C'.$idx, $row['ref_date']);
                $sheet->setCellValue('D'.$idx, $row['project']);
                $sheet->setCellValue('E'.$idx, $row['line_memo']);
                $sheet->setCellValue('F'.$idx, $row['reference1']);
                $sheet->setCellValue('G'.$idx, $row['reference2']);
                $sheet->setCellValue('H'.$idx, $row['begining']);
                $sheet->setCellValue('I'.$idx, $row['debit']);
                $sheet->setCellValue('J'.$idx, $row['credit']);
                $sheet->setCellValue('K'.$idx, $row['last']);
                $sheet->setCellValue('L'.$idx, $row['is_void']);
                $sheet->setCellValue('M'.$idx, $row['is_verified']);
            }

            $sheet->getStyle('C6:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            $sheet->getStyle('D6:G'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            $last=$idx;
            $idx=$idx+1;
            $sheet->setCellValue('I'.$idx, "=SUM(H6:H$last)");
            $sheet->setCellValue('J'.$idx, "=SUM(I6:I$last)");
            $sheet->getStyle('H6:K'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
            // Formater
            $sheet->getStyle('A1:M5')->getFont()->setBold(true);
            $sheet->getStyle('A'.$idx.':'.'M'.$idx)->getFont()->setBold(true);
            $styleArray = [
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ],
                        ],
                    ];
            $sheet->getStyle('A5:L'.$idx)->applyFromArray($styleArray);
            foreach(range('C','L') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(15);
        }
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="buku_besar_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
