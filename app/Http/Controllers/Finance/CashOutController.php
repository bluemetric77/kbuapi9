<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\Cash1;
use App\Models\Finance\Cash2;
use App\Models\Master\Account;
use App\Models\Ops\Operation;
use App\Models\Master\Bank;
use App\Models\Ops\OperationUnpaid;
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

class CashOutController extends Controller
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
        $model=isset($request->model) ? $request->model :'GENERAL';
         $data= Cash1::from('t_cash_bank1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.pool_code,a.ref_date,a.reference1,a.reference2,CONCAT(a.trans_code,'-',a.trans_series) as voucher,
        a.cash_id,a.no_account,a.amount,a.descriptions,a.is_void,a.void_date,b.account_name,link_document,sysid_jurnal")
        ->leftJoin('m_account as b','a.no_account','=','b.account_no')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.pool_code', '=', $pool_code)
        ->where('a.enum_inout', '=', 'OUT')
        ->where('a.model', '=', $model);
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
                $respon['message']='Void Pengeluaran kas/bank berhasil';
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
            $data['detail']=Cash2::selectRaw("sysid,line_no,no_account,description,
                line_memo,amount,reference,IFNULL(driver_id,'') as driver_id")
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
            $header['enum_inout'] ='OUT';

            $sysid=$header['sysid'];
            unset($header['sysid']);
            if ($opr == 'updated') {
                $document=$header['trans_code'].'-'.$header['trans_series'];
                OperationUnpaid::where('doc_number',$document)->delete();
                Cash1::where($where)->update($header);
                Cash2::where($where)->delete();
            } else if ($opr = 'inserted') {
                $header['doc_number']=Cash1::DocNumber($header['pool_code'],$header['ref_date']);
                $header['sysid_jurnal'] = -1;
                if ($is_recorded=='1'){
                    $header['trans_series'] = Cash1::GenerateNumber($header['trans_code'],$header['ref_date']);
                }
                $sysid = Cash1::insertGetId($header);
            }
            foreach($detail as $row){
                $dtl=(array)$row;
                $dtl['sysid']=$sysid;
                $dtl['is_accident']=0;
                if ($header['model']=='GENERAL'){
                    if ($dtl['driver_id']==''){
                        $dtl['is_accident']=0;
                    } else {
                        $dtl['is_accident']=1;
                    }
                }
                Cash2::insert($dtl);
            }
            if ($header['model']=='GENERAL'){
                DB::insert("INSERT INTO t_ar_document(sysid,doc_number,ops_number,conductor_id,ref_date,amount,update_userid,update_timestamp,descriptions)
                    SELECT a.sysid,CONCAT(a.trans_code,'-',a.trans_series),'-',b.driver_id,a.ref_date,b.amount,a.update_userid,a.update_timestamp,CONCAT('LAKA : ',IFNULL(b.line_memo,''))
                    FROM t_cash_bank1 a
                    LEFT JOIN t_cash_bank2 b ON a.sysid=b.sysid
                    WHERE a.sysid=? AND IFNULL(b.driver_id,'')<>'' AND b.is_accident=1",[$sysid]);
                DB::update("UPDATE m_personal a
                    INNER JOIN
                    (SELECT conductor_id,SUM(amount-paid) AS account FROM t_ar_document
                    WHERE is_paid=0 AND is_void=0
                    GROUP BY conductor_id) b ON a.employee_id=b.conductor_id
                    SET a.account=b.account");
            } else if ($header['model']=='SPJ'){
                DB::update("UPDATE t_operation SET internal_cost=?,external_cost=standar_cost-? WHERE doc_number=?",
                [$header['amount'],$header['amount'],$header['link_document']]);
            }
            if ($is_recorded=='1') {
                $info=$this->build_jurnal($sysid,$request);
            } else {
                $info['state']=true;
            }
            if ($info['state']==true){
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']='Simpan data berhasil';
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
        /* Jurnal untuk Pengeluaran Kas
           Biaya
             Kas/Bank
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
                  'transtype'=>'2',
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
                    'line_memo'=>$row->line_memo,
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
                'line_memo'=>$data->descriptions,
                'reference1'=>$data->reference1,
                'reference2'=>$data->reference1,
                'debit'=>0,
                'credit'=>$cash,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
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
           Bank
             Biaya-Biaya
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
                  'transtype'=>'2',
                  'notes'=>$data->descriptions
                ]);
            }
            $lines=Cash2::where('sysid',$sysid)->get();
            $line=1;
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
                    'debit'=>0,
                    'credit'=>$row->amount,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }
            /* Kas Kecil */
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$data->no_account,
                'line_memo'=>'VOID '.$data->descriptions,
                'reference1'=>$data->reference1,
                'reference2'=>$data->reference1,
                'debit'=>$cash,
                'credit'=>0,
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
                        a.no_account as cash_account,IFNULL(f.personal_name,'') as driver_name,
                        b.line_no,b.no_account ,b.description,b.line_memo,b.amount,b.reference,c.bank_account,c.account_number,d.user_name,a.update_timestamp,a.descriptions,e.account_name")
            ->leftjoin('t_cash_bank2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.cash_id','=','c.sysid')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->leftjoin('m_account as e','a.no_account','=','e.account_no')
            ->leftjoin('m_personal as f','b.driver_id','=','f.employee_id')
            ->where('a.sysid',$sysid)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('finance.cashbankout',['header'=>$header,
                 'profile'=>$profile])->setPaper(array(0, 0, 612,486),'portrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function open(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = PagesHelp::PoolCode($request);
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.time_boarding,a.pool_code,a.vehicle_no,a.police_no,a.driver_id,c.personal_name AS driver,
            a.helper_id,d.personal_name AS helper,a.conductor_id,e.personal_name AS conductor,
            a.route_id,b.route_name,a.route_default,a.is_closed,a.is_ops_closed,a.standar_cost")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.helper_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.conductor_id','=','e.employee_id')
        ->where('a.pool_code',$pool_code)
        ->where('a.is_closed',0)
        ->where('a.is_cancel',0)
        ->where('a.model','POINT-BIAYA 1')
        ->where('a.internal_cost','0');
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('b.route_name', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter);
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
    public function variablecost(Request $request)
    {
        $sysid = isset($request->id) ? $request->id : '-1';
        $data = Operation::from('t_operation as a')
        ->selectRaw("b.line_no,b.cost_id,b.cost_name,b.cost,c.no_account,d.account_name")
        ->join("m_vehicle_routepoint_cost as b",'a.cost_id','=','b.sysid')
        ->join("m_variable_cost as c",'b.cost_id','=','c.line_no')
        ->leftjoin("m_account as d",'c.no_account','=','d.account_no')
        ->where('a.sysid',$sysid)
        ->get();
        return response()->success('Success', $data);
    }
    public function printspjcost(Request $request){
        $sysid=$request->sysid;
        $header=Cash1::from('t_cash_bank1 as a')
            ->selectRaw(" a.sysid,a.ref_date,CONCAT(a.trans_code,'-',a.trans_series) AS voucher, a.no_account AS kas,a.amount as total,a.is_void,a.void_date,a.pool_code,a.reference1,
                        a.no_account as cash_account,IFNULL(f.personal_name,'') as driver_name,
                        b.line_no,b.no_account ,b.description,b.line_memo,b.amount,b.reference,c.bank_account,c.account_number,d.user_name,a.update_timestamp,a.descriptions,e.account_name,
                        g.vehicle_no,g.police_no,a.doc_number,a.link_document")
            ->leftjoin('t_cash_bank2 as b','a.sysid','=','b.sysid')
            ->leftjoin('m_cash_operation as c','a.cash_id','=','c.sysid')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->leftjoin('m_account as e','a.no_account','=','e.account_no')
            ->leftjoin('m_personal as f','b.driver_id','=','f.employee_id')
            ->leftjoin('t_operation as g','a.link_document','=','g.doc_number')
            ->where('a.sysid',$sysid)
            ->orderby('a.sysid','asc')
            ->orderby('b.line_no','asc')
            ->get();
        if (!$header->isEmpty()) {
            $header[0]->ref_date=date_format(date_create($header[0]->ref_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('finance.spjcost',['header'=>$header,
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
        $paid_id = isset($request->paid_id)?$request->paid_id : -1;
        $state = isset($request->state)?$request->state : -1;
        $data= Cash1::from('t_cash_bank1 as a')
        ->selectRaw("(a.sysid*1000000)+b.line_no as _index,a.doc_number,CONCAT(a.trans_code,'-',a.trans_series) AS voucher, a.ref_date,a.cash_id,c.descriptions AS bank_name,
                b.no_account,b.description,b.line_memo,b.amount,b.reference,a.enum_inout,a.update_userid,a.update_timestamp,a.pool_code")
        ->join('t_cash_bank2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_cash_operation as c','c.sysid','=','a.cash_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_void',0);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($paid_id==99999)){
            $data=$data->where('a.cash_id',$paid_id);
        }

        if ($state==1){
            $data=$data->where('a.enum_inout','OUT');
        } elseif ($state==2){
            $data=$data->where('a.enum_inout','IN');
        }

        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('b.line_memo', 'like', $filter)
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
        $state = isset($request->state)?$request->state : -1;
        $data= Cash1::from('t_cash_bank1 as a')
        ->selectRaw("(a.sysid*1000000)+b.line_no as _index,a.doc_number,CONCAT(a.trans_code,'-',a.trans_series) AS voucher, a.ref_date,a.cash_id,c.descriptions AS bank_name,
                b.no_account,b.description,b.line_memo,b.amount,b.reference,a.enum_inout,a.update_userid,a.update_timestamp,a.pool_code")
        ->join('t_cash_bank2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_cash_operation as c','c.sysid','=','a.cash_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_void',0);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($paid_id==99999)){
            $data=$data->where('a.cash_id',$paid_id);
        }

        if ($state==1){
            $data=$data->where('a.enum_inout','OUT');
        } elseif ($state==2){
            $data=$data->where('a.enum_inout','IN');
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PENERIMAAN/PENGELUARAN KAS/BANK');
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
            $sheet->setCellValue('B4', ': SEMUA BANK');
        } else {
            $sheet->setCellValue('A4', 'BANK');
            $sheet->setCellValue('B4', ': ');
        }
        $sheet->setCellValue('A5', 'No.Bukti');
        $sheet->setCellValue('B5', 'Voucher');
        $sheet->setCellValue('C5', 'Bank');
        $sheet->setCellValue('D5', 'No.Akun');
        $sheet->setCellValue('E5', 'Nama Akun');
        $sheet->setCellValue('F5', 'Keterangan');
        $sheet->setCellValue('G5', 'Jumlah');
        $sheet->setCellValue('H5', 'IN/OUT');
        $sheet->setCellValue('I5', 'Pool');
        $sheet->setCellValue('J5', 'User Input');
        $sheet->setCellValue('K5', 'Tgl.Input');
        $sheet->getStyle('A5:K5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->bank_name);
            $sheet->setCellValue('D'.$idx, $row->no_account);
            $sheet->setCellValue('E'.$idx, $row->description);
            $sheet->setCellValue('F'.$idx, $row->line_memo);
            $sheet->setCellValue('G'.$idx, $row->amount);
            $sheet->setCellValue('H'.$idx, $row->enum_inout);
            $sheet->setCellValue('I'.$idx, $row->pool_code);
            $sheet->setCellValue('J'.$idx, $row->update_userid);
            $sheet->setCellValue('K'.$idx, $row->update_timestamp);
       }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('K6:K'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('G'.$idx, "=SUM(G6:G$last)");
        $sheet->getStyle('G6:G'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('D6:D'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('A1:K5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'K'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:K'.$idx)->applyFromArray($styleArray);
        foreach(range('C','K') as $columnID) {
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
        $xls="laporan_kas_bank_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
