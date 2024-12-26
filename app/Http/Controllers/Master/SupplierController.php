<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Partner;
use App\Models\Finance\CustomerAccount;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Validator;
use PagesHelp;

class SupplierController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $data= Partner::select('sysid','partner_id','partner_name','partner_address','invoice_address','invoice_handler','phone_number','fax_number','email','contact_person','contact_phone','tax_number',
        'partner_type','due_interval','is_active','cash_id','format_id','ar_account','ap_account','dp_account','is_document','update_timestamp')
        ->where('partner_type','S');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data
               ->where(function($q) use ($filter) {
                    $q->where('partner_name','like',$filter)
                        ->orwhere('partner_address','like',$filter)
                        ->orwhere('phone_number','like',$filter)
                        ->orwhere('email','like',$filter);
               });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $sysid=$request->sysid;
        $data=Partner::where('sysid',$sysid)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                Partner::where('sysid',$sysid)->delete();
                PagesHelp::write_log($request,-1,$data->partner_id,'Delete recods ['.$data->partner_id.'-'.$data->partner_name.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch (Exception $e) {
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $sysid=$request->sysid;
        $data=Partner::where('sysid',$sysid)->first();
        return response()->success('Success',$data);
    }

    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['update_userid']=PagesHelp::UserID($request);
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        $rec['partner_type']='S';
        $validator=Validator::make($rec,[
            'partner_id'=>'bail|required',
            'partner_name'=>'bail|required'
        ],[
            'partner_id.required'=>'Kode supplier harus diisi',
            'partner_name.required'=>'Nama supplier harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Partner::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                $rec['partner_type']='S';
                Partner::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['partner_id'],'Add/Update record ['.$rec['partner_id'].'-'.$rec['partner_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getSupplier(Request $request){
        $data=Partner::select('partner_id',DB::raw('CONCAT(partner_name, " - ", partner_id) AS partner_name'))
            ->where('partner_type','S')
            ->where('is_active','1')
            ->orderBy('partner_name')
            ->get();
        return response()->success('Success',$data);
    }

    public function lookup(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $data= Partner::select('sysid','partner_id','partner_name','partner_address','phone_number')
        ->where('partner_type','S')
        ->where('is_active','1');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data
               ->where(function($q) use ($filter) {
                    $q->where('partner_name','like',$filter)
                        ->orwhere('partner_address','like',$filter)
                        ->orwhere('phone_number','like',$filter)
                        ->orwhere('email','like',$filter);
               });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $state = isset($request->state) ? $request->state : 3;
        $data= CustomerAccount::from('t_customer_account as a')
        ->selectRaw("a.sysid AS _index,a.doc_number,a.doc_source,a.reference,a.ref_date,a.due_date,a.partner_id,b.partner_name,a.amount,
                a.total_paid,a.amount-a.total_paid AS unpaid,a.no_account,a.approved_by,a.approved_date")
        ->leftJoin('m_partner as b','a.partner_id','=','b.partner_id');
        if ($state==1) {
            $data=$data->whereRaw('a.due_date <=CURRENT_DATE()');
        } elseif ($state==2) {
            $data=$data->whereRaw('a.due_date > CURRENT_DATE()');
        }
        $data=$data->whereRaw("(a.amount-a.total_paid)>0  AND IFNULL(a.is_approved,0)=1");
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.partner_id', 'like', $filter)
                    ->orwhere('b.partner_name', 'like', $filter);
            });
        }

        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
    public function report(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $state = isset($request->state) ? $request->state : 3;
        $data= CustomerAccount::from('t_customer_account as a')
        ->selectRaw("a.sysid AS _index,a.doc_number,a.doc_source,a.reference,a.ref_date,a.due_date,a.partner_id,b.partner_name,a.amount,
                a.total_paid,a.amount-a.total_paid AS unpaid,a.no_account,a.approved_by,a.approved_date")
        ->leftJoin('m_partner as b','a.partner_id','=','b.partner_id');
        if ($state==1) {
            $data=$data->whereRaw('a.due_date <=CURRENT_DATE()');
        } elseif ($state==2) {
            $data=$data->whereRaw('a.due_date > CURRENT_DATE()');
        }
        $data=$data->whereRaw("(a.amount-a.total_paid)>0 AND IFNULL(a.is_approved,0)=1");
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'RINCIAN HUTANG PER DOKUMEN');
        $sheet->setCellValue('A3', 'No.Bukti');
        $sheet->setCellValue('B3', 'Tanggal');
        $sheet->setCellValue('C3', 'Jatuh Tempo');
        $sheet->setCellValue('D3', 'Invoice Supplier');
        $sheet->setCellValue('E3', 'Kode Supplier');
        $sheet->setCellValue('F3', 'Nama Supplier');
        $sheet->setCellValue('G3', 'Hutang');
        $sheet->setCellValue('H3', 'Pembayaran');
        $sheet->setCellValue('I3', 'Sisa');
        $sheet->setCellValue('J3', 'Akun Hutang');
        $sheet->setCellValue('K3', 'DiPeriksa');
        $sheet->setCellValue('L3', 'Tgl.Periksa');
        $sheet->getStyle('A5:L3')->getAlignment()->setHorizontal('center');

        $idx=3;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->due_date);
            $sheet->setCellValue('D'.$idx, $row->reference);
            $sheet->setCellValue('E'.$idx, $row->partner_id);
            $sheet->setCellValue('F'.$idx, $row->partner_name);
            $sheet->setCellValue('G'.$idx, $row->amount);
            $sheet->setCellValue('H'.$idx, $row->total_paid);
            $sheet->setCellValue('I'.$idx, $row->unpaid);
            $sheet->setCellValue('J'.$idx, $row->no_account);
            $sheet->setCellValue('K'.$idx, $row->approved_by);
            $sheet->setCellValue('L'.$idx, $row->approved_date);
        }

        $sheet->getStyle('B4:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('C4:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D4:D'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('J4:J'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('L4:L'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('G'.$idx, "=SUM(G6:G$last)");
        $sheet->setCellValue('H'.$idx, "=SUM(H6:H$last)");
        $sheet->setCellValue('I'.$idx, "=SUM(H6:I$last)");
        $sheet->getStyle('G4:I'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:L3')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'L'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A3:L'.$idx)->applyFromArray($styleArray);
        foreach(range('C','L') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_saldo_gudang.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
   public function query_summary(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $data=Partner::from('m_partner as a')
        ->selectRaw('a.partner_id,a.partner_name,b.amount')
        ->join(DB::raw("(SELECT partner_id,SUM(amount-total_paid) AS amount FROM t_customer_account
                WHERE IFNULL(is_paid,0)=0  AND IFNULL(is_approved,0)=1 GROUP BY partner_id) as b"),
                function ($join){
                    $join->on('a.partner_id','=','b.partner_id');
                });
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.partner_id', 'like', $filter)
                    ->orwhere('a.partner_name', 'like', $filter);
            });
        }
        if ($sortBy=='ref_date') {
            $sortBy='a.partner_id';
        }

        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
   public function query_summaryxls(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $data=Partner::from('m_partner as a')
        ->selectRaw('a.partner_id,a.partner_name,b.amount')
        ->join(DB::raw("(SELECT partner_id,SUM(amount-total_paid) AS amount FROM t_customer_account
                WHERE IFNULL(is_paid,0)=0  GROUP BY partner_id) as b"),
                function ($join){
                    $join->on('a.partner_id','=','b.partner_id');
                });
        $data=$data->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'HUTANG SUPPLIER');
        $sheet->setCellValue('A3', 'Kode');
        $sheet->setCellValue('B3', 'Nama Supplier');
        $sheet->setCellValue('C3', 'Saldo Hutang');
        $sheet->getStyle('A3:C3')->getAlignment()->setHorizontal('center');
        $idx=3;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->partner_id);
            $sheet->setCellValue('B'.$idx, $row->partner_name);
            $sheet->setCellValue('C'.$idx, $row->amount);
        }
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('B'.$idx, "TOTAL");
        $sheet->setCellValue('C'.$idx, "=SUM(C4:C$last)");
        $sheet->getStyle('C4:C'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:C3')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'C'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A3:C'.$idx)->applyFromArray($styleArray);
        foreach(range('C','C') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(40);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_saldo_hutang.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

}
