<?php

namespace App\Http\Controllers\Purchase;

use App\Models\Master\Partner;
use App\Models\Purchase\JobInvoice1;
use App\Models\Purchase\JobInvoice2;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Service\ServiceExternal;
use App\Models\Service\ServiceExternalDetail;
use App\Models\Service\ServiceExternalJob;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Config\Users;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PagesHelp;
use Inventory;
use Accounting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;

class JobInvoiceController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ? 'desc' : 'asc';
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);
        $date1 = $request->date1;
        $date2 = $request->date2;

        $data = JobInvoice1::selectRaw("
                sysid, doc_number, ref_date, ref_document, service_no, job_number, partner_name,
                due_date, amount, discount, tax, total, payment, unpaid,
                CONCAT(trans_code, '-', trans_series) as voucher,
                sysid_jurnal, is_void, void_date, doc_name, uuid_rec
            ")
            ->where('pool_code', $pool_code)
            ->whereBetween('ref_date', [$date1, $date2]);

        if (!empty($filter)) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('doc_number', 'like', $filter)
                ->orWhere('partner_name', 'like', $filter)
                ->orWhere('service_no', 'like', $filter)
                ->orWhere('job_number', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }


    public function destroy(Request $request)
    {
        $uuid = $request->uuid ?? '';
        $invoice = JobInvoice1::selectRaw('sysid, doc_number, is_void, payment')
            ->where('uuid_rec', $uuid)
            ->first();

        if (!$invoice) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        $sysid = $invoice->sysid;

        if ($invoice->is_void == '1') {
            return response()->error('', 201, 'Invoice tersebut sudah divoid');
        }

        if ($invoice->payment > 0) {
            return response()->error('', 201, 'Invoice tersebut tidak bisa divoid, sudah ada pembayaran');
        }

        DB::beginTransaction();
        try {
            JobInvoice1::where('sysid', $sysid)->update([
                'is_void' => 1,
                'void_by' => PagesHelp::UserID($request),
                'void_date' => now()->toDateString()
            ]);

            $info = $this->void_jurnal($sysid, $request);
            if ($info['state'] === true) {
                $acc = Accounting::Config();
                $ap_invoice = $acc->ap_invoice;
                Accounting::void_customer_account($sysid, 'SPK', $ap_invoice);

                DB::commit();
                return response()->success('Success', [
                    'sysid' => $sysid,
                    'message' => "Pembatalan invoice/SPK berhasil"
                ]);
            }

            DB::rollback();
            return response()->error('', 501, $info['message']);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }


    public function get(Request $request){
        $uuid=isset($request->uuid) ? $request->uuid :'';
        $header=JobInvoice1::from('t_job_invoice1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_document,a.service_no,a.job_number,a.vehicle_no,a.partner_code,a.partner_name,
        a.ref_date,a.top , a.due_date,amount, a.discount, a.tax, a.total, a.payment, a.sysid_jurnal, a.trans_code, a.trans_series,
        a.source_payment,a.link_id,CONCAT(b.police_no,' ',b.vehicle_no,' ',a.service_no,' ',a.job_number) as job_number_desc,a.uuid_rec")
        ->leftjoin("t_workorder_external as b","a.job_number","=","b.doc_number")
        ->where('a.uuid_rec',$uuid)->first();
        if ($header){
            $sysid=$header->sysid;
            $data['header']=$header;
            $data['detail']=JobInvoice2::where('sysid',$sysid)->get();
            return response()->success('Success',$data);
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $header=$data['header'];
        $detail=$data['detail'];
        $header['pool_code']=PagesHelp::PoolCode($request);
        $header['vehicle_no']='';
        $header['service_no']='';
        $header['job_number']==isset($header['job_number']) ? $header['job_number'] :'-';
        $sysid=$header['sysid'];
        $validator=Validator::make($header,[
            'ref_document'=>'bail|required',
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'partner_code'=>'bail|required'
        ],[
            'ref_document.required'=>'Nomor bukti invoice harus diisi',
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'partner_code.required'=>'Bengkel/supplier harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $validator=Validator::make($detail,[
            '*.descriptions'=>'bail|required',
            '*.qty_invoice'=>'bail|required|numeric|min:1',
            '*.price'=>'bail|required|numeric|min:1',
            '*.discount'=>'bail|required|numeric|min:0',
            '*.total'=>'bail|required|numeric|min:1'
        ],[
            '*.descriptions.required'=>'Detail invoice  harus diisi',
            '*.qty_invoice.min'=>'Jumlah invoice harus diisi/lebih besar dari NOL',
            '*.price.min'=>'Harga pembelian tidak boleh NOL',
            '*.discount.min'=>'Diskon pembelian tidak boleh NOL'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if (!($header['job_number']=='')) {
            $external=ServiceExternal::selectRaw("vehicle_no,service_no")->where('doc_number',$header['job_number'])->first();
            if ($external){
                $header['vehicle_no']=$external->vehicle_no;
                $header['service_no']=$external->service_no;
            }
        }
        if ($opr=='updated') {
            $invoice=JobInvoice1::selectRaw('sysid,doc_number,is_void,payment')->where('uuid_rec',$header['uuid_rec'])->first();
            if ($invoice){
                $sysid=$invoice->sysid;
                if ($invoice->is_void=='1') {
                    return response()->error('',201,'Invoice tersebut sudah divoid');
                } else if ($invoice->payment>0){
                    return response()->error('',201,'Invoice tersebut tidak bisa diubah, sudah ada pembayaran');
                } else if ($invoice->ref_date>$header['ref_date']){
                    return response()->error('Gagal',501,'Tanggal transaksi tidak bisa mundur');
                }
            } else {
                return response()->error('Gagal',501,'Data invoice tidak ditemukan');
            }
        } else {
            $sysid=-1;
            $cur_date=Date('Y-m-d');
            if ($header['ref_date']<$cur_date) {
                return response()->error('Gagal',501,'Tanggal transaksi tidak bisa backdate (Mundur)');
            }
        }

        DB::beginTransaction();
        try{
            $header['update_userid'] = PagesHelp::UserID($request);
            $header['unpaid'] =  $header['total'];
            $Partner=Partner::select('partner_name')
            ->where('partner_id',$header['partner_code'])->first();
            if (!($Partner==null)){
                $header['partner_name']=$Partner->partner_name;
            }
            unset($header['sysid']);
            unset($header['job_number_desc']);
            if ($opr=='updated'){
                $ispaid=DB::table('t_customer_account')
                 ->where('ref_sysid',$sysid)
                 ->whereRaw("doc_source='SPK' AND IFNULL(total_paid,0)>0")
                 ->first();
                if ($ispaid) {
                    DB::rollback();
                    return response()->error('',501,'Invoice pekerjaan tidak bisa diubah, invoice sudah ada pembayaran');
                }
                JobInvoice1::where('sysid',$sysid)->update($header);
                JobInvoice2::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $number=JobInvoice1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $header['uuid_rec']=Str::uuid()->toString();
                $sysid=JobInvoice1::insertGetId($header);
            }
            $header['update_timestamp'] = Date('Y-m-d H:i:s');
            foreach($detail as $record) {
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                JobInvoice2::insert($dtl);
            }
            DB::update("UPDATE t_job_invoice1 a
                INNER JOIN
                (SELECT sysid,SUM(qty_invoice*price) AS amount,SUM(discount) AS discount,SUM(total) AS total FROM t_job_invoice2
                WHERE sysid=? GROUP BY sysid)  b ON a.sysid=b.sysid
                SET a.amount=b.amount,a.discount=b.discount,a.total=b.total
                WHERE a.sysid=?",[$sysid,$sysid]);
            DB::update("UPDATE t_workorder_external a INNER JOIN t_job_invoice1 b ON a.doc_number=b.job_number
                SET a.partner_id=b.partner_code,a.partner_name=b.partner_name,a.is_closed=1,a.close_date=CURRENT_DATE(),
                a.invoice_number=b.doc_number
                WHERE a.doc_number=?",[$header['job_number']]);
            $info=$this->build_jurnal($sysid,$request);
            if ($info['state']==true){
                $acc=Accounting::Config();
                $ap_invoice=isset($acc->ap_invoice) ? $acc->ap_invoice :'';
                Accounting::create_customer_account($sysid,'SPK',$ap_invoice);
                DB::commit();
                $respon['uuid']=$header['uuid_rec'];
                $respon['message']="Simpan data berhasil";
                return response()->success('Success',$respon);
            } else {
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function request(Request $request){
        $pool_code=PagesHelp::PoolCode($request);
        $partner_code=isset($request->partner_code) ?$request->partner_code:'-';
        $data=ServiceExternal::selectRaw("doc_number,CONCAT(police_no,'-',vehicle_no,'-',service_no) AS descriptions")
        ->where('pool_code',$pool_code)
        ->where('is_closed','0')
        ->where('is_cancel','0')
        ->orderBy('ref_date','desc')
        ->offset(0)
        ->limit(100)
        ->get();
        return response()->success('Success',$data);
    }

    public function material(Request $request){
        $sysid=isset($request->sysid) ?$request->sysid:'-';
        $data=ServiceExternalDetail::where('sysid',$sysid)
        ->get();
        return response()->success('Success',$data);
    }

    public function job(Request $request){
        $sysid=isset($request->sysid) ?$request->sysid:'-';
        $data=ServiceExternalJob::where('sysid',$sysid)
        ->get();
        return response()->success('Success',$data);
    }

    public function external(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $data= ServiceExternal::from('t_workorder_external as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.partner_id,a.partner_name,a.vehicle_no,
        a.police_no,a.notes,a.cost_estimation,a.date_estimation,a.time_estimation,a.is_closed,a.service_no,a.is_cancel")
        ->where('a.pool_code',$pool_code)
        ->where('a.is_closed',0)
        ->where('a.is_cancel',0);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('a.doc_number','like',$filter)
                   ->orwhere('a.partner_name','like',$filter)
                   ->orwhere('a.police_no','like',$filter)
                   ->orwhere('a.vehicle_no','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }
    public static function build_jurnal($sysid,$request) {
        /* Biaya Biaya
             Hutang
         */
        $ret['state']=true;
        $ret['message']='';
        $data=JobInvoice1::selectRaw('pool_code,ref_document,doc_number,partner_name,ref_date,
        sysid_jurnal,trans_code,trans_series')
        ->where('sysid',$sysid)->first();
        if ($data){
            $pool_code=$data->pool_code;
            $detail=JobInvoice2::from('t_job_invoice2 as a')
            ->selectRaw('a.descriptions,a.total,a.account_no')
            ->where('a.sysid',$sysid)
            ->get();
            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $series = Journal1::GenerateNumber('SPK',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->ref_document,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'SPK',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'6',
                  'notes'=>'SPK '.$data->ref_document.' '.$data->partner_name
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
                  'reference2'=>$data->ref_document,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'10',
                  'notes'=>'SPK. '.$data->ref_document.' '.$data->partner_name
                ]);
            }
            $acc=Accounting::Config();
            $ap_invoice=$acc->ap_invoice;
            /* Inventory */
            $line=0;
            $ap=0;
            foreach($detail as $row){
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->account_no,
                    'line_memo'=>substr($row->descriptions,0,199),
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->ref_document,
                    'debit'=>$row->total,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $ap = $ap + floatval($row->total);
            }
            $line++;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$ap_invoice,
                'line_memo'=>'SPK-'.$data->doc_number.'-'.$data->ref_document.'-'.$data->partner_name,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->ref_document,
                'debit'=>0,
                'credit'=>$ap,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                JobInvoice1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'INV',
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
    public static function void_jurnal($sysid,$request) {
        /* Hutang
             Biaya
         */
        $ret['state']=true;
        $ret['message']='';
        $data=JobInvoice1::selectRaw('pool_code,ref_document,doc_number,partner_name,void_date,
        sysid_void,trans_code,trans_series_void')
        ->where('sysid',$sysid)->first();
        if ($data){
            $pool_code=$data->pool_code;
            $detail=JobInvoice2::from('t_job_invoice2 as a')
            ->selectRaw('a.descriptions,a.total,a.account_no')
            ->where('a.sysid',$sysid)
            ->get();
            $realdate = date_create($data->void_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_void==-1){
                $series = Journal1::GenerateNumber('SPK',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->void_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->ref_document,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'10',
                  'trans_code'=>'SPK',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'6',
                  'notes'=>'Pembatalan SPK '.$data->ref_document.' '.$data->partner_name
              ]);
            } else {
                $sysid_jurnal=$data->sysid_void;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->ref_document,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'10',
                  'notes'=>'SPK. '.$data->ref_document.' '.$data->partner_name
                ]);
            }
            $acc=Accounting::Config();
            $ap_invoice=$acc->ap_invoice;
            /* Hutang */
            $line=1;
            $ap=0;
            foreach($detail as $row){
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->account_no,
                    'line_memo'=>'Pembatalan '.substr($row->descriptions,0,150),
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->ref_document,
                    'debit'=>0,
                    'credit'=>$row->total,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $ap = $ap + floatval($row->total);
            }
            $line=1;
            Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line,
                'no_account'=>$ap_invoice,
                'line_memo'=>'Pembatalan SPK-'.$data->doc_number.'-'.$data->ref_document.'-'.$data->partner_name,
                'reference1'=>$data->doc_number,
                'reference2'=>$data->ref_document,
                'debit'=>$ap,
                'credit'=>0,
                'project'=>PagesHelp::Project($data->pool_code)
            ]);
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                JobInvoice1::where('sysid',$sysid)
                ->update(['sysid_void'=>$sysid_jurnal,
                'trans_code'=>'INV',
                'trans_series_void'=>$series]);
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
        $uuid=isset($request->uuid) ? $request->uuid :'';
        $header=JobInvoice1::from('t_job_invoice1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_document,a.partner_name,a.ref_date,a.due_date,
            a.amount,a.discount,a.tax,a.total as net_total,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.pool_code,
            IFNULL(a.vehicle_no,'') AS vehicle_no,a.service_no,b.user_name,a.vehicle_no,c.descriptions as unit_notes,c.police_no,
            a.update_timestamp,a.update_userid,a.update_timestamp")
        ->leftJoin('o_users as b','a.update_userid','=','b.user_id')
        ->leftJoin('m_vehicle as c','a.vehicle_no','=','c.vehicle_no')
        ->where('a.uuid_rec',$uuid)->first();
        if ($header) {
            $detail=JobInvoice2::from('t_job_invoice2 as a')
            ->selectRaw("a.line_no,a.descriptions,a.qty_invoice,a.price,a.discount,a.total")
            ->orderby('a.line_no','asc')
            ->where('a.sysid',$header->sysid)->get();
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $header->due_date=date_format(date_create($header->due_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $sign=array();
            $sign['user_sign']='';

            $home = storage_path().'/app/';
            $user=Users::selectRaw("IFNULL(sign,'') as sign")->where('user_id',$header->update_userid)->first();
            if ($user) {
                if ($user->sign<>''){
                    $sign['user_sign']=$home.$user->sign;
                }
            }
            $pdf = PDF::loadView('finance.invoicejob',['header'=>$header,'detail'=>$detail,'profile'=>$profile,'sign'=>$sign])->setPaper('A4','potriat');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

    public function upload_document(Request $request)
    {
        $uuid  = isset($request->uuid) ? $request->uuid : '';
        $uploadedFile = $request->file('file');
        $originalFile = $uploadedFile->getClientOriginalName();
        $ori= $uploadedFile->getClientOriginalName();
        $originalFile = Date('Ymd-His')."-".$originalFile;
        $doc=JobInvoice1::selectRaw('sysid,ref_date')
        ->where('uuid_rec',$uuid)->first();
        if ($doc) {
            $sysid=$doc->sysid;
            $directory="public/invoice/".substr($doc->ref_date,0,4);
            $path = $uploadedFile->storeAs($directory,$originalFile);
            JobInvoice1::where('sysid',$sysid)
            ->update([
            'invoice_path'=>$path,
            'doc_name'=>$ori]);
            CustomerAccount::where('uuid_invoice',$uuid)
            ->update([
                'invoice_path'=>$path,
                'doc_name'=>$ori
            ]);
            $respon['path_file']=$path;
            $respon['message']='Upload dokumen berhasil';
            return response()->success('success',$respon);
        } else {
            $respon['path_file']='';
            $respon['message']='Data tidak ditemukan';
            return response()->error('',501,$respon);
        }
    }

    public function download_document(Request $request)
    {
        $uuid  = isset($request->uuid) ? $request->uuid : '';
        $doc=JobInvoice1::selectRaw('sysid,doc_name,invoice_path,ref_date')
        ->where('uuid_rec',$uuid)->first();
        if ($doc) {
            $file=$doc->invoice_path;
            $publicPath = \Storage::url($file);
            $backfile =$doc->doc_name;
            return Storage::download($file, $backfile,[]);
        } else {
            return response()->error('',301,'Dokumen tidak ditemukan');
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
        $start_date = $request->start_date;
        $data= JobInvoice1::from('t_job_invoice1 as a')
        ->selectRaw("(a.sysid*1000)+b.line_no as _index,a.doc_number,a.ref_document,a.partner_code,a.partner_name,a.ref_date,a.due_date,
            a.amount,a.discount,a.tax,a.total as net_total,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.pool_code,
            IFNULL(a.vehicle_no,'') AS vehicle_no,a.service_no,a.update_userid,
            a.update_timestamp,b.line_no,b.item_code,c.part_number,b.descriptions,IFNULL(mou_inventory,'') as mou_inventory,b.qty_invoice,b.price,b.discount,b.total")
        ->leftJoin('t_job_invoice2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_void',0);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.ref_document', 'like', $filter)
                    ->orwhere('a.partner_name', 'like', $filter)
                    ->orwhere('b.item_code', 'like', $filter)
                    ->orwhere('c.part_number', 'like', $filter)
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
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= JobInvoice1::from('t_job_invoice1 as a')
        ->selectRaw("(a.sysid*1000)+b.line_no as _index,a.doc_number,a.ref_document,a.partner_code,a.partner_name,a.ref_date,a.due_date,
            a.amount,a.discount,a.tax,a.total as net_total,CONCAT(a.trans_code,'-',a.trans_series) AS voucher,a.pool_code,
            IFNULL(a.vehicle_no,'') AS vehicle_no,a.service_no,a.update_userid,
            a.update_timestamp,b.line_no,b.item_code,c.part_number,b.descriptions,IFNULL(mou_inventory,'') as mou_inventory,b.qty_invoice,b.price,b.discount,b.total")
        ->leftJoin('t_job_invoice2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_void',0);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PEMBELIAN (SPK/WO)');
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
        $sheet->setCellValue('C5', 'Invoice Supplier');
        $sheet->setCellValue('D5', 'Kode Supplier');
        $sheet->setCellValue('E5', 'Nama Supplier');
        $sheet->setCellValue('F5', 'No.Service');
        $sheet->setCellValue('G5', 'No.Body');
        $sheet->setCellValue('H5', 'Kode Item');
        $sheet->setCellValue('I5', 'No. Part');
        $sheet->setCellValue('J5', 'Nama Barang/Pekerjaan');
        $sheet->setCellValue('K5', 'Jml.Invoice');
        $sheet->setCellValue('L5', 'Satuan');
        $sheet->setCellValue('M5', 'Harga');
        $sheet->setCellValue('N5', 'Diskon');
        $sheet->setCellValue('O5', 'Total');
        $sheet->setCellValue('P5', 'Pool');
        $sheet->setCellValue('Q5', 'User Input');
        $sheet->setCellValue('R5', 'Tgl.Input');
        $sheet->getStyle('A5:R5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->ref_document);
            $sheet->setCellValue('D'.$idx, $row->partner_code);
            $sheet->setCellValue('E'.$idx, $row->partner_name);
            $sheet->setCellValue('F'.$idx, $row->service_no);
            $sheet->setCellValue('G'.$idx, $row->vehicle_no);
            $sheet->setCellValue('H'.$idx, $row->item_code);
            $sheet->setCellValue('I'.$idx, $row->part_number);
            $sheet->setCellValue('J'.$idx, $row->descriptions);
            $sheet->setCellValue('K'.$idx, $row->qty_invoice);
            $sheet->setCellValue('L'.$idx, $row->mou_inventory);
            $sheet->setCellValue('M'.$idx, $row->price);
            $sheet->setCellValue('N'.$idx, $row->discount);
            $sheet->setCellValue('O'.$idx, $row->total);
            $sheet->setCellValue('P'.$idx, $row->pool_code);
            $sheet->setCellValue('Q'.$idx, $row->update_userid);
            $sheet->setCellValue('R'.$idx, $row->update_timestamp);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('H6:I'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('R6:R'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('N'.$idx, "TOTAL");
        $sheet->setCellValue('O'.$idx, "=SUM(O6:O$last)");
        $sheet->getStyle('M6:M'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('N6:N'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        $sheet->getStyle('O6:O'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:R5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'R'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:R'.$idx)->applyFromArray($styleArray);
        foreach(range('C','R') as $columnID) {
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
        $xls="laporan_spk_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
