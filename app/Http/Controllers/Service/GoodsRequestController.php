<?php

namespace App\Http\Controllers\Service;

use App\Models\Master\Partner;
use App\Models\Service\GoodsRequest1;
use App\Models\Service\GoodsRequest2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service\Service;
use PagesHelp;
use Inventory;
use Accounting;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
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
use Illuminate\Support\Str;

class GoodsRequestController extends Controller
{
    public function show(Request $request){
        $filter     = $request->filter;
        $limit      = $request->limit;
        $sorting    = ($request->descending=="true") ?'desc':'asc';
        $sortBy     = $request->sortBy;

        $pool_code  =PagesHelp::Session()->pool_code;
        $date1 = $request->date1;
        $date2 = $request->date2;
        $isOpen = isset($request->isopen) ? $request->isopen : '1';

        $data= GoodsRequest1::from('t_inventory_booked1 as a')
        ->selectRaw("a.sysid,
        a.doc_number,
        a.reference,
        a.ref_date,
        a.notes,
        a.service_no,
        a.vehicle_no,
        a.is_approved,
        a.approved_date,
        a.approved_by,
        b.police_no,
        a.is_autoclosed,
        a.uuid_rec,
        a.sysid_jurnal,
        a.pool_code,
        a.warehouse_id,
        CONCAT(a.trans_code,'-',a.trans_series) as voucher")
        ->leftjoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
        ->where('a.pool_code',$pool_code)
        ->where('is_credit_note','0');

        if ($isOpen=='1') {
            $data=$data->where('a.is_approved','0')
            ->where('a.is_autoclosed','0');
        } else {
            $data=$data
            ->where('a.ref_date','>=',$date1)
            ->where('a.ref_date','<=',$date2);
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                $q->where('a.doc_number','like',$filter)
                ->orwhere('a.vehicle_no','like',$filter)
                ->orwhere('a.service_no','like',$filter)
                ->orwhere('b.police_no','like',$filter);
            });
        }

        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function get(Request $request){
        $uuid=$request->uuid ?? '';
        $header=GoodsRequest1::SelectRaw("
            sysid,
            doc_number,
            reference,
            ref_date,
            notes,
            service_no,
            vehicle_no,
            pool_code,
            warehouse_id,
            is_approved,
            approved_date,
            approved_by,
            is_autoclosed,
            sysid_jurnal,
            trans_code,
            trans_series,
            uuid_rec
        ")->where('uuid_rec',$uuid)->first();
        $detail=GoodsRequest2::from('t_inventory_booked2 as a')
        ->selectRaw("
            a.sysid,
            a.line_no,
            a.item_code,
            b.part_number,
            a.descriptions,
            a.qty_item,
            a.qty_supply,
            a.mou_inventory,
            a.notes"
        )
        ->leftJoin('m_item as b','a.item_code','=','b.item_code')
        ->where('sysid',$header->sysid ?? -1)->get();

        $data=[
            'header'=>$header,
            'detail'=>$detail
        ];
        return response()->success('Success',$data);

    }

    public function post(Request $request){
        $data   = $request->json()->all();
        $header =$data['header'];
        $detail =$data['detail'];

        $session =PagesHelp::Session();

        $header['pool_code']    = $session->pool_code;
        $header['warehouse_id'] = PagesHelp::Warehouse($session->pool_code);

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'warehouse_id'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'warehouse_id.required'=>'Gudang harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->all());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|exists:m_item,item_code',
            '*.qty_item'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_item.min'=>'Jumlah invoice harus diisi/lebih besar dari NOL',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $service=Service::where('doc_number',$header['service_no'])->first();
        if ($service){
            if ($service->is_closed=='1'){
                return response()->error('',400,"Workorder tersebut sudah selesai,Permintaan barang tidak bisa dilakukan");
            }
        } else {
            return response()->error('',400,"Workorder tersebut tidak ditemukan");
        }

        DB::beginTransaction();
        try{
            $req=GoodsRequest1::where('uuid_rec',$header['uuid_rec'] ?? '')->first();
            if (!$req){
                $req = new GoodsRequest1();
                $req->doc_number  = GoodsRequest1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $req->uuid_rec    = Str::uuid();
            } else {
                if ($req->is_approved=='1'){
                    DB::rollback();
                    return response()->error('',501,'Permintaan sudah diproses, tidak bisa diubah');
                }
                GoodsRequest2::where('sysid',$req->sysid)->delete();
            }
            $req->fill([
                'reference'   => $header['reference'] ?? '',
                'ref_date'    => $header['ref_date'],
                'service_no'  => $header['service_no'],
                'vehicle_no'  => $header['vehicle_no'],
                'pool_code'   => $header['pool_code'],
                'warehouse_id'=> $header['warehouse_id'],
                'is_approved'  => 0,
                'is_autoclosed'=> 0,
                'approved_by'  => '',
                'sysid_jurnal' => -1,
                'trans_code'   => '',
                'trans_series' => '',
                'update_userid'=>$session->user_id,
                'update_timestamp'=>Date('Y-m-d H:i:s'),
            ]);
            $req->save();
            $sysid=$req->sysid;

            foreach($detail as $line) {
                GoodsRequest2::insert([
                    "sysid"     => $sysid,
                    "line_no"   => $line['line_no'],
                    "item_code" => $line['item_code'],
                    "descriptions" =>$line['descriptions'],
                    "mou_inventory"=>$line['mou_inventory'],
                    "qty_item"  => $line['qty_item'],
                    "qty_supply"=> 0,
                    "notes"     => $line['notes'],
                    "qty_used"  => 0,
                    "itemcost"  => 0,
                    "line_cost" => 0,
                    "warehouse_id"=> $req->warehouse_id
                ]);
            }
            $this->build_item($header['service_no']);
            DB::commit();
            $respon= [
                'uuid_rec'=>$req->uuid_rec,
                'message'=>"Simpan data berhasil"
            ];
            return response()->success('Success', $respon);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function approved(Request $request){
        $data= $request->json()->all();
        $header=$data['header'];
        $detail=$data['detail'];
        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->all());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|exists:m_item,item_code',
            '*.qty_item'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_item.min'=>'Jumlah invoice harus diisi/lebih besar dari NOL',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        if (Service::where('doc_number',$header['service_no'])
            ->where('is_closed','1')->exists()) {
            return response()->error('',501,'Nomor workorder/service '.$header['service_no'].' sudah selesai');
        }

        DB::beginTransaction();
        try{
            $req=GoodsRequest1::selectRaw("sysid,is_approved,is_autoclosed,approved_date,approved_by,
            warehouse_id,uuid_rec")
            ->where('uuid_rec',$header['uuid_rec'] ?? '')
            ->first();

            if (!$req){
                DB::rollback();
                return response()->error('',501,'Permintaan barang tidak ditemukan');
            }

            if ($req->is_approved=='1'){
                DB::rollback();
                return response()->error('',501,'Permintaan sudah diproses, tidak bisa diubah');
            } else if ($req->is_autoclosed=='1'){
                DB::rollback();
                return response()->error('',501,'Workorder service sudah selesai, tidak bisa diubah');
            }

            $req->is_approved   = 1;
            $req->approved_by   = PagesHelp::Session()->user_id;
            $req->approved_date = Date('Y-m-d H:i:s');
            $req->save();

            $sysid = $req->sysid;

            foreach($detail as $line) {
                GoodsRequest2::where('sysid',$sysid)
                ->where('item_code',$line['item_code'])
                ->update([
                    'qty_supply'=>$line['qty_supply'],
                    'notes'=>$line['notes'],
                    'warehouse_id'=>$req->warehouse_id,
                    'qty_used'=>$line['qty_supply']
                ]);
            }
            $this->build_item($header['service_no']);

            if (DB::table('t_item_mutation')
                ->where('sysid',$sysid)
                ->where('doc_type','IOS')
                ->exists()) {
                $opr='updated';
            } else {
                $opr='inserted';
            }

            $respon=Inventory::ItemCard($sysid,'IOS',$opr,false,false,true);

            if ($respon['success']==false){
                DB::rollback();
                return response()->error('',501,$respon['message']);
            }


            $info=$this->build_jurnal($sysid,$request);
            if ($info['state']==false){
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }

            DB::update("UPDATE t_inventory_booked2 a INNER JOIN
            (SELECT sysid,item_code,SUM(qty*item_cost) as line_cost FROM t_item_price
            WHERE sysid=? AND doc_type='IOS' GROUP BY sysid,item_code) b ON a.sysid=b.sysid AND a.item_code=b.item_code
            SET a.line_cost=ABS(b.line_cost),a.itemcost=ABS(b.line_cost/a.qty_item)
            WHERE a.sysid=?",[$sysid,$sysid]);

            DB::commit();
            $respon=[
                'uuid_rec'=>$req->uuid_rec,
                'message'=>"Simpan data berhasil"
            ];

            return response()->success('Success',$respon);

        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public static function build_item($service_no){
        DB::table('t_workorder_material'
        )->where('service_no',$service_no)
        ->delete();

        DB::insert("
            INSERT INTO t_workorder_material (service_no, item_code, descriptions, mou_inventory, request, approved, used, warehouse_id)
            SELECT ?, item_code, descriptions, mou_inventory,
                SUM(qty_item) AS request,
                SUM(qty_supply) AS approved,
                SUM(qty_supply) AS used,
                a.warehouse_id
            FROM t_inventory_booked1 a
            INNER JOIN t_inventory_booked2 b ON a.sysid = b.sysid
            WHERE a.service_no = ?
            GROUP BY item_code, descriptions, mou_inventory, a.warehouse_id
        ", [$service_no, $service_no]);

        DB::update("
            UPDATE t_workorder_material a
            INNER JOIN t_workorder_service b ON a.service_no = b.doc_number
            SET a.sysid = b.sysid
            WHERE a.service_no = ?
        ", [$service_no]);
    }

    public function print(Request $request)
    {
        $uuid = $request->uuid;

        $header = GoodsRequest1::from('t_inventory_booked1 as a')
            ->selectRaw("
                a.sysid, a.doc_number, a.pool_code, a.warehouse_id, a.ref_date, a.notes,
                b.descriptions as warehouse_name, a.service_no, c.descriptions as pool_name,
                d.line_no, d.item_code, d.descriptions, d.qty_item, d.qty_supply, d.mou_inventory,
                e.user_name, a.vehicle_no, f.police_no,
                IFNULL(g.part_number, '') as part_number, a.update_timestamp
            ")
            ->leftJoin('m_warehouse as b', 'a.warehouse_id', '=', 'b.warehouse_id')
            ->leftJoin('m_pool as c', 'a.pool_code', '=', 'c.pool_code')
            ->leftJoin('t_inventory_booked2 as d', 'a.sysid', '=', 'd.sysid')
            ->leftJoin('o_users as e', 'a.approved_by', '=', 'e.user_id')
            ->leftJoin('m_vehicle as f', 'a.vehicle_no', '=', 'f.vehicle_no')
            ->leftJoin('m_item as g', 'd.item_code', '=', 'g.item_code')
            ->where('a.uuid_rec', $uuid)
            ->get();

        if ($header->isEmpty()) {
            return response()->error('', 404, 'Data not found');
        }

        // Format the reference date
        $header[0]->ref_date = date('d-m-Y', strtotime($header[0]->ref_date));

        // Fetch profile details
        $profile = PagesHelp::Profile();

        // Generate and stream the PDF
        $pdf = PDF::loadView('inventory.WorkorderRequest', [
            'header' => $header,
            'profile' => $profile
        ])->setPaper('A4', 'portrait');

        return $pdf->stream();
    }

    public function getRequest(Request $request)
    {
        $service_no = $request->service_no ?? '-';

        $data = GoodsRequest1::
        selectRaw("
        sysid,doc_number,reference,ref_date,vehicle_no,pool_code,warehouse_id,is_approved,approved_date,
        approved_by,sysid_jurnal,CONCAT(trans_code,'-',trans_series) as voucher,uuid_rec
        ")->where('service_no', $service_no)->get();
        return response()->success('Success',$data);
   }


    public static function build_jurnal($sysid,$request) {
        /* Inventory Cost
             Inventory
         */
        $ret['state']=true;
        $ret['message']='';
        $data=GoodsRequest1::selectRaw('pool_code,reference,doc_number,ref_date,
        sysid_jurnal,trans_code,trans_series,warehouse_id')
        ->where('sysid',$sysid)->first();
        if ($data){
            $pool_code=$data->pool_code;
            $detail=GoodsRequest2::from('t_inventory_booked2 as a')
            ->selectRaw('a.item_code,a.descriptions,ABS(b.qty) as qty,b.item_cost,ABS(b.qty)*b.item_cost as line_cost,d.inv_account,d.cost_account')
            ->join('t_item_price as b', function($join)
                {
                    $join->on('a.sysid', '=', 'b.sysid');
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.doc_type','=',DB::raw("'IOS'"));
                })
            ->leftjoin('m_item as c','a.item_code','=','c.item_code')
            ->leftJoin('m_item_group_account as d', function($join) use($pool_code)
                {
                    $join->on('c.item_group', '=', 'd.item_group');
                    $join->on('d.pool_code','=',DB::raw("'$pool_code'"));
                })
            ->where('a.sysid',$sysid)
            ->get();
            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $series = Journal1::GenerateNumber('IOS',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'PBG',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'7',
                  'notes'=>'Pengeluran barang '.$data->reference
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
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'7',
                  'notes'=>'Pengeluran barang '.$data->reference
                ]);
            }
            /* Cost
                Inventory */
            $line=0;
            $ontransfer=0;
            foreach($detail as $row){
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->cost_account,
                    'line_memo'=>'Pengeluaran '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>$row->line_cost,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->inv_account,
                    'line_memo'=>'Pengeluaran '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->doc_number,
                    'reference2'=>$data->reference,
                    'debit'=>0,
                    'credit'=>$row->line_cost,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                GoodsRequest1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'IOS',
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
    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= GoodsRequest1::from('t_inventory_booked1 as a')
        ->selectRaw("(a.sysid*1000)+b.line_no as _index,a.doc_number,a.ref_date,a.service_no,a.vehicle_no,a.pool_code,a.update_userid,a.update_timestamp,
            CONCAT(a.trans_code,'-',a.trans_series) as voucher,b.item_code,c.part_number,b.descriptions,b.qty_used,b.itemcost,b.line_cost")
        ->leftJoin('t_inventory_booked2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_approved', '=','1');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.service_no', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
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
        $data= GoodsRequest1::from('t_inventory_booked1 as a')
        ->selectRaw("a.doc_number,a.ref_date,a.service_no,a.vehicle_no,a.pool_code,a.update_userid,a.update_timestamp,
            CONCAT(a.trans_code,'-',a.trans_series) as voucher,b.item_code,c.part_number,b.descriptions,b.qty_used,b.itemcost,b.line_cost,
            a.pool_code")
        ->leftJoin('t_inventory_booked2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_approved', '=','1');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PENGELUARAN BARANG BENGKEL');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'No.Permintaan');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'No.Service');
        $sheet->setCellValue('D5', 'No.Body');
        $sheet->setCellValue('E5', 'Kode Item');
        $sheet->setCellValue('F5', 'No. Part');
        $sheet->setCellValue('G5', 'Nama Item/Barang');
        $sheet->setCellValue('H5', 'Jumlah');
        $sheet->setCellValue('I5', 'Harga');
        $sheet->setCellValue('J5', 'Total Harga');
        $sheet->setCellValue('K5', 'User ID');
        $sheet->setCellValue('L5', 'Tgl. Input');
        $sheet->setCellValue('M5', 'Pool');
        $sheet->setCellValue('N5', 'Akunting');
        $sheet->getStyle('A5:N5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->service_no);
            $sheet->setCellValue('D'.$idx, $row->vehicle_no);
            $sheet->setCellValueExplicit('E'.$idx,$row->item_code,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$idx, $row->part_number,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('G'.$idx, $row->descriptions);
            $sheet->setCellValue('H'.$idx, $row->qty_used);
            $sheet->setCellValue('I'.$idx, $row->itemcost);
            $sheet->setCellValue('J'.$idx, $row->line_cost);
            $sheet->setCellValue('K'.$idx, $row->update_userid);
            $sheet->setCellValue('L'.$idx, $row->update_timestamp);
            $sheet->setCellValue('M'.$idx, $row->pool_code);
            $sheet->setCellValue('N'.$idx, $row->voucher);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('L6:L'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('J'.$idx, "=SUM(J6:J$last)");
        $sheet->getStyle('H6:J'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        // Formater
        $sheet->getStyle('A1:N5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'N'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:N'.$idx)->applyFromArray($styleArray);
        foreach(range('C','N') as $columnID) {
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
        $xls="laporan_pengeluaran_bengkel_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

}
