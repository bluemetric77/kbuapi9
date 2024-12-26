<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\InPayment1;
use App\Models\Finance\InPayment2;
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

class InPaymentController extends Controller
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
        $data= InPayment1::from('t_InPayment_bank1 as a')
        ->selectRaw("a.sysid,a.pool_code,a.ref_date,a.reference1,a.reference2,a.trans_code,a.trans_series,
        a.InPayment_id,a.no_account,a.amount,a.descriptions,a.is_void,a.void_date,b.account_name,
        CONCAT(a.trans_code,'-',trans_series) as voucher,a.uuid_rec,sysid_jurnal")
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
        $id = $request->sysid;
        $data = OpsInPaymentier::where('sysid', $id)
            ->first();
        if ($data->is_canceled == 1) {
            return response()->error('', 501, 'Order ' . $data->doc_number . ' sudah dibatalkan');
        }
        DB::beginTransaction();
        try {
            $data = OpsInPaymentier::select('sysid','sysid_operation','doc_operation')->where('sysid', $id)->first();
            if (!($data == null)) {
                $sysid_opr=$data->sysid_operation;
                DB::update("UPDATE m_vehicle a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.vehicle_status='Beroperasi',last_operation=?
                     WHERE a.sysid=?",[$sysid_opr,$sysid_opr]);
                OpsInPaymentier::where('sysid',$id)->update(['is_canceled'=>'1']);
                Operation::where('sysid',$sysid_opr)->update(['is_closed'=>'1']);
                DB::commit();
                return response()->success('Success', 'Penerimaan kasir berhasil divoid');
            } else {
                DB::rollback();
                return response()->error('', 501, 'Data tidak ditemukan');
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }


    public function get(Request $request)
    {
        $id = $request->sysid;
        $header = InPayment1::where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $sysid=$header->sysid;
            $data['detail']=InPayment2::selectRaw('sysid,line_no,no_account,description,
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

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'trans_code'=>'bail|required',
            'pool_code'=>'bail|required',
            'InPayment_id'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'trans_code.required'=>'Voucher harus diisi',
            'InPayment_id.required'=>'Kas/Bank harus diisi'
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
        $bank=Bank::where('sysid',$header['InPayment_id'])->first();
        if ($bank){
            $header['no_account']=$bank->no_account;
        }
        $sysid = $header['sysid'];
        DB::beginTransaction();
        try {
            $realdate = date_create($header['ref_date']);
		    $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            $header['update_userid'] = PagesHelp::UserID($request);
            $header['posting_date'] =new \DateTime();
            $header['update_timestamp'] =new \DateTime();
            $header['enum_inout'] ='IN';

            $sysid=$header['sysid'];
            unset($header['sysid']);
            if ($opr == 'updated') {
                InPayment1::where($where)->update($header);
                InPayment2::where($where)
                    ->delete();
            } else if ($opr = 'inserted') {
                $header['trans_series'] = InPayment1::GenerateNumber($header['trans_code'],$header['ref_date']);
                $header['sysid_jurnal'] = -1;
                $sysid = InPayment1::insertGetId($header);
            }
            foreach($detail as $row){
                $dtl=(array)$row;
                $dtl['sysid']=$sysid;
                InPayment2::insert($dtl);
            }
            $info=$this->build_jurnal($sysid,$request);
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
        $data=InPayment1::from('t_InPayment_bank1 as a')
        ->select('a.ref_date','a.trans_code','a.trans_series','a.ref_date','a.sysid_jurnal','a.pool_code','a.amount','a.descriptions',
        'b.no_account','a.reference1','a.reference2')
        ->join('m_InPayment_operation as b','a.InPayment_id','=','b.sysid')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $InPayment=floatval($data->amount);

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
                'debit'=>$InPayment,
                'credit'=>0,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);

            $lines=InPayment2::where('sysid',$sysid)->get();
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
                InPayment1::where('sysid',$sysid)
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
}
