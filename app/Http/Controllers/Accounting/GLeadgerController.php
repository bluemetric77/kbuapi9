<?php

namespace App\Http\Controllers\Accounting;

use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Master\Account;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

class GLeadgerController extends Controller
{
    public function show(Request $request)
    {
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending == "true") ?'desc' :'asc';
        $sortBy  = $request->sortBy;

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $pool_code=PagesHelp::Session()->pool_code;

        $data= Journal1::from('t_jurnal1 as a')
        ->selectRaw("a.sysid,a.pool_code,a.ref_date,a.reference1,a.reference2,CONCAT(a.trans_code,'-',a.trans_series) as voucher,
        a.debit,a.credit,a.notes,a.is_void,a.is_verified,a.verified_date,a.uuid_rec")
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.pool_code', $pool_code)
        ->where('a.is_deleted','0');

        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.trans_code', 'like', $filter)
                    ->orwhere('a.trans_series', 'like', $filter)
                    ->orwhere('a.reference1', 'like', $filter)
                    ->orwhere('a.reference2', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }

    public function inquery(Request $request)
    {
        $filter     = $request->filter;
        $limit      = $request->limit;
        $sorting    = ($request->descending == "true") ?'desc' :'asc';
        $sortBy     = $request->sortBy;

        $start_date = $request->start_date;
        $end_date   = $request->end_date;
        $jurnaltype=isset($request->jurnaltype) ? intval($request->jurnaltype) : -99;

        $data= Journal1::from('t_jurnal1 as a')
        ->selectRaw("a.sysid as _index,a.sysid,a.pool_code,a.ref_date,a.reference1,a.reference2,a.trans_code,a.trans_series,
                     a.debit,a.credit,a.notes,a.is_void,a.is_verified,a.verified_date,uuid_rec")
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_deleted','0');

        if ($jurnaltype!=-1){
            $data=$data->where('a.transtype',$jurnaltype);
        }

        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.trans_code', 'like', $filter)
                    ->orwhere('a.trans_series', 'like', $filter)
                    ->orwhere('a.reference1', 'like', $filter)
                    ->orwhere('a.reference2', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);
        return response()->success('Success', $data);
    }


    public function get(Request $request)
    {
        $uuid = $request->uuid ?? '';
        $header = Journal1::selectRaw("sysid,ref_date,pool_code,reference1,reference2,trans_code,trans_series,
        fiscal_year,fiscal_month,debit,credit,transtype,notes,uuid_rec")
        ->where('uuid_rec', $uuid)
        ->where('is_deleted',0)
        ->first();

        $detail=Journal2::selectRaw('sysid,line_no,no_account,description,
            line_memo,debit,credit,reference1,reference2,project')
            ->where('sysid',$header->sysid ?? -1)
            ->get();

        $data=[
            'header'=>$header,
            'detail'=>$detail
        ];
        return response()->success('Success', $data);
    }
    public function post(Request $request)
    {
        $data = $request->json()->all();
        $header = $data['header'];
        $detail = $data['detail'];


        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'trans_code'=>'bail|required',
            'transtype'=>'bail|required'
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'trans_code.required'=>'Voucher harus diisi',
            'transtype.required'=>'Tipe jurnal harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.no_account'=>'bail|required|exists:m_account,account_no',
        ],[
            '*.no_account.required'=>'Kode barang harus diisi',
            '*.no_account.exists'=>'No.Akun [ :input ] tidak ditemukan dimaster akun',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $debit=0;
        $credit=0;

        foreach($detail as $row){
            $debit=$debit + floatval($row['debit']);
            $credit=$credit + floatval($row['credit']);
        }

        if (!($debit==$credit)){
            return response()->error('',501,'Debit & Kredit belum balance/sama');
        }

        DB::beginTransaction();
        try {
            $realdate = date_create($header['ref_date']);
		    $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            $jnl =journal1::where('uuid_rec',$header['uuid_rec'] ?? '')->first();
            if (!$jnl) {
                $jnl = new journal1();
                $jnl->uuid_rec     = Str::uuid();
                $jnl->trans_code   = $header['trans_code'];
                $jnl->trans_series = Journal1::GenerateNumber($header['trans_code'],$header['ref_date']);
                $jnl->transtype    = 0;
                $jnl->pool_code    = PagesHelp::Session()->pool_code;
            } else {
                Accounting::rollback($jnl->sysid);
            }

            $jnl->ref_date         = $header['ref_date'];
            $jnl->pool_code        = $header['pool_code'];
            $jnl->ref_date         = $header['ref_date'];
            $jnl->reference1       = $header['reference1'] ?? '';
            $jnl->reference2       = $header['reference2'] ?? '';
            $jnl->notes            = $header['notes'] ?? '';
            $jnl->debit            = $header['debit'] ?? 0;
            $jnl->credit           = $header['credit'] ?? 0;
            $jnl->fiscal_month     = $month_period;
            $jnl->fiscal_year      = $year_period;
            $jnl->update_userid    = PagesHelp::Session()->user_id;
            $jnl->posting_date     = Date('Y-m-d H:i:s');
            $jnl->update_timestamp = Date('Y-m-d H:i:s');
            $jnl->save();

            foreach($detail as $row){
                Journal2::insert([
                    'sysid'      => $jnl->sysid,
                    'line_no'    => $row['line_no'],
                    'no_account' => $row['no_account'],
                    'description'=> $row['description'] ?? '',
                    'line_memo'  => $row['line_memo'] ?? '',
                    'debit'      => $row['debit'],
                    'credit'     => $row['credit'],
                    'reference1' => $row['reference1'],
                    'reference2' => $row['reference2'],
                    'project'    => $row['project']
                ]);
            }

            $info=Accounting::Posting($jnl->sysid);

            if ($info['state']==false){
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }
            DB::commit();
            return response()->success('Success', 'Simpan data Berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }

    public function print(Request $request){
        $sysid=$request->sysid ?? -1;
        $uuid=$request->uuid ?? 'N/A';
        $header=Journal1::from('t_jurnal1 as a')
            ->selectRaw("a.sysid,a.pool_code,a.ref_date,a.reference1,a.reference2,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,
            a.notes,a.is_void,a.ref_date_void,
            b.line_no,b.no_account,b.description,b.line_memo,b.debit,b.credit,a.transtype,c.descriptions,d.user_name")
            ->leftjoin('t_jurnal2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_jurnal_type as c','a.transtype','=','c.jurnal_type')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->where(function ($query) use($sysid,$uuid) {
                $query->where('a.sysid', '=',$sysid)
                    ->orWhere('a.uuid_rec', '=', $uuid);
            })
            ->where('a.is_deleted',0)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();

        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();

            $pdf = PDF::loadView('accounting.accform',
            [
                'header'=>$header,
                 'profile'=>$profile
            ])->setPaper(array(0, 0, 612,486),'portrait');

            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function inqueryxls(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $jurnaltype=isset($request->jurnaltype) ? $request->jurnaltype : -99;
        $data= Journal1::from('t_jurnal1 as a')
        ->selectRaw("a.sysid as _index,a.sysid,a.pool_code,a.ref_date,a.reference1,a.reference2,a.trans_code,a.trans_series,
                     a.debit,a.credit,a.notes,a.is_void,a.is_verified,a.verified_date")
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_deleted',0);

        if ($jurnaltype==0){
            $data=$data->where('a.transtype',$jurnaltype);
        }

        $data=$data->orderBy('ref_date','asc')
        ->orderBy('sysid','asc')
        ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'INQUERY JURNAL');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));

        $sheet->getStyle('A5:A5')->getAlignment()->setHorizontal('center');
        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $line=$idx;
            $sheet->setCellValue('A'.$line,"POOL");
            $sheet->setCellValue('B'.$line, ": ".$row['pool_code']);
            $sheet->setCellValue('A'.strval($line+1),"VOUCHER");
            $sheet->setCellValue('B'.strval($line+1), ": ".$row['trans_code'].'-'.$row['trans_series']);
            $sheet->setCellValue('A'.strval($line+2),"Tanggal");
            $sheet->setCellValue('B'.strval($line+2), $row['ref_date']);

            $sheet->setCellValue('E'.$line,"Referensi 1");
            $sheet->setCellValue('F'.$line, ": ".$row['reference1']);
            $sheet->setCellValue('E'.strval($line+1),"Referensi 2");
            $sheet->setCellValue('F'.strval($line+1), ": ".$row['reference1']);
            $sheet->setCellValue('E'.strval($line+2),"Void");
            $sheet->setCellValue('F'.strval($line+2), ": ".$row['is_void']);
            $idx=$line+3;
            $awal=$idx;
            $sheet->setCellValue('A'.$idx,'No');
            $sheet->setCellValue('B'.$idx,'No.Akun');
            $sheet->setCellValue('C'.$idx,'Nama Akun');
            $sheet->setCellValue('D'.$idx,'Keterangan');
            $sheet->setCellValue('E'.$idx,'Proyek');
            $sheet->setCellValue('F'.$idx,'Debit');
            $sheet->setCellValue('G'.$idx,'Kredit');
            $detail=Journal2::where('sysid',$row['_index'])->get();
            foreach($detail as $dtl){
                $idx=$idx+1;
                $sheet->setCellValue('A'.$idx,$dtl->line_no);
                $sheet->setCellValue('B'.$idx,$dtl->no_account);
                $sheet->setCellValue('C'.$idx,$dtl->description);
                $sheet->setCellValue('D'.$idx,$dtl->line_memo);
                $sheet->setCellValue('E'.$idx,$dtl->project);
                $sheet->setCellValue('F'.$idx,$dtl->debit);
                $sheet->setCellValue('G'.$idx,$dtl->credit);
            }
            $sheet->getStyle('F'.strval($awal+1).':G'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
            $akhir=$idx;
            $styleArray = [
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ],
                        ],
                    ];
            $sheet->getStyle('A'.$awal.':G'.$akhir)->applyFromArray($styleArray);
            $sheet->getStyle('C'.$awal.':D'.$akhir)->getAlignment()->setWrapText(true);
            $sheet->getStyle('A'.$awal.':G'.$akhir)->getAlignment()->setVertical('top');
            $styleArray = [
                        'fill' => [
                                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                'color' => [
                                    'argb' => 'FFA0A0A0',
                                ]
                            ],
                    ];
            $sheet->getStyle('A'.$awal.':G'.$awal)->applyFromArray($styleArray);
            $sheet->getStyle('B'.$awal.':E'.$akhir)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            $sheet->getStyle('B'.strval($awal-1).':B'.strval($awal-1))->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            $idx=$idx + 1;
        }
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(40);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="inquery_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
