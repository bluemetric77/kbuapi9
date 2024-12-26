<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\Cash1;
use App\Models\Finance\Cash2;
use App\Models\Master\Account;
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
use PDF;

class CashInController extends Controller
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
        $data= Cash1::from('t_cash_bank1 as a')
        ->selectRaw("a.sysid,a.pool_code,a.ref_date,a.reference1,a.reference2,CONCAT(a.trans_code,'-',a.trans_series) as voucher,
        a.cash_id,a.no_account,a.amount,a.descriptions,a.is_void,a.void_date,b.account_name,sysid_jurnal")
        ->leftJoin('m_account as b','a.no_account','=','b.account_no')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.pool_code', '=', $pool_code)
        ->where('a.enum_inout', '=', 'IN');
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
        $sysid = isset($request->sysid) ? $request->sysid : -1;
        $data=Cash1::selectRaw("sysid,is_void,doc_number,void_date,trans_code,is_jurnal,sysid_jurnal")->where('sysid',$sysid)->first();
        if (!($data)){
            return response()->error('', 501, "Data tidak ditemukan");
        } elseif ($data->is_void=="1"){
            return response()->error('', 501, "Dokumen pengeluaran kas/kas sudah divoid");
        }
        DB::beginTransaction();
        try {
            Cash1::where('sysid',$sysid)
            ->update([
                'is_void'=>'1',
                'void_date'=>Date('Y-m-d'),
                'void_by'=> PagesHelp::UserID($request)
            ]);
            if ($data->is_jurnal==1) {
                $series= Cash1::GenerateNumber($data->trans_code,$data->ref_date);
                Cash1::where('sysid',$sysid)
                ->update([
                    'sysid_void'=>'-1',
                    'trans_series_void'=>$series
                    ]);
                $info=$this->void_jurnal($sysid,$request);
            } else {
                $info['state']=true;
            }
            if ($info['state']==true){
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']='Void penerimaan kas/bank berhasil';
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

    public function get(Request $request)
    {
        $id = $request->sysid;
        $header = Cash1::where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $sysid=$header->sysid;
            $data['detail']=Cash2::selectRaw('sysid,line_no,no_account,description,
                line_memo,amount,reference')
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
        $header['model']='GENERAL';

        $is_recorded = '1';
        $bank=Bank::where('sysid',$header['cash_id'])->first();
        if ($bank){
            $header['no_account']=$bank->no_account;
            $is_recorded=$bank->is_recorded;
        }
        if (($is_recorded=='0') && (!($header['model']=='GENERAL'))){
            return response()->error('',501,'Kas/Bank yang tidak terjurnal tidak bisa dipakai untuk proses ini');
        }

        if ($opr == 'updated') {
            $data=Cash1::selectRaw("sysid,is_void,doc_number,void_date,trans_code,is_jurnal,sysid_jurnal")->where('sysid',$where['sysid'])->first();
            if (!($data)){
                return response()->error('', 501, "Data tidak ditemukan");
            } elseif ($data->is_void=="1"){
                return response()->error('', 501, "Dokumen pengeluaran kas/kas sudah divoid");
            }
        }

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'cash_id'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Voucher harus diisi',
            'cash_id.required'=>'Kas/Bank harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        if ($is_recorded){
            $validator=Validator::make($header,[
                'trans_code'=>'bail|required',
            ],[
                'trans_code.required'=>'Tanggal harus diisi',
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
        }
        $header['descriptions']='';
        foreach($detail as $row){
            $header['descriptions'] = trim($header['descriptions']).$row['line_memo'].', ';
        }
        $sysid = $header['sysid'];
        DB::beginTransaction();
        try {
            $realdate = date_create($header['ref_date']);
		    $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            $header['update_userid'] = PagesHelp::UserID($request);
            $header['posting_date'] =Date('Y-m-d');
            $header['update_timestamp'] =Date('Y-m-d H:i:s');
            $header['enum_inout'] ='IN';

            $sysid=$header['sysid'];
            unset($header['sysid']);
            if ($opr == 'updated') {
                Cash1::where($where)->update($header);
                Cash2::where($where)
                    ->delete();
            } else if ($opr = 'inserted') {
                if ($is_recorded=='1'){
                    $header['trans_series'] = Cash1::GenerateNumber($header['trans_code'],$header['ref_date']);
                }
                $header['sysid_jurnal'] = -1;
                $sysid = Cash1::insertGetId($header);
            }
            foreach($detail as $row){
                $dtl=(array)$row;
                $dtl['sysid']=$sysid;
                Cash2::insert($dtl);
            }
            if ($is_recorded=='1') {
                $info=$this->build_jurnal($sysid,$request);
            } else {
                $info['state']=true;
            }

            if ($info['state']==true){
                DB::commit();
                return response()->success('Success', 'Simpan data Berhasil');
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
        $data=Cash1::from('t_cash_bank1 as a')
        ->select('a.ref_date','a.trans_code','a.trans_series','a.ref_date','a.sysid_jurnal','a.pool_code','a.amount','a.descriptions',
        'b.no_account','a.reference1','a.reference2')
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

            $lines=Cash2::where('sysid',$sysid)->get();
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
                    'line_memo'=>$row->line_memo,
                    'reference1'=>$reference,
                    'reference2'=>'',
                    'debit'=>0,
                    'credit'=>$row->amount,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }

            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                Cash1::where('sysid',$sysid)
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
        $data=Cash1::from('t_cash_bank1 as a')
        ->select('a.ref_date','a.trans_code','a.trans_series','a.ref_date','a.sysid_jurnal','a.pool_code','a.amount','a.descriptions',
        'b.no_account','a.reference1','a.reference2','a.sysid_void','a.trans_series_void')
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
                  'trans_series'=>$data->trans_series_void,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'3',
                  'notes'=>$data->descriptions
                ]);
            }
            $lines=Cash2::where('sysid',$sysid)->get();
            $line=0;
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
                    'line_memo'=>'VOID '.$row->line_memo,
                    'reference1'=>$reference,
                    'reference2'=>'',
                    'debit'=>$row->amount,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }
            /* Kas Kecil */
            $line++;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$data->no_account,
                'line_memo'=>'VOID '.$data->descriptions,
                'reference1'=>$data->reference1,
                'reference2'=>$data->reference1,
                'debit'=>0,
                'credit'=>$cash,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);


            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                Journal1::where('sysid',$data->sysid_jurnal)
                ->update([
                    'is_void'=>'1',
                    'ref_date_void'=>Date('Y-m-d'),
                    'sysid_void'=>$sysid_jurnal
                ]);
                Cash1::where('sysid',$sysid)
                ->update([
                    'sysid_void'=>$sysid_jurnal
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
        $header=Cash1::from('t_cash_bank1 as a')
            ->selectRaw(" a.sysid,a.ref_date,CONCAT(a.trans_code,'-',a.trans_series) AS voucher, a.no_account AS kas,a.amount as total,a.is_void,a.void_date,a.pool_code,a.reference1,
                        b.line_no,b.no_account ,b.description,b.line_memo,b.amount,b.reference,c.bank_account,c.account_number,d.user_name,a.update_timestamp,a.descriptions,e.account_name")
            ->leftjoin('t_cash_bank2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.cash_id','=','c.sysid')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->leftjoin('m_account as e','a.no_account','=','e.account_no')
            ->where('a.sysid',$sysid)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('finance.cashbankin',['header'=>$header,
                 'profile'=>$profile])->setPaper(array(0, 0, 612,486),'portrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
}
