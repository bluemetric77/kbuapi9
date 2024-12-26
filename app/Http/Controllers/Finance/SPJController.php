<?php

namespace App\Http\Controllers\Finance;

use App\Models\Ops\Operation;
use App\Models\Ops\OperationRoute;
use App\Models\Finance\OpsCashier;
use App\Models\Ops\OperationUnpaid;
use App\Models\Ops\InTransit;
use App\Models\Finance\OtherItems;
use App\Models\Master\Vehicle;
use App\Models\Master\Driver;
use App\Models\Master\Pools;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Finance\OpsChashierOthers;
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

class SPJController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = PagesHelp::PoolCode($request);
        $data= OpsCashier::from('t_operation_cashier as a')
        ->selectRaw("a.sysid,a.doc_number,a.pool_code,a.paid_type,a.sysid_operation,a.doc_operation,a.ref_date,a.target,a.target2,
            a.revenue,a.others,a.total,a.paid,a.dispensation,a.ks,a.unpaid,
            b.ref_date AS opr_date,b.vehicle_no,b.police_no,b.route_id,c.route_name,d.personal_name,a.is_canceled")
        ->leftJoin('t_operation as b','a.sysid_operation','=','b.sysid')
        ->leftJoin('m_bus_route as c','b.route_id','=','c.sysid')
        ->leftJoin('m_personal as d','b.driver_id','=','d.employee_id')
        ->where('a.pool_code',$pool_code)
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_operation', 'like', $filter)
                    ->orwhere('b.vehicle_no', 'like', $filter)
                    ->orwhere('b.police_no', 'like', $filter);
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
        $data = OpsCashier::selectRaw("doc_number,is_canceled")->where('sysid', $id)->first();
        if ($data->is_canceled == 1) {
            return response()->error('', 501, 'Order ' . $data->doc_number . ' sudah dibatalkan');
        }
        if (InTransit::where('doc_type','SL')
            ->where('doc_number',$data->doc_number)
            ->where('is_deposit',1)->exists()) {
            return response()->error('', 501, 'Penyetoran intransit sudah diproses, data tidak bisa diubah');
        }
        DB::beginTransaction();
        try {
            $data = OpsCashier::select('sysid','doc_number','sysid_operation','doc_operation')->where('sysid', $id)->first();
            if ($data) {
                $sysid_opr=$data->sysid_operation;
                DB::update("UPDATE m_vehicle a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.vehicle_status='Beroperasi',last_operation=?
                     WHERE a.sysid=?",[$sysid_opr,$sysid_opr]);
                OpsCashier::where('sysid',$id)->update(['is_canceled'=>'1']);
                Operation::where('sysid',$sysid_opr)->update(['is_closed'=>'0']);
                OperationUnpaid::where('sysid',$id)->update(['is_void'=>'1']);
                InTransit::where('doc_type','SL')->where('doc_number',$data->doc_number)->update(['is_deleted'=>'1']);
                $info=$this->void_jurnal($id,$request);
                if ($info['state']==true){
                    DB::commit();
                    return response()->success('Success', 'Penerimaan kasir berhasil divoid/dibatalkan');
                } else {
                    DB::rollback();
                    return response()->error('', 501, $info['message']);
                }
            } else {
                DB::rollback();
                return response()->error('', 501, 'Data tidak ditemukan');
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }

    public function getspjinfo(Request $request){
        $id = $request->id;
        $header = Operation::from('t_operation as a')
        ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.ref_date,a.time_boarding,CONCAT(a.vehicle_no,'  - ',a.police_no) AS vehicle_no,a.odometer,a.distance,
            b.route_name,a.rate,a.target,a.target2,a.deposit,a.price_model,c.personal_name AS driver,d.personal_name AS helper, e.personal_name AS conductor,operation_notes,
            a.passenger,a.revenue,a.others,a.total,a.dispensation,a.ks,a.paid,a.unpaid,a.is_ops_closed,a.is_closed,a.external_cost,a.internal_cost,a.standar_cost,
            a.station_fee,a.operation_fee,a.model,
            a.debt_accident,a.debt_deposit,a.driver_id,a.helper_id,a.conductor_id")
        ->leftjoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftjoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftjoin('m_personal as d','a.helper_id','=','d.employee_id')
        ->leftjoin('m_personal as e','a.conductor_id','=','e.employee_id')
        ->where('a.sysid', $id)->first();
        $data['header']=$header;
        if ($data){
            $data['route1']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','point','factor_point','passenger','total')
                ->where('sysid',$id)
                ->where('flag_route','GO')
                ->get();
            $data['route2']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','point','factor_point','passenger','total')
                ->where('sysid',$id)
                ->where('flag_route','BACK')
                ->get();
        }
        return response()->success('Success', $data);
    }

    public function get(Request $request)
    {
        $id = $request->id;
        $header = OpsCashier::where('sysid', $id)->first();
        $data['header']=$header;
        if ($data) {
            $opr_id=$header->sysid_operation;
            $data['route1']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','point','factor_point','passenger','total')
                ->where('sysid',$opr_id)
                ->where('flag_route','GO')
                ->get();
            $data['route2']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','point','factor_point','passenger','total')
                ->where('sysid',$opr_id)
                ->where('flag_route','BACK')
                ->get();
            $data['spj'] = Operation::from('t_operation as a')
            ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.ref_date,a.time_boarding,CONCAT(a.vehicle_no,'  - ',a.police_no) AS vehicle_no,a.odometer,a.distance,
                b.route_name,a.rate,a.target,a.deposit,a.price_model,c.personal_name AS driver,d.personal_name AS helper, e.personal_name AS conductor,a.model")
            ->leftjoin('m_bus_route as b','a.route_id','=','b.sysid')
            ->leftjoin('m_personal as c','a.driver_id','=','c.employee_id')
            ->leftjoin('m_personal as d','a.helper_id','=','d.employee_id')
            ->leftjoin('m_personal as e','a.conductor_id','=','e.employee_id')
            ->where('a.sysid', $opr_id)->first();
        }
        return response()->success('Success', $data);
    }
    public function post(Request $request)
    {
        $data = $request->json()->all();
        $opr = $data['operation'];
        $where = $data['where'];
        $rec = $data['data'];
        $rec['pool_code'] = PagesHelp::PoolCode($request);
        $route1 = $data['route1'];
        $route2 = $data['route2'];
        $others = $data['others'];
        $validator=Validator::make($rec,[
            'ref_date'=>'bail|required',
            'doc_operation'=>'bail|required',
            'pool_code'=>'bail|required'
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'doc_operation.required'=>'Nomor SPJ harus diisi',
            'pool_code.required'=>'Pool kendaraan harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $operasi=Operation::selectRaw("is_ops_closed,is_closed")->where('sysid',$rec['sysid_operation'])->first();
        if ($operasi->is_ops_closed=='0') {
            return response()->error('', 501, "SPJ belum diapproved bagian operasi");
        } else if (($operasi->is_closed==1) && ($opr=='inserted')) {
            return response()->error('', 501, "SPJ sudah dilakukan penerimaan kasir");
        }
        if (floatval($rec['total_mustpaid']) < floatval($rec['paid'])) {
            return response()->error('', 501, "Penerimaan melebihi yang seharusnya dibayar");
        } else if (floatval($rec['unpaid'])<0) {
            return response()->error('', 501, "Kurang bayar tidak boleh MINUS");
        }
        if ($opr=='updated') {
            $ops=OpsCashier::selectRaw("is_canceled")->where($where)->first();
            if ($ops){
                if ($ops->is_canceled==1){
                    return response()->error('', 501, "Transaksi Penerimaan sudah dibatalkan/divoid");
                }
            }
        }
        $sysid = $rec['sysid'];
        DB::beginTransaction();
        try {
            $rec['ks']=$rec['unpaid'];
            $rec['update_userid'] = PagesHelp::UserID($request);
            $rec['update_timestamp'] =new \DateTime();
            $sysid_opr=$rec['sysid_operation'];
            unset($rec['sysid']);
            unset($rec['total_mustpaid']);
            if ($opr == 'updated') {
                if (InTransit::where('doc_type','SL')
                ->where('doc_number',$rec['doc_number'])
                ->where('is_deposit',1)->exists()) {
                    return response()->error('', 501, 'Penyetoran intransit sudah diproses, data tidak bisa diubah');
                }
                OpsCashier::where($where)
                    ->update($rec);
                OperationUnpaid::where($where)->delete();
            } else if ($opr = 'inserted') {
                $rec['doc_number'] = OpsCashier::GenerateNumber($rec['pool_code'],$rec['ref_date']);
                $sysid = OpsCashier::insertGetId($rec);
            }
            foreach($route1 as $row){
                OperationRoute::where('sysid',$sysid_opr)
                ->where('line_id',$row['line_id'])
                ->where('flag_route','GO')
                ->update(['point'=>$row['point'],
                    'factor_point'=>$row['factor_point'],
                    'passenger'=>$row['passenger'],
                    'total'=>$row['total']
                ]);
            }
            foreach($route2 as $row){
                OperationRoute::where('sysid',$sysid_opr)
                ->where('line_id',$row['line_id'])
                ->where('flag_route','BACK')
                ->update(['point'=>$row['point'],
                    'factor_point'=>$row['factor_point'],
                    'passenger'=>$row['passenger'],
                    'total'=>$row['total']
                ]);
            }
            DB::table('t_operation_others')->where('sysid',$sysid)->delete();
            foreach($others  as $row){
               DB::table('t_operation_others')->insert([
                   'sysid'=>$sysid,
                   'item_code'=>$row['item_code'],
                   'item_name'=>$row['item_name'],
                   'amount'=>$row['amount']
               ]);
            }
            OpsChashierOthers::where('sysid',$sysid)->delete();
            $cost=array();
            $cost['sysid']=$sysid;
            $cost['ref_date']=$rec['ref_date'];
            foreach($others  as $row){
                $field='o_'.$row['item_code'];
                $cost[$field]=$row['amount'];
            }
            OpsChashierOthers::insert($cost);
            Operation::where('sysid',$sysid_opr)->update(['is_closed'=>'1']);
            DB::insert("INSERT INTO t_ar_document(sysid,doc_number,ops_number,conductor_id,ref_date,amount,update_userid,update_timestamp,descriptions)
                SELECT a.sysid,a.doc_number,a.doc_operation,b.conductor_id,a.ref_date,a.unpaid,a.update_userid,a.update_timestamp,CONCAT('Kurang setor SPJ : ',doc_operation)
                FROM t_operation_cashier a
                LEFT JOIN t_operation b ON a.sysid_operation=b.sysid
                WHERE a.sysid=? AND a.unpaid>0",[$sysid]);
            DB::update("UPDATE t_operation a INNER JOIN t_operation_cashier b ON a.sysid=b.sysid_operation
             SET a.ks=b.ks,a.paid=b.paid,a.unpaid=b.unpaid,a.dispensation=b.dispensation
             WHERE a.sysid=? AND b.sysid=?",[$rec['sysid_operation'],$sysid]);
            DB::update("UPDATE m_personal a
                INNER JOIN
                (SELECT conductor_id,SUM(amount-paid) AS account FROM t_ar_document
                WHERE is_paid=0 AND is_void=0
                GROUP BY conductor_id) b ON a.employee_id=b.conductor_id
                SET a.account=b.account");
            $info=$this->build_jurnal($sysid,$request);
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
        /* Jurnal untuk Penerimaan SPJ
           Kas Kecil Operasional
             Pendapatan
         */
        $ret['state']=true;
        $ret['message']='';
        $data=OpsCashier::from('t_operation_cashier as a')
        ->select('a.sysid','a.doc_number','a.ref_date','a.sysid_jurnal','a.trans_code','a.trans_series',
            'a.doc_operation','b.vehicle_no','b.police_no','a.others','a.revenue','a.others','a.dispensation',
            'a.ks','a.paid','a.unpaid','a.pool_code','a.target2')
        ->join('t_operation as b','a.sysid_operation','=','b.sysid')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $revenue=floatval($data->paid)-(floatval($data->others)+floatval($data->target2));

            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $series = Journal1::GenerateNumber('SL',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->doc_operation,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'SL',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'1',
                  'notes'=>'Ops. '.$data->vehicle_no.' '.$data->doc_operation.' '.$data->doc_number
              ]);
            } else {
                $sysid_jurnal=$data->sysid_jurnal;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->doc_operation,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'1',
                  'notes'=>'Ops. '.$data->vehicle_no.' '.$data->doc_operation.' '.$data->doc_number
                ]);
            }
            $acc=Accounting::Config();
            $account_revenue=$acc->revenue_account;
            $account_ks=$acc->ar_conductor_account;
            $account_cash='';
            $pool=Pools::select('cash_intransit')->where('pool_code',$data->pool_code)->first();
            if ($pool){
                $account_cash=$pool->cash_intransit;
            }
            /* Kas Kecil */
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$account_cash,
                'line_memo'=>'Intransit - SPJ .'.$data->doc_operation.' '.$data->vehicle_no.' - '.$data->police_no,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->doc_operation,
                'debit'=>$revenue,
                'credit'=>0,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $line++;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$account_revenue,
                'line_memo'=>'Pendapatan SPJ .'.$data->doc_operation.' '.$data->vehicle_no.' - '.$data->police_no,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->doc_operation,
                'debit'=>0,
                'credit'=>$revenue,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            OpsCashier::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                OpsCashier::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'SL',
                'trans_series'=>$series]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        } else {
            $ret['state']=false;
            $ret['message']='Data tidak ditemukan';
        }
        return $ret;
    }

    public function expenses(Request $request){
        $sysid=isset($request->sysid) ? $request->sysid:-1;
        $sysid_operation=isset($request->sysid_operation) ? $request->sysid_operation:-1;
        if (DB::table('t_operation_others')->where('sysid',$sysid)->exists()) {
            $data=OtherItems::from('m_others_item as a')
            ->selectRaw("a.item_code,a.item_name,IFNULL(b.amount,a.amount) AS amount")
            ->leftJoin('t_operation_others as b', function($join) use($sysid)
                {
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.sysid','=',DB::raw("$sysid"));
                })
            ->where('a.is_active',1)
            ->get();
        } else {
            $data=OtherItems::from('m_others_item as a')
            ->selectRaw("a.item_code,a.item_name,IFNULL(b.amount,a.amount) AS amount")
            ->leftJoin('t_operation_others_draft as b', function($join) use($sysid_operation)
                {
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.sysid','=',DB::raw("$sysid_operation"));
                })
            ->where('a.is_active',1)
            ->get();
        }
        return response()->success('Success', $data);
    }
    public function print(Request $request){
        $sysid=$request->sysid;
        $header=OpsCashier::from('t_operation_cashier as a')
            ->selectRaw("a.doc_number,a.paid_type,a.sysid_operation,a.doc_operation,a.ref_date,a.deposit,a.target,a.target2,
            a.revenue,a.others,a.total,a.dispensation,
            a.ks,a.paid,a.unpaid,a.trans_code,a.trans_series,
            b.vehicle_no,b.police_no,c.route_name,d.personal_name AS driver,
            e.personal_name AS helper,f.personal_name AS conductor,b.ref_date as spj_date,
            IFNULL(g.user_name,a.update_userid) as user,a.standar_cost,a.internal_cost,a.external_cost,a.operation_fee,a.station_fee")
            ->leftJoin('t_operation as b','a.sysid_operation','=','b.sysid')
            ->leftJoin('m_bus_route as c','b.route_id','=','c.sysid')
            ->leftJoin('m_personal as d','b.driver_id','=','d.employee_id')
            ->leftJoin('m_personal as e','b.helper_id','=','e.employee_id')
            ->leftJoin('m_personal as f','b.conductor_id','=','f.employee_id')
            ->leftjoin('o_users as g','a.update_userid','=','g.user_id')
            ->where('a.sysid',$sysid)->first();
        if (!($header==null)){
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $header->spj_date=date_format(date_create($header->spj_date),'d-m-Y');
            $data['header']=$header;
            $go=OperationRoute::select('checkpoint_name','factor_point','point','passenger','total')
            ->where('sysid',$header->sysid_operation)
            ->where('flag_route','GO')
            ->orderBy('line_id')
            ->get();
            $back=OperationRoute::select('checkpoint_name','factor_point','point','passenger','total')
            ->where('sysid',$header->sysid_operation)
            ->where('flag_route','BACK')
            ->orderBy('line_id')
            ->get();
            $profile=PagesHelp::Profile();
            $terbilang=PagesHelp::Terbilang($header->paid);
            $others=DB::table('t_operation_others')->where('sysid',$sysid)->get();
            $pdf = PDF::loadView('finance.Spj',['header'=>$header,'go'=>$go,'back'=>$back,'others'=>$others,
                'profile'=>$profile,'terbilang'=>$terbilang])->setPaper(array(0, 0, 612,504),'portrait');
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
        $pool_code = $request->pool_code;
        $data= OpsCashier::from('t_operation_cashier as a')
        ->selectRaw("a.sysid as _index,a.doc_number,a.ref_date,a.doc_operation,b.ref_date AS ops_date, b.vehicle_no,b.route_id,c.route_name,b.passenger AS jpoint,
                    a.revenue,a.target,a.target2,a.dispensation,a.paid,-a.ks as ks,a.external_cost,a.internal_cost,b.driver_id,d.personal_name AS driver_name,a.others,
                    b.conductor_id,e.personal_name AS conductor_name,a.station_fee,a.operation_fee,b.model,a.external_cost,
                    b.helper_id,f.personal_name AS helper_name,a.pool_code,a.update_userid,a.update_timestamp")
        ->leftJoin('t_operation as b','a.sysid_operation','=','b.sysid')
        ->leftJoin('m_bus_route as c','b.route_id','=','c.sysid')
        ->leftJoin('m_personal as d','b.driver_id','=','d.employee_id')
        ->leftJoin('m_personal as e','b.conductor_id','=','e.employee_id')
        ->leftJoin('m_personal as f','b.helper_id','=','f.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_canceled','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_operation', 'like', $filter)
                    ->orwhere('b.vehicle_no', 'like', $filter)
                    ->orwhere('b.police_no', 'like', $filter)
                    ->orwhere('b.pool_code', 'like', $filter);
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
        $pool_code = $request->pool_code;
        $data= OpsCashier::from('t_operation_cashier as a')
        ->selectRaw("a.sysid as _index,a.doc_number,a.ref_date,a.doc_operation,b.ref_date AS ops_date, b.vehicle_no,b.route_id,c.route_name,b.passenger AS jpoint,
                    a.revenue,a.target,a.target2,a.dispensation,a.paid,-a.ks as ks,a.external_cost,a.internal_cost,b.driver_id,d.personal_name AS driver_name,a.others,
                    b.conductor_id,e.personal_name AS conductor_name,a.station_fee,a.operation_fee,b.model,a.external_cost,
                    b.helper_id,f.personal_name AS helper_name,a.pool_code,a.update_userid,a.update_timestamp")
        ->leftJoin('t_operation as b','a.sysid_operation','=','b.sysid')
        ->leftJoin('m_bus_route as c','b.route_id','=','c.sysid')
        ->leftJoin('m_personal as d','b.driver_id','=','d.employee_id')
        ->leftJoin('m_personal as e','b.conductor_id','=','e.employee_id')
        ->leftJoin('m_personal as f','b.helper_id','=','f.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_canceled','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PENERIMAAN KASIR');
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
        $sheet->setCellValue('B5', 'Tgl.Setor');
        $sheet->setCellValue('C5', 'No.SPJ');
        $sheet->setCellValue('D5', 'Tgl.SPJ');
        $sheet->setCellValue('E5', 'No. Seri');
        $sheet->setCellValue('F5', 'Trayek');
        $sheet->setCellValue('G5', 'JP');
        $sheet->setCellValue('H5', 'Pendapatan');
        $sheet->setCellValue('I5', 'Komisi Terminal');
        $sheet->setCellValue('J5', 'Komisi Awak Bus');
        $sheet->setCellValue('K5', 'Target1');
        $sheet->setCellValue('L5', 'tagret2');
        $sheet->setCellValue('M5', 'Lain2');
        $sheet->setCellValue('N5', 'KS');
        $sheet->setCellValue('O5', 'Biaya Ops');
        $sheet->setCellValue('P5', 'Dispensasi');
        $sheet->setCellValue('Q5', 'Bonus');
        $sheet->setCellValue('R5', 'Penerimaan');
        $sheet->setCellValue('S5', 'NIK Pengemudi');
        $sheet->setCellValue('T5', 'Pengemudi');
        $sheet->setCellValue('U5', 'NIK Kondektur');
        $sheet->setCellValue('V5', 'Kondektur');
        $sheet->setCellValue('W5', 'NIK Kernet');
        $sheet->setCellValue('X5', 'Kernet');
        $sheet->setCellValue('Y5', 'Pool');
        $sheet->setCellValue('Z5', 'User Input');
        $sheet->setCellValue('AA5', 'Tgl.Input');
        $sheet->getStyle('A5:AA5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->doc_operation);
            $sheet->setCellValue('D'.$idx, $row->ops_date);
            $sheet->setCellValue('E'.$idx, $row->vehicle_no);
            $sheet->setCellValue('F'.$idx, $row->route_name);
            $sheet->setCellValue('G'.$idx, $row->jpoint);
            $sheet->setCellValue('H'.$idx, $row->model);
            $sheet->setCellValue('H'.$idx, $row->revenue);
            $sheet->setCellValue('I'.$idx, $row->station_fee);
            $sheet->setCellValue('J'.$idx, $row->external_cost);
            $sheet->setCellValue('K'.$idx, $row->target);
            $sheet->setCellValue('L'.$idx, $row->target2);
            $sheet->setCellValue('M'.$idx, $row->others);
            $sheet->setCellValue('N'.$idx, $row->ks);
            $sheet->setCellValue('O'.$idx, $row->internal_cost);
            $sheet->setCellValue('P'.$idx, $row->dispensation);
            $sheet->setCellValue('Q'.$idx, $row->operation_fee);
            $sheet->setCellValue('R'.$idx, $row->paid);
            $sheet->setCellValue('S'.$idx, $row->driver_id);
            $sheet->setCellValue('T'.$idx, $row->driver_name);
            $sheet->setCellValue('U'.$idx, $row->conductor_id);
            $sheet->setCellValue('V'.$idx, $row->conductor_name);
            $sheet->setCellValue('W'.$idx, $row->helper_id);
            $sheet->setCellValue('X'.$idx, $row->helper_name);
            $sheet->setCellValue('Y'.$idx, $row->pool_code);
            $sheet->setCellValue('Z'.$idx, $row->update_userid);
            $sheet->setCellValue('AA'.$idx, $row->update_timestamp);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D6:D'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('AAA6:AA'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('G'.$idx, "=SUM(G6:G$last)");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->setCellValue('J'.$idx, "=SUM(J6:J$last)");
        $sheet->setCellValue('K'.$idx, "=SUM(K6:K$last)");
        $sheet->setCellValue('L'.$idx, "=SUM(L6:L$last)");
        $sheet->setCellValue('M'.$idx, "=SUM(M6:M$last)");
        $sheet->setCellValue('N'.$idx, "=SUM(N6:N$last)");
        $sheet->setCellValue('O'.$idx, "=SUM(O6:O$last)");
        $sheet->setCellValue('P'.$idx, "=SUM(P6:P$last)");
        $sheet->setCellValue('Q'.$idx, "=SUM(Q6:Q$last)");
        $sheet->setCellValue('R'.$idx, "=SUM(R6:R$last)");
        $sheet->getStyle('H6:R'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
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
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('AA')->setWidth(20);
        foreach(range('C','Z') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_kasir_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public static function void_jurnal($sysid,$request) {
        /* Jurnal untuk Penerimaan SPJ
           Pendapatan
             Kas Kecil Operasional
         */
        $ret['state']=true;
        $ret['message']='';
        $data=OpsCashier::from('t_operation_cashier as a')
        ->select('a.sysid','a.doc_number','a.ref_date','a.sysid_jurnal','a.trans_code','a.trans_series',
            'a.doc_operation','b.vehicle_no','b.police_no','a.others','a.revenue','a.others','a.dispensation',
            'a.ks','a.paid','a.unpaid','a.pool_code','a.target2','a.sysid_void','a.trans_series_void')
        ->join('t_operation as b','a.sysid_operation','=','b.sysid')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $revenue=floatval($data->paid)-(floatval($data->others)+floatval($data->target2));

            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_void==-1){
                $series = Journal1::GenerateNumber('SL',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->doc_operation,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'SL',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'1',
                  'notes'=>'Void Ops. '.$data->vehicle_no.' '.$data->doc_operation.' '.$data->doc_number
              ]);
            } else {
                $sysid_jurnal=$data->sysid_void;
                $series=$data->trans_series_void;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->doc_operation,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'1',
                  'notes'=>'Void Ops. '.$data->vehicle_no.' '.$data->doc_operation.' '.$data->doc_number
                ]);
            }
            $acc=Accounting::Config();
            $account_revenue=$acc->revenue_account;
            $account_ks=$acc->ar_conductor_account;
            $account_cash='';
            $pool=Pools::select('cash_intransit')->where('pool_code',$data->pool_code)->first();
            if ($pool){
                $account_cash=$pool->cash_intransit;
            }
            /* Kas Kecil */
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$account_revenue,
                'line_memo'=>'Void Pendapatan SPJ .'.$data->doc_operation.' '.$data->vehicle_no.' - '.$data->police_no,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->doc_operation,
                'debit'=>$revenue,
                'credit'=>0,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $line++;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$account_cash,
                'line_memo'=>'Void Intransit - SPJ .'.$data->doc_operation.' '.$data->vehicle_no.' - '.$data->police_no,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->doc_operation,
                'debit'=>0,
                'credit'=>$revenue,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                OpsCashier::where('sysid',$sysid)
                ->update(['sysid_void'=>$sysid_jurnal,
                    'trans_series_void'=>$series,
                    'void_date'=>Date('Y-m-d'),
                    'void_by'=>PagesHelp::UserID($request)]);
                Journal1::where('sysid',$data->sysid_jurnal)
                ->update(['sysid'=>$data->sysid_jurnal,
                    'sysid_void'=>$sysid_jurnal,
                    'ref_date_void'=>Date('Y-m-d'),
                    'is_void'=>'1']);
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
