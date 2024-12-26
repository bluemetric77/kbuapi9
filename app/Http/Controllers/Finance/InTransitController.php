<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\InTransit1;
use App\Models\Finance\InTransit2;
use App\Models\Ops\InTransit;
use App\Models\Master\Account;
use App\Models\Master\Bank;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Config\Users;
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

class InTransitController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $data= InTransit1::from('t_intransit1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.pool_code,a.ref_date,a.reference1,a.reference2,CONCAT(a.trans_code,'-',a.trans_series) as voucher,
        a.cash_id,a.no_account,a.amount,a.descriptions,b.account_name,a.is_void,a.void_date,a.void_by,a.trans_series_void")
        ->leftJoin('m_account as b','a.no_account','=','b.account_no')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.pool_code', '=', $pool_code);
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.trans_code', 'like', $filter)
                    ->orwhere('a.trans_series', 'like', $filter)
                    ->orwhere('a.reference1', 'like', $filter)
                    ->orwhere('a.reference2', 'like', $filter)
                    ->orwhere('a.descriptions', 'like', $filter);
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
        $data = InTransit1::selectRaw('doc_number,is_void,cash_id,trans_code,ref_date')->where('sysid', $id)->first();
        if ($data->is_void == 1) {
            return response()->error('', 501, 'Penerimaan intransit ' . $data->doc_number . ' sudah dibatalkan/divoid');
        }
        DB::beginTransaction();
        try {
            $series = InTransit1::GenerateVoucherSeries($data->trans_code,$data->ref_date);
            Intransit1::where('sysid',$id)
            ->update([
                'is_void'=>'1',
                'void_date'=>Date('Y-m-d'),
                'void_by'=>PagesHelp::UserID($request),
                'sysid_void'=>'-1',
                'trans_series_void'=>$series
            ]);
            $is_recorded = '1';
            $bank=Bank::where('sysid',$data['cash_id'])->first();
            if ($bank){
               $is_recorded=$bank->is_recorded;
            }
            $in=InTransit2::selectRaw('line_intransit,doc_source,doc_number,deposit')->where('sysid',$id)->get();
            foreach($in as $row){
                InTransit::where('sysid',$row->line_intransit)
                ->update([
                    'deposit'=>0,
                    'is_deposit'=>0
                ]);
            }
            if ($is_recorded){
                $info=$this->void_jurnal($id,$request);
            } else {
                $info['state']=true;
                $info['message']='';
            }
            if ($info['state']==true){
                DB::commit();
                $respon['sysid']=$id;
                $respon['message']= 'Void/Pembatalan penerimaan intransit Berhasil';
                return response()->success('Success',$respon['message']);
            } else {
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }


    public function get(Request $request)
    {
        $id = $request->sysid;
        $header = InTransit1::where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $sysid=$header->sysid;
            $data['detail']=InTransit2::selectRaw("sysid,line_intransit,line_no,doc_source,doc_number,ref_date,
                descriptions,amount,deposit,no_account")
                ->where('sysid',$sysid)
                ->get();
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
        $bank=Bank::where('sysid',$header['cash_id'])->first();
        if ($bank){
            $header['no_account']=$bank->no_account;
            $is_recorded=$bank->is_recorded;
        }
        if ($is_recorded=='1'){
            $validator=Validator::make($header,[
                'ref_date'=>'bail|required',
                'trans_code'=>'bail|required',
                'pool_code'=>'bail|required',
                'cash_id'=>'bail|required',
            ],[
                'ref_date.required'=>'Tanggal harus diisi',
                'trans_code.required'=>'Voucher harus diisi',
                'cash_id.required'=>'Kas/Bank harus diisi'
            ]);
        } else {
            $validator=Validator::make($header,[
                'ref_date'=>'bail|required',
                'pool_code'=>'bail|required',
                'cash_id'=>'bail|required',
            ],[
                'ref_date.required'=>'Tanggal harus diisi',
                'cash_id.required'=>'Kas/Bank harus diisi'
            ]);
        }

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if ($is_recorded=='1') {
            $validator=Validator::make($detail,[
                '*.no_account'=>'bail|required|exists:m_account,account_no',
            ],[
                '*.no_account.required'=>'Kode Akun harus diisi',
                '*.no_account.exists'=>'No.Akun [ :input ] tidak ditemukan dimaster akun',
            ]);
            if ($validator->fails()){
                return response()->error('',501,$validator->errors()->first());
            }
        }
        if ($opr=='updated') {
            $data=Intransit1::selectRaw('doc_number,is_void')->where($where)->first();
            if ($data->is_void=='1') {
                return response()->error('',501,'Dokumen intransit sudah divoid/dibatalkan,tidak bisa diubah');
            }
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
                $in=InTransit2::selectRaw('line_intransit,doc_source,doc_number,deposit')->where($where)->get();
                foreach($in as $row){
                    InTransit::where('sysid',$row->line_intransit)
                    ->update([
                        'deposit'=>0,
                        'is_deposit'=>0
                    ]);
                }
                InTransit1::where($where)->update($header);
                InTransit2::where($where)
                    ->delete();
            } else if ($opr = 'inserted') {
                $header['doc_number']=InTransit1::GenerateNumber($header['pool_code'],$header['ref_date']);
                if ($is_recorded=='1') {
                    $header['trans_series'] = InTransit1::GenerateVoucherSeries($header['trans_code'],$header['ref_date']);
                } else {
                    $header['trans_code'] = '';
                    $header['trans_series'] = '';
                }
                $header['sysid_jurnal'] = -1;
                $sysid = InTransit1::insertGetId($header);
            }
            foreach($detail as $row){
                $dtl=(array)$row;
                $dtl['sysid']=$sysid;
                InTransit2::insert($dtl);
                InTransit::where('sysid',$row['line_intransit'])
                    ->update([
                        'deposit'=>$row['deposit'],
                        'deposit_date'=>$header['ref_date'],
                        'is_deposit'=>'1'
                    ]);
            }
            if ($is_recorded){
                $info=$this->build_jurnal($sysid,$request);
            } else {
                $info['state']=true;
                $info['message']='';
            }
            if ($info['state']==true){
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']= 'Simpan data Berhasil';
                return response()->success('Success',$respon);
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
        /* Jurnal untuk Pengeluaran Kas
           Kas/Bank
             Pendapatan Lain
         */
        $ret['state']=true;
        $ret['message']='';
        $data=InTransit1::from('t_intransit1 as a')
        ->selectRaw('a.ref_date,a.trans_code,a.trans_series,a.ref_date,a.sysid_jurnal,a.pool_code,a.amount,a.descriptions,
                    b.no_account,a.reference1,a.reference2')
        ->join('m_cash_operation as b','a.cash_id','=','b.sysid')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $cash=floatval($data->amount);

            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->reference1,
                  'reference2'=>$data->reference2,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'2',
                  'notes'=>$data->descriptions
              ]);
            } else {
                $sysid_jurnal=$data->sysid_jurnal;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->reference1,
                  'reference2'=>$data->reference2,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'3',
                  'notes'=>$data->descriptions
                ]);
            }
            /* Kas Kecil */
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$data->no_account,
                'line_memo'=>$data->descriptions,
                'reference1'=>$data->reference1,
                'reference2'=>$data->reference1,
                'debit'=>$cash,
                'credit'=>0,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);

            $lines=InTransit2::where('sysid',$sysid)->get();
            foreach($lines as $row){
                if ($row->reference) {
                    $reference = $row->reference;
                } else {
                    $reference = '-';
                }
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->no_account,
                    'line_memo'=>$row->descriptions,
                    'reference1'=>$row->doc_number,
                    'reference2'=>'',
                    'debit'=>0,
                    'credit'=>$row->amount,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }

            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                InTransit1::where('sysid',$sysid)
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

    public static function void_jurnal($sysid,$request) {
        /* Jurnal untuk Pengeluaran Kas
           Kas/Bank
             Pendapatan Lain
         */
        $ret['state']=true;
        $ret['message']='';
        $data=InTransit1::from('t_intransit1 as a')
        ->selectRaw('a.ref_date,a.trans_code,a.trans_series,a.ref_date,a.sysid_jurnal,a.pool_code,a.amount,a.descriptions,
                    b.no_account,a.reference1,a.reference2,a.sysid_void,a.trans_series_void')
        ->join('m_cash_operation as b','a.cash_id','=','b.sysid')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $cash=floatval($data->amount);
            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_void==-1){
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->reference1,
                  'reference2'=>$data->reference2,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series_void,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'2',
                  'notes'=>$data->descriptions
              ]);
            } else {
                $sysid_jurnal=$data->sysid_void;
                $series=$data->trans_series_void;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->reference1,
                  'reference2'=>$data->reference2,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>$data->trans_code,
                  'trans_series'=>$data->trans_series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'3',
                  'notes'=>$data->descriptions
                ]);
            }
            /* Intransit */
            $line=0;
            $lines=InTransit2::where('sysid',$sysid)->get();
            foreach($lines as $row){
                if ($row->reference) {
                    $reference = $row->reference;
                } else {
                    $reference = '-';
                }
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->no_account,
                    'line_memo'=>$row->descriptions,
                    'reference1'=>$row->doc_number,
                    'reference2'=>'',
                    'debit'=>$row->amount,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }
            $line=$line+1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$data->no_account,
                'line_memo'=>$data->descriptions,
                'reference1'=>$data->reference1,
                'reference2'=>$data->reference1,
                'debit'=>0,
                'credit'=>$cash,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                InTransit1::where('sysid',$sysid)
                ->update(['sysid_void'=>$sysid_jurnal]);
                Journal1::where('sysid',$data->sysid_jurnal)
                ->update([
                    'is_void'=>'1',
                    'sysid_void'=>$sysid_jurnal,
                    'ref_date_void'=>Date('Y-m-d')
                ]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        } else {
            $ret['state']=false;
            $ret['message']='Data tidak ditemukan';
        }
        return $ret;
    }

    public function print(Request $request){
        $sysid=$request->sysid;
        $header=InTransit1::from('t_intransit1 as a')
            ->selectRaw(" a.sysid,a.doc_number,a.ref_date,CONCAT(a.trans_code,'-',a.trans_series) AS voucher, a.no_account AS kas,a.amount as total,a.pool_code,a.reference1,
                        b.line_no,b.no_account ,b.descriptions as descriptions_line,b.amount,c.bank_account,c.account_number,
                        a.update_userid,d.user_name,a.update_timestamp,a.descriptions,e.account_name,
                        b.doc_number as doc_number_line")
            ->leftjoin('t_intransit2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.cash_id','=','c.sysid')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->leftjoin('m_account as e','a.no_account','=','e.account_no')
            ->where('a.sysid',$sysid)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();
        if (!$header->isEmpty()) {
            $sign=array();
            $sign['user']='';

            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $home = storage_path().'/app/';
            $user=Users::select('sign')->where('user_id',$header[0]->update_userid)->first();
            if ($user) {
                $sign['user']=$home.$user->sign;
            }
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('finance.intransit',['header'=>$header,
                 'profile'=>$profile,'sign'=>$sign])->setPaper(array(0, 0, 612,486),'portrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function open(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $cash_id = isset($request->cash_id) ? $request->cash_id : -1;
        $date1=isset($request->date1) ? $request->date1 : Date('Y-m-d');
        $date2=isset($request->date2) ? $request->date2 : Date('Y-m-d');
        $data= InTransit::selectRaw("'0' as checkbox,sysid,doc_type,doc_number,ref_date,descriptions,amount,no_account,update_userid");
        $data=$data
           ->where('ref_date','>=',$date1)
           ->where('ref_date','<=',$date2)
           ->where('pool_code',$pool_code)
           ->where('is_deposit','=','0')
           ->where('cash_id','=',$cash_id);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('descriptions','like',$filter);
            });
        }
        if (!($sortBy=='')) {
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        } else {
            $data=$data->paginate($limit);
        }
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
        $pool_code = isset($request->pool_code) ? $request->pool_code : '';
        $paid_id = isset($request->paid_id)?$request->paid_id : -1;
        $data= InTransit1::from('t_intransit1 as a')
        ->selectRaw("(a.sysid*1000000)+b.line_no AS _index,a.doc_number,a.ref_date,c.descriptions bank_name,a.cash_id,a.no_account,
                    CONCAT(a.trans_code,'-',a.trans_series) as voucher,
                    a.pool_code,a.update_userid,a.update_timestamp,b.doc_number AS cashier_doc,b.amount,b.deposit,
                    b.ref_date AS cashier_date,e.vehicle_no,e.police_no,e.doc_number AS ops_number")
        ->leftJoin('t_intransit2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_cash_operation as c','c.sysid','=','a.cash_id')
        ->leftJoin('t_operation_cashier as d','d.doc_number','=','b.doc_number')
        ->leftJoin('t_operation as e','d.sysid_operation','=','e.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($paid_id==99999)){
            $data=$data->where('a.cash_id',$paid_id);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_operation', 'like', $filter)
                    ->orwhere('e.vehicle_no', 'like', $filter)
                    ->orwhere('e.police_no', 'like', $filter)
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
        $paid_id = isset($request->paid_id)?$request->paid_id : -1;
        $data= InTransit1::from('t_intransit1 as a')
        ->selectRaw("(a.sysid*1000000)+b.line_no AS _index,a.doc_number,a.ref_date,c.descriptions bank_name,a.cash_id,a.no_account,
                    CONCAT(a.trans_code,'-',a.trans_series) as voucher,
                    a.pool_code,a.update_userid,a.update_timestamp,b.doc_number AS cashier_doc,b.amount,b.deposit,
                    b.ref_date AS cashier_date,e.vehicle_no,e.police_no,e.doc_number AS ops_number")
        ->leftJoin('t_intransit2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_cash_operation as c','c.sysid','=','a.cash_id')
        ->leftJoin('t_operation_cashier as d','d.doc_number','=','b.doc_number')
        ->leftJoin('t_operation as e','d.sysid_operation','=','e.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($paid_id==99999)){
            $data=$data->where('a.cash_id',$paid_id);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PENERIMAAN KASIR (INTRANSIT)');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        if (!($paid_id==99999)){
            $sheet->setCellValue('A4', 'BANK');
            $sheet->setCellValue('B4', ': -');
        } else {
            $sheet->setCellValue('A4', 'BANK');
            $sheet->setCellValue('B4', ': ');
        }
        $sheet->setCellValue('A5', 'No.Bukti');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'Bank');
        $sheet->setCellValue('D5', 'No.Akun');
        $sheet->setCellValue('E5', 'Voucher');
        $sheet->setCellValue('F5', 'No.Setor');
        $sheet->setCellValue('G5', 'No.Bodi');
        $sheet->setCellValue('H5', 'No.Polisi');
        $sheet->setCellValue('I5', 'Jumlah Setor');
        $sheet->setCellValue('J5', 'Pool');
        $sheet->setCellValue('K5', 'User Input');
        $sheet->setCellValue('L5', 'Tgl.Input');
        $sheet->getStyle('A5:L5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->bank_name);
            $sheet->setCellValue('D'.$idx, $row->no_account);
            $sheet->setCellValue('E'.$idx, $row->voucher);
            $sheet->setCellValue('F'.$idx, $row->cashier_doc);
            $sheet->setCellValue('G'.$idx, $row->vehicle_no);
            $sheet->setCellValue('H'.$idx, $row->police_no);
            $sheet->setCellValue('I'.$idx, $row->deposit);
            $sheet->setCellValue('J'.$idx, $row->pool_code);
            $sheet->setCellValue('K'.$idx, $row->update_userid);
            $sheet->setCellValue('L'.$idx, $row->update_timestamp);
       }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('L6:L'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->getStyle('I6:I'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
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
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_instransit_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

}
