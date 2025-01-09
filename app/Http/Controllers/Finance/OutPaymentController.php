<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\OutPayment1;
use App\Models\Finance\OutPayment2;
use App\Models\Finance\CustomerAccount;
use App\Models\Master\Account;
use App\Models\Master\Bank;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Finance\PaymentSubmission1;
use App\Models\Finance\PaymentSubmission2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Accounting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Str;

class OutPaymentController extends Controller
{
    public function show(Request $request)
    {
        $filter      = $request->filter;
        $limit       = $request->limit;
        $sorting     = ($request->descending == "true") ?'desc':'asc';
        $sortBy      = $request->sortBy;
        $start_date  = $request->start_date;
        $end_date    = $request->end_date;
        $outstanding = isset($request->outstanding) ? $request->outstanding : '0';

        $data= OutPayment1::from('t_outpayment1 as a')
        ->selectRaw("a.sysid,doc_number,a.ref_date,a.bank_date,a.reference,a.trans_code,a.trans_series,
        a.paid_id,a.partner_id,a.partner_name,a.amount,a.paid,a.is_void,a.is_approved,b.descriptions,
        CONCAT(a.trans_code,'-',trans_series) as voucher,a.uuid_rec,sysid_jurnal")
        ->leftJoin('m_cash_operation as b','a.paid_id','=','b.sysid');

        if ($outstanding=='1') {
            $data=$data->where('a.is_approved', '0');
        } else {
            $data=$data->where('a.ref_date', '>=', $start_date)
            ->where('a.ref_date', '<=', $end_date);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.trans_code', 'like', $filter)
                    ->orwhere('a.trans_series', 'like', $filter)
                    ->orwhere('a.reference', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter);
            });
        }
        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);
        return response()->success('Success', $data);
    }

    public function destroy(Request $request)
    {
        $sysid = $request->sysid;
        $data = OutPayment1::where('sysid', $sysid)->first();
        if ($data->is_void == '1') {
            return response()->error('', 501, 'Order ' . $data->doc_number . ' sudah dibatalkan');
        }
        DB::beginTransaction();
        try {
            $data = OutPayment1::selectRaw("sysid,doc_number,ref_date,partner_name,sysid_jurnal")->where('sysid', $sysid)->first();
            if ($data) {
                OutPayment1::where('sysid',$sysid)
                ->update(['is_void'=>'1','void_date'=>Date('Y-m-d H:i:s'),'void_by'=>PagesHelp::UserID($request)]);
                DB::update("UPDATE t_customer_account a
                  INNER JOIN t_outpayment2 b ON a.sysid=b.ref_sysid
                  SET a.total_paid=a.total_paid - (b.paid+b.discount+b.tax),
                      a.tax=a.tax-b.tax,a.discount=a.discount-b.discount
                      WHERE a.partner_id=? AND b.sysid=?",[$data->partner_id,$sysid]);
                DB::delete("DELETE FROM t_customer_card WHERE sysid=? AND doc_source='BOP'",[$sysid]);
                $info=Accounting::void_jurnal($data->sysid_jurnal,$request);
                if ($info['state']==true){
                    DB::commit();
                    return response()->success('Success', 'Pembatalan pembayaran hutang berhasil');
                } else {
                    DB::rollback();
                    return response()->error('', 501, $info['message']);
                }
            } else {
                DB::rollback();
                return response()->error('', 501, 'Data/dokumen tidak ditemukan ');
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }


    public function get(Request $request)
    {
        $id = $request->sysid;
        $header = OutPayment1::where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $sysid=$header->sysid;
            $data['detail']=OutPayment2::where('sysid',$sysid)->get();
        }
        return response()->success('Success', $data);
    }

    public function post(Request $request)
    {
        $data = $request->json()->all();
        $opr = $data['operation'];
        $where = $data['where'];
        $header = $data['header'];
        $detail = $data['detail'];

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'trans_code'=>'bail|required',
            'paid_id'=>'bail|required',
            'partner_id'=>'bail|required',
            'sysid_submission'=>'bail|required|exists:t_payment_submission1,sysid',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'trans_code.required'=>'Voucher harus diisi',
            'paid_id.required'=>'Kas/Bank harus diisi',
            'partner_id.required'=>'Supplier harus diisi',
            'sysid_submission.required'=>'Nomor permintaann pembayaran harus diisi',
            'sysid_submission.exists'=>'Nomor permintaan pembayaran :input tidak ditemukan',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.no_account'=>'bail|required|exists:m_account,account_no',
            '*.doc_number'=>'bail|required|exists:t_customer_account,doc_number',
        ],[
            '*.no_account.required'=>'Kode Akun harus diisi',
            '*.no_account.exists'=>'No.Akun [ :input ] tidak ditemukan dimaster akun',
            'doc_number.required'=>'Nomor invoice harus diisi',
            'doc_number.exists'=>'Nomor invoice  :input tidak ditemukan pada hutang supplier',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $bank=Bank::select('no_account')->where('sysid',$header['paid_id'])->first();
        if ($bank){
            $header['no_account']=$bank->no_account;
        }
        $partner=DB::table('m_partner')->select('partner_name')->where('partner_id',$header['partner_id'])->first();
        if ($partner){
            $header['partner_name']=$partner->partner_name;
        }
        $sysid = $header['sysid'];
        DB::beginTransaction();
        try {
            $realdate = date_create($header['ref_date']);
		    $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            $header['update_userid'] = PagesHelp::UserID($request);
            $header['update_timestamp'] =new \DateTime();

            $sysid=$header['sysid'];
            unset($header['sysid']);
            if ($opr == 'updated') {
                DB::update("UPDATE t_customer_account a
                  INNER JOIN t_outpayment2 b ON a.sysid=b.ref_sysid
                  SET a.total_paid=a.total_paid - (b.paid+b.discount+b.tax),
                      a.tax=a.tax-b.tax,a.discount=a.discount-b.discount
                      WHERE a.partner_id=? AND b.sysid=?",[$header['partner_id'],$sysid]);
                DB::delete("DELETE FROM t_customer_card WHERE sysid=? AND doc_source='BOP'",[$sysid]);
                OutPayment1::where($where)->update($header);
                OutPayment2::where($where)->delete();
            } else if ($opr = 'inserted') {
                $header['doc_number']   = OutPayment1::GenerateNumber($header['ref_date']);
                $header['trans_series'] = OutPayment1::VoucherNumber($header['trans_code'],$header['ref_date']);
                $header['sysid_jurnal'] = -1;
                $sysid = OutPayment1::insertGetId($header);
            }
            foreach($detail as $row){
                $dtl=(array)$row;
                $dtl['sysid']=$sysid;
                OutPayment2::insert($dtl);
            }
            DB::update("UPDATE t_customer_account a
                INNER JOIN t_outpayment2 b ON a.sysid=b.ref_sysid
                SET a.total_paid=a.total_paid + (b.paid+b.discount+b.tax),
                    a.tax=a.tax+b.tax,a.discount=a.discount+b.discount,last_payment=CURRENT_DATE(),doc_payment=?
                    WHERE a.partner_id=? AND b.sysid=?",[$header['doc_number'],$header['partner_id'],$sysid]);
            DB::insert("INSERT INTO t_customer_card(ref_sysid,doc_source,doc_number,reference,ref_date,debit,credit)
                    SELECT sysid,'BOP',doc_number,reference,ref_date,0,paid FROM t_outpayment1 WHERE
                    sysid=?",[$sysid]);
            $validate=DB::table('t_customer_account')
                ->where('partner_id',$header['partner_id'])
                ->whereRaw("IFNULL(amount,0)<IFNULL(total_paid,0) AND IFNULL(amount,0)>0")
                ->first();
            if ($validate) {
                DB::rollback();
                return response()->error('', 501,"Pembayaran melebihi hutang ".$validate->doc_number.
                "Hutang : ".number_format($validate->amount,2,',','.').", Total pembayaran : ".number_format($validate->total_paid,2,',','.'));
            }
            $submission=PaymentSubmission1::selectRaw("doc_number")->where('sysid',isset($header['sysid_submission']) ?$header['sysid_submission'] :'-1')->first();
            if ($submission) {
              OutPayment1::where('sysid',isset($header['sysid_submission']) ?$header['sysid_submission'] :'-1')
              ->update([
                  'submission_number'=>$submission->doc_number
              ]);
              PaymentSubmission1::where('sysid',isset($header['sysid_submission']) ? $header['sysid_submission'] :'-1')
              ->update(['is_realization'=>1,
                        'sysid_payment'=>$sysid
                ]);
            }
            $info=$this->build_jurnal($sysid,$request);
            if ($info['state']==true){
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']=$sysid;
                return response()->success('Success', $respon);
            } else {
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public static function build_jurnal($sysid,$request) {
        /* Hutang2                 2000
              Pph 23                   100
              Additional Diskon        100
              Bank                    1800

         */
        $ret['state']=true;
        $ret['message']='';
        $data=OutPayment1::from('t_outpayment1 as a')
        ->select('a.ref_date','a.trans_code','a.trans_series','a.ref_date','a.sysid_jurnal','a.paid',
                 'a.no_account','a.reference','b.partner_name')
        ->join('m_partner as b','a.partner_id','=','b.partner_id')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $paid=floatval($data->paid);
            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>'000',
                  'reference1'=>$data->reference,
                  'reference2'=>'-',
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'5',
                  'notes'=>'Pembayaran hutang '.$data->partner_name
              ]);
            } else {
                $sysid_jurnal=$data->sysid_jurnal;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>'000',
                  'reference1'=>$data->reference,
                  'reference2'=>'-',
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'5',
                  'notes'=>'Pembayaran hutang '.$data->partner_name
                ]);
            }
            /* Kas Kecil */
            $detail=OutPayment2::where('sysid',$sysid)->get();
            $line=0;
            foreach($detail as $row) {
                $line=$line+1;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->no_account,
                    'line_memo'=>'Invoice '.$row->doc_number.'-'.$row->reference,
                    'reference1'=>'-',
                    'reference2'=>'-',
                    'debit'=>$row->paid,
                    'credit'=>0,
                    'project'=>'00'
                ]);
                if ($row->discount>0) {
                    $line=$line+1;
                    Journal2::insert([
                        'sysid'=>$sysid_jurnal,
                        'line_no'=>$line,
                        'no_account'=>$row->account_discount,
                        'line_memo'=>'Invoice '.$row->doc_number.'-'.$row->reference,
                        'reference1'=>'-',
                        'reference2'=>'-',
                        'debit'=>0,
                        'credit'=>$row->discount,
                        'project'=>'00'
                    ]);
                }
                if ($row->tax>0) {
                    $line=$line+1;
                    Journal2::insert([
                        'sysid'=>$sysid_jurnal,
                        'line_no'=>$line,
                        'no_account'=>$row->account_tax,
                        'line_memo'=>'Invoice '.$row->doc_number.'-'.$row->reference,
                        'reference1'=>'-',
                        'reference2'=>'-',
                        'debit'=>0,
                        'credit'=>$row->tax,
                        'project'=>'00'
                    ]);
                }
            }
            $line++;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$data->no_account,
                'line_memo'=>'Pembayaran hutang '.$data->partner_name,
                'reference1'=>$data->reference,
                'reference2'=>'',
                'debit'=>0,
                'credit'=>$data->paid,
                'project'=>'00'
            ]);

            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                OutPayment1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        } else {
            $ret['state']=false;
            $ret['message']='Data tidak ditemukan';
        }
        return $ret;
    }

    public function getAP(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $partner_id = isset($request->partner_id) ? $request->partner_id :'-';

        $data= CustomerAccount::selectRaw("sysid,ref_sysid,doc_source,doc_number,reference,ref_date,amount,total_paid as paid,amount-total_paid as unpaid,no_account")
        ->where('partner_id', '=', $partner_id)
        ->whereRaw('IFNULL(amount,0)-IFNULL(total_paid,0)>0 AND IFNULL(is_approved,0)=1 AND IFNULL(is_void,0)=0');
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('doc_number', 'like', $filter)
                    ->orwhere('reference', 'like', $filter);
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
    public function print(Request $request){
        $sysid=$request->sysid;
        $header=OutPayment1::from('t_outpayment1 as a')
            ->selectRaw("a.sysid,a.doc_number,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.ref_date,a.partner_id,a.partner_name,a.paid,
            b.line_no,b.doc_number as invoice_number,b.reference,b.total,b.paid AS payment,c.descriptions AS payment_method,
            d.user_name")
            ->leftjoin('t_outpayment2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.paid_id','=','c.sysid')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->where('a.sysid',$sysid)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('finance.outpayment',['header'=>$header,
                 'profile'=>$profile])->setPaper(array(0, 0, 612,486),'portrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $data=OutPayment1::from('t_outpayment1 as a')
            ->selectRaw("a.sysid as _index,a.doc_number,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.ref_date,
            a.partner_id,a.partner_name,a.paid,a.update_userid,a.update_timestamp,
            b.line_no,b.doc_number as invoice_number,b.reference,b.total,b.paid AS payment,c.descriptions AS payment_method")
            ->leftjoin('t_outpayment2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.paid_id','=','c.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.partner_code', 'like', $filter)
                    ->orwhere('partner_name', 'like', $filter);
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
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $data=OutPayment1::from('t_outpayment1 as a')
            ->selectRaw("a.sysid as _index,a.doc_number,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.ref_date,
            a.partner_id,a.partner_name,a.paid,a.update_userid,a.update_timestamp,
            b.line_no,b.doc_number as invoice_number,b.reference,b.total,b.paid AS payment,c.descriptions AS payment_method")
            ->leftjoin('t_outpayment2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.paid_id','=','c.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        $data=$data
        ->orderBy('a.ref_date','asc')
        ->orderBy('a.sysid','asc')
        ->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PEMBAYARAN HUTANG');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        $sheet->setCellValue('A5', 'No.Bukti');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'Kas/Bank');
        $sheet->setCellValue('D5', 'Voucher');
        $sheet->setCellValue('E5', 'Kode');
        $sheet->setCellValue('F5', 'Nama Supplier');
        $sheet->setCellValue('G5', 'No.Invoice');
        $sheet->setCellValue('H5', 'Invoice Supplier');
        $sheet->setCellValue('I5', 'Hutang');
        $sheet->setCellValue('J5', 'Bayar');
        $sheet->setCellValue('K5', 'User Input');
        $sheet->setCellValue('L5', 'Tgl.Input');
        $sheet->getStyle('A5:L5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->payment_method);
            $sheet->setCellValue('D'.$idx, $row->voucher);
            $sheet->setCellValue('E'.$idx, $row->partner_id);
            $sheet->setCellValue('F'.$idx, $row->partner_name);
            $sheet->setCellValue('G'.$idx, $row->invoice_number);
            $sheet->setCellValue('H'.$idx, $row->reference);
            $sheet->setCellValue('I'.$idx, $row->total);
            $sheet->setCellValue('J'.$idx, $row->payment);
            $sheet->setCellValue('K'.$idx, $row->update_userid);
            $sheet->setCellValue('L'.$idx, $row->update_timestamp);
       }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('L6:L'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('H'.$idx, "TOTAL");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->setCellValue('J'.$idx, "=SUM(J6:J$last)");
        $sheet->getStyle('I6:J'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:L5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'L'.$idx)->getFont()->setBold(true);
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
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_pembayaran_hutang_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
    public function submission(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $data= PaymentSubmission1::from('t_payment_submission1 as a')
        ->selectRaw("a.sysid,doc_number,a.ref_date,a.action_date,a.reference,
        a.paid_id,a.partner_id,a.partner_name,a.total,a.payment,a.is_void,a.is_approved,b.descriptions,
        a.approved_by1,a.approved_date1,a.approved_by2,a.approved_date2")
        ->leftJoin('m_cash_operation as b','a.paid_id','=','b.sysid')
        ->where('a.is_approved','1')
        ->where('a.is_realization','0   ')
        ->where('a.is_void','0');
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.reference', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter);
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
    public function submission_info(Request $request)
    {
        $id = $request->sysid;
        $header = PaymentSubmission1::selectRaw("sysid,partner_id,partner_name,paid_id")
        ->where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $sysid=$header->sysid;
            $data['detail']=PaymentSubmission2::from('t_payment_submission2 as a')
            ->selectRaw("a.ref_sysid,a.doc_number,a.reference,a.ref_date,a.total,a.paid,a.payment,b.no_account")
            ->join('t_customer_account as b','a.ref_sysid','=','b.sysid')
            ->where('a.sysid',$sysid)->get();
        }
        return response()->success('Success', $data);
    }

}
