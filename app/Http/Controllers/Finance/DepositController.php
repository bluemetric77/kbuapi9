<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\Deposit1;
use App\Models\Finance\Deposit2;
use App\Models\Ops\OperationUnpaid;
use App\Models\Master\Account;
use App\Models\Master\Driver;
use App\Models\Master\Bank;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
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

class DepositController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $data= Deposit1::from('t_deposit1 as a')
        ->selectRaw("a.sysid,doc_number,a.ref_date,a.bank_date,a.reference,CONCAT(a.trans_code,'-',a.trans_series) as voucher,
        a.paid_id,a.employee_id,a.personal_name,a.amount,a.is_approved,b.descriptions,a.amount,a.paid")
        ->leftJoin('m_cash_operation as b','a.paid_id','=','b.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.trans_code', 'like', $filter)
                    ->orwhere('a.trans_series', 'like', $filter)
                    ->orwhere('a.reference', 'like', $filter)
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

    public function destroy(Request $request)
    {
        $id = $request->sysid;
    }


    public function get(Request $request)
    {
        $id = $request->sysid;
        $header = Deposit1::where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $sysid=$header->sysid;
            $data['detail']=Deposit2::where('sysid',$sysid)->get();
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
        $header['pool_code']=PagesHelp::PoolCode($request);
        $is_recorded = '1';
        $driver= Driver::selectRaw("personal_type")->where('employee_id',$header['employee_id'])->first();
        if ($driver) {
            if ($driver->personal_type=='Pengemudi') {
                $header['flag']='D';
            } else {
                $header['flag']='C';
            }
        }
        $paid=DB::table('o_config')->where('sysid',1)->first();
        if ($paid) {
            if ($header['flag']=='D'){
                $header['paid_id']=$paid->bank_accident;
            } else {
                $header['paid_id']=$paid->bank_deposit;
            }
        }

        $bank=Bank::selectRaw("no_account,voucher_in")->where('sysid',$header['paid_id'])->first();
        if ($bank){
            $header['no_account']=$bank->no_account;
            $header['trans_code']=$bank->voucher_in;
            $is_recorded=$bank->is_recorded;
        }
        $validator=Validator::make($header,[
            'pool_code'=>'bail|required',
            'ref_date'=>'bail|required',
            'trans_code'=>'bail|required',
            'paid_id'=>'bail|required',
            'employee_id'=>'bail|required',
        ],[
            'pool_code.required'=>'Pool harus diisi',
            'ref_date.required'=>'Tanggal harus diisi',
            'trans_code.required'=>'Voucher harus diisi',
            'paid_id.required'=>'Kas/Bank harus diisi',
            'employee_id.required'=>'Driver/Kondektur harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.no_account'=>'bail|required|exists:m_account,account_no',
        ],[
            '*.no_account.required'=>'Kode Akun harus diisi',
            '*.no_account.exists'=>'No.Akun [ :input ] tidak ditemukan dimaster akun',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        foreach($detail as $row){
            if ((floatval($row['paid']) <=0) || (floatval($row['paid'])>floatval($row['amount']))){
                return response()->error('',501,"Baris ke-".$row['line_no'].", Jumlah terima tidak boleh NOL atau lebih besar dari yang seharusnya");
            }
        }
        $bank=Bank::select('no_account')->where('sysid',$header['paid_id'])->first();
        if ($bank){
            $header['no_account']=$bank->no_account;
        }
        $partner=DB::table('m_personal')->select('personal_name')->where('employee_id',$header['employee_id'])->first();
        if ($partner){
            $header['personal_name']=$partner->personal_name;
        }
        $sysid = $header['sysid'];
        if ($is_recorded!='1') {
            $header['trans_code']='';
            $header['trans_series']='';
        }
        DB::beginTransaction();
        try {
            $realdate = date_create($header['ref_date']);
		    $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            $header['update_userid'] = PagesHelp::UserID($request);
            $header['update_timestamp'] =Date('Y-m-d H:i:s');

            $sysid=$header['sysid'];
            unset($header['sysid']);
            if ($opr == 'updated') {
                DB::update("UPDATE t_ar_document a
                  INNER JOIN t_deposit2 b ON a.sysid=b.ref_sysid
                  SET a.paid=a.paid - b.paid
                  WHERE a.conductor_id=? AND b.sysid=?",[$header['employee_id'],$sysid]);
                Deposit1::where($where)->update($header);
                Deposit2::where($where)->delete();
            } else if ($opr = 'inserted') {
                $header['doc_number']   = Deposit1::GenerateNumber($header['ref_date']);
                if ($is_recorded=='1'){
                    $header['trans_series'] = Deposit1::VoucherNumber($header['trans_code'],$header['ref_date']);
                }
                $header['sysid_jurnal'] = -1;
                $sysid = Deposit1::insertGetId($header);
            }
            foreach($detail as $row){
                $dtl=(array)$row;
                $dtl['sysid']=$sysid;
                Deposit2::insert($dtl);
            }
            DB::update("UPDATE t_ar_document a
                  INNER JOIN t_deposit2 b ON a.sysid=b.ref_sysid
                  SET a.paid=a.paid + b.paid
                  WHERE a.conductor_id=? AND b.sysid=?",[$header['employee_id'],$sysid]);
            $validate=DB::table('t_ar_document')
                ->where('conductor_id',$header['employee_id'])
                ->whereRaw("IFNULL(amount,0)<IFNULL(paid,0)")
                ->first();
            if ($validate) {
                DB::rollback();
                return response()->error('', 501,"Pembayaran melebihi hutang ".$validate->doc_number.
                "Saldo : ".number_format($validate->amount,2,',','.').", Total Penerimaan : ".number_format($validate->paid,2,',','.'));
            }
            DB::update("UPDATE m_personal a
                INNER JOIN
                (SELECT conductor_id,SUM(amount-paid) AS account FROM t_ar_document
                WHERE is_paid=0 AND is_void=0
                GROUP BY conductor_id) b ON a.employee_id=b.conductor_id
                SET a.account=b.account");
            if ($is_recorded=='1') {
                $info=$this->build_jurnal($sysid,$request);
            } else {
                $info['state']=true;
            }

            if ($info['state']==true){
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']="Simpan data berhasil";
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
        /* Kas/Bank Intransit
              Pendapatan Lain-2        100
              Pendapatan Lain-2        100

         */
        $ret['state']=true;
        $ret['message']='';
        $data=Deposit1::from('t_deposit1 as a')
        ->select('a.ref_date','a.trans_code','a.trans_series','a.ref_date','a.sysid_jurnal','a.amount',
                 'a.paid','a.no_account','a.reference','b.personal_name','a.pool_code')
        ->join('m_personal as b','a.employee_id','=','b.employee_id')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $paid=floatval($data->paid);

            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->reference,
                  'reference2'=>'-',
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'11',
                  'notes'=>'Cicilan Laka/Kurang Setor '.$data->personal_name
              ]);
            } else {
                $sysid_jurnal=$data->sysid_jurnal;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->reference,
                  'reference2'=>'-',
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'11',
                  'notes'=>'Cicilan Laka/Kurang Setor '.$data->personal_name
                ]);
            }
            /* Kas Kecil */
            $project=PagesHelp::Project($data->pool_code);
            $detail=Deposit2::where('sysid',$sysid)->get();
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$data->no_account,
                'line_memo'=>'Pembayaran hutang '.$data->partner_name,
                'reference1'=>$data->reference,
                'reference2'=>'',
                'debit'=>$data->paid,
                'credit'=>0,
                'project'=>'00'
            ]);
            $line=1;
            foreach($detail as $row) {
                $line=$line+1;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->no_account,
                    'line_memo'=>'KS/Laka '.$row->doc_number.'-'.$row->descriptions,
                    'reference1'=>'-',
                    'reference2'=>'-',
                    'debit'=>0,
                    'credit'=>$row->paid,
                    'project'=>$project
                ]);
            }
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                Deposit1::where('sysid',$sysid)
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
    public function getOpen(Request $request)
    {
        $employee_id = isset($request->employee_id) ? $request->employee_id :'-';
        $data= OperationUnpaid::selectRaw("sysid,doc_number,ops_number,descriptions,ref_date,amount,paid,(amount-paid) as unpaid")
        ->where('conductor_id', '=', $employee_id)
        ->whereRaw('IFNULL(amount,0)-IFNULL(paid,0)>0')
        ->get();
        return response()->success('Success', $data);
    }
    public function print(Request $request){
        $sysid=$request->sysid;
        $header=Deposit1::from('t_deposit1 as a')
            ->selectRaw(" a.sysid,a.doc_number,a.ref_date,CONCAT(a.trans_code,'-',a.trans_series) AS voucher, a.no_account AS cash_account,a.paid as total,a.pool_code,a.reference,
                        b.line_no,b.no_account ,b.descriptions,a.amount,a.paid,d.user_name,a.update_timestamp,b.doc_number as ref_number,
                        CONCAT(c.descriptions,' - ',IFNULL(c.account_number,'')) as payment_name,a.update_timestamp")
            ->leftjoin('t_deposit2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.paid_id','=','c.sysid')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->leftjoin('m_account as e','a.no_account','=','e.account_no')
            ->where('a.sysid',$sysid)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $header[0]->update_timestamp=date_format(date_create($header[0]->update_timestamp),'d-m-Y H:i:s');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('finance.deposit',['header'=>$header,
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
        $pool_code = isset($request->pool_code) ? $request->pool_code : '';
        $data= Deposit1::from('t_deposit1 as a')
        ->selectRaw(" (a.sysid*1000000)+b.line_no AS _index,a.pool_code,a.doc_number,a.ref_date,a.paid_id,c.descriptions AS bank_name,a.no_account,
            CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.update_userid,
            a.update_timestamp,b.descriptions,b.ref_date AS doc_date,b.amount,b.paid as paid_ks,b.no_account AS acc_detail,
            d.doc_number as doc_cashier,d.doc_operation,e.ref_date as ops_date,e.passenger as jpoint,ABS(e.ks) as ks,e.revenue,
            e.others,e.target,e.target2,e.internal_cost,e.dispensation,e.paid,e.vehicle_no,e.police_no,
            e.driver_id,f.personal_name as driver_name,
            e.conductor_id,g.personal_name as conductor_name,
            e.helper_id,h.personal_name as kernet_name,
            i.route_name")
        ->leftJoin('t_deposit2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_cash_operation as c','c.sysid','=','a.paid_id')
        ->leftJoin('t_operation_cashier as d','d.sysid','=','b.ref_sysid')
        ->leftJoin('t_operation as e','e.sysid','=','d.sysid_operation')
        ->leftJoin('m_personal as f','e.driver_id','=','f.employee_id')
        ->leftJoin('m_personal as g','e.conductor_id','=','g.employee_id')
        ->leftJoin('m_personal as h','e.helper_id','=','h.employee_id')
        ->leftJoin('m_bus_route as i','e.route_id','=','i.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('b.descriptions', 'like', $filter)
                    ->orwhere('a.pool_code', 'like', $filter);
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
        $pool_code = isset($request->pool_code) ? $request->pool_code : '';
        $data= Deposit1::from('t_deposit1 as a')
        ->selectRaw(" (a.sysid*1000000)+b.line_no AS _index,a.pool_code,a.doc_number,a.ref_date,a.paid_id,c.descriptions AS bank_name,a.no_account,
            CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.update_userid,
            a.update_timestamp,b.descriptions,b.ref_date AS doc_date,b.amount,b.paid as paid_ks,b.no_account AS acc_detail,
            d.doc_number as doc_cashier,d.doc_operation,e.ref_date as ops_date,e.passenger as jpoint,ABS(e.ks) as ks,e.revenue,
            e.others,e.target,e.target2,e.internal_cost,e.dispensation,e.paid,e.vehicle_no,e.police_no,
            e.driver_id,f.personal_name as driver_name,
            e.conductor_id,g.personal_name as conductor_name,
            e.helper_id,h.personal_name as kernet_name,
            i.route_name")
        ->leftJoin('t_deposit2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_cash_operation as c','c.sysid','=','a.paid_id')
        ->leftJoin('t_operation_cashier as d','d.sysid','=','b.ref_sysid')
        ->leftJoin('t_operation as e','e.sysid','=','d.sysid_operation')
        ->leftJoin('m_personal as f','e.driver_id','=','f.employee_id')
        ->leftJoin('m_personal as g','e.conductor_id','=','g.employee_id')
        ->leftJoin('m_personal as h','e.helper_id','=','h.employee_id')
        ->leftJoin('m_bus_route as i','e.route_id','=','i.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN SETORAN LAKA/KS');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'No.Bukti');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'Bank');
        $sheet->setCellValue('D5', 'No.Akun');
        $sheet->setCellValue('E5', 'Penerimaan');
        $sheet->setCellValue('F5', 'No.SPJ');
        $sheet->setCellValue('G5', 'Tgl.SPJ');
        $sheet->setCellValue('H5', 'No.Unit');
        $sheet->setCellValue('I5', 'No.Polisi');
        $sheet->setCellValue('J5', 'Rute');
        $sheet->setCellValue('K5', 'Point');
        $sheet->setCellValue('L5', 'Pendapatan');
        $sheet->setCellValue('M5', 'Target 1');
        $sheet->setCellValue('N5', 'Target 2');
        $sheet->setCellValue('O5', 'Lain2');
        $sheet->setCellValue('P5', 'KS');
        $sheet->setCellValue('Q5', 'Biaya Ops');
        $sheet->setCellValue('R5', 'Dispensasi');
        $sheet->setCellValue('S5', 'Penerimaan');
        $sheet->setCellValue('T5', 'Pengemudi');
        $sheet->setCellValue('U5', 'Kondektur');
        $sheet->setCellValue('V5', 'Kernet');
        $sheet->setCellValue('W5', 'Jumlah');
        $sheet->setCellValue('X5', 'Bayar');
        $sheet->setCellValue('Y5', 'Pool');
        $sheet->setCellValue('Z5', 'User Input');
        $sheet->setCellValue('AA5', 'Tgl.Input');
        $sheet->getStyle('A5:AA5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->bank_name);
            $sheet->setCellValue('D'.$idx, $row->no_account);
            $sheet->setCellValue('E'.$idx, $row->doc_cashier);
            $sheet->setCellValue('F'.$idx, $row->doc_operation);
            $sheet->setCellValue('G'.$idx, $row->doc_date);
            $sheet->setCellValue('H'.$idx, $row->vehicle_no);
            $sheet->setCellValue('I'.$idx, $row->police_no);
            $sheet->setCellValue('J'.$idx, $row->route_name);
            $sheet->setCellValue('K'.$idx, $row->jpoint);
            $sheet->setCellValue('L'.$idx, $row->revenue);
            $sheet->setCellValue('M'.$idx, $row->target);
            $sheet->setCellValue('N'.$idx, $row->target2);
            $sheet->setCellValue('O'.$idx, $row->others);
            $sheet->setCellValue('P'.$idx, $row->ks);
            $sheet->setCellValue('Q'.$idx, $row->internat_cost);
            $sheet->setCellValue('R'.$idx, $row->dispensation);
            $sheet->setCellValue('S'.$idx, $row->paid);
            $sheet->setCellValue('T'.$idx, $row->driver_name);
            $sheet->setCellValue('U'.$idx, $row->conductor_name);
            $sheet->setCellValue('V'.$idx, $row->kernet_name);
            $sheet->setCellValue('W'.$idx, $row->amount);
            $sheet->setCellValue('X'.$idx, $row->paid_ks);
            $sheet->setCellValue('Y'.$idx, $row->pool_code);
            $sheet->setCellValue('Z'.$idx, $row->update_userid);
            $sheet->setCellValue('AA'.$idx, $row->update_timestamp);
       }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('G6:G'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('AA6:AA'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('J'.$idx, "TOTAL");
        $sheet->setCellValue('K'.$idx, "=SUM(K6:K$last)");
        $sheet->setCellValue('L'.$idx, "=SUM(L6:L$last)");
        $sheet->setCellValue('M'.$idx, "=SUM(M6:M$last)");
        $sheet->setCellValue('N'.$idx, "=SUM(N6:N$last)");
        $sheet->setCellValue('O'.$idx, "=SUM(O6:O$last)");
        $sheet->setCellValue('P'.$idx, "=SUM(P6:P$last)");
        $sheet->setCellValue('Q'.$idx, "=SUM(Q6:Q$last)");
        $sheet->setCellValue('R'.$idx, "=SUM(R6:R$last)");
        $sheet->setCellValue('S'.$idx, "=SUM(S6:S$last)");
        $sheet->setCellValue('W'.$idx, "=SUM(W6:R$last)");
        $sheet->setCellValue('X'.$idx, "=SUM(X6:S$last)");
        $sheet->getStyle('K6:S'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('W6:X'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A6:A'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('A1:AA5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'AA'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:AA'.$idx)->applyFromArray($styleArray);
        foreach(range('C','AA') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_setor_ks_laka_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

}
