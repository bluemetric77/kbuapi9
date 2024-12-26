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

class GoodsRequestReturnController extends Controller
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
        a.pool_code,
        a.warehouse_id,
        a.sysid_ref,
        a.doc_reference,
        a.is_approved,
        a.approved_date,
        a.approved_by,
        b.police_no,
        a.is_autoclosed,
        a.uuid_rec,
        a.pool_code,
        a.warehouse_id,
        a.sysid_jurnal,
        CONCAT(a.trans_code,'-',a.trans_series) as voucher")
        ->leftjoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
        ->where('a.ref_date','>=',$date1)
        ->where('a.ref_date','<=',$date2)
        ->where('a.pool_code',$pool_code)
        ->where('is_credit_note','1');

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
            uuid_rec,
            sysid_ref,
            doc_reference
        ")->where('uuid_rec',$uuid)->first();

        $detail=GoodsRequest2::from('t_inventory_booked2 as a')
        ->selectRaw("
            a.sysid,
            a.line_no,
            a.item_code,
            b.part_number,
            a.descriptions,
            ABS(a.qty_item) as qty_item,
            ABS(a.qty_supply) as qty_supply,
            a.mou_inventory,
            a.notes,
            a.itemcost,
            a.line_cost"
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
            /*if ($service->is_closed=='1'){
                return response()->error('',400,"Workorder tersebut sudah selesai,Permintaan barang tidak bisa dilakukan");
            }*/
        } else {
            return response()->error('',400,"Workorder tersebut tidak ditemukan");
        }

        DB::beginTransaction();
        try{
            $req=GoodsRequest1::where('uuid_rec',$header['uuid_rec'] ?? '')->first();
            if (!$req){
                $req = new GoodsRequest1();
                $req->doc_number  = GoodsRequest1::GenerateRetur($header['pool_code'],$header['ref_date']);
                $req->uuid_rec    = Str::uuid();
                $opr = 'inserted';
            } else {
                GoodsRequest2::where('sysid',$req->sysid)->delete();
                $opr = 'updated';
            }
            $req->fill([
                'reference'   => $header['reference'] ?? '',
                'ref_date'    => $header['ref_date'],
                'service_no'  => $header['service_no'],
                'vehicle_no'  => $header['vehicle_no'],
                'pool_code'   => $header['pool_code'],
                'warehouse_id'=> $header['warehouse_id'],
                'is_approved'  => 1,
                'is_autoclosed'=> 0,
                'approved_by'  => 'SYSTEM',
                'sysid_jurnal' => -1,
                'trans_code'   => '',
                'trans_series' => '',
                'sysid_ref'    => $header['sysid_ref'],
                'doc_reference'=> $header['doc_reference'],
                'update_userid'=>$session->user_id,
                'is_credit_note'=>'1',
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
                    "qty_item"  => - $line['qty_item'],
                    "qty_supply"=> - $line['qty_supply'],
                    "notes"     => $line['notes'] ?? '',
                    "qty_used"  => - $line['qty_supply'],
                    "itemcost"  => 0,
                    "line_cost" => 0,
                    "warehouse_id"=> $req->warehouse_id,
                    'sysid_ref'=>$req->sysid_ref
                ]);
            }
            $this->build_item($header['service_no']);

            $respon=Inventory::ItemCard($sysid,'IRS',$opr,false,false,true);

            if ($respon['success']==false){
                DB::rollback();
                return response()->error('',501,$respon['message']);
            }

            $info=$this->build_jurnal($sysid,$request);

            if ($info['state']==false){
                DB::rollback();
                return response()->error('', 501, $info['message']);
            }

            DB::update(
                "UPDATE t_inventory_booked2 a INNER JOIN
                (SELECT sysid,item_code,SUM(qty*item_cost) as line_cost FROM t_item_price
                WHERE sysid=? AND doc_type='IRS' GROUP BY sysid,item_code) b ON a.sysid=b.sysid AND a.item_code=b.item_code
                SET
                a.line_cost= - b.line_cost,
                a.itemcost=ABS(b.line_cost/a.qty_item)
                WHERE
                a.sysid=?",
                [$sysid,$sysid]
            );

            DB::update(
                "UPDATE t_inventory_booked2 a
                INNER JOIN t_inventory_booked2 b ON a.sysid=b.sysid_ref AND a.item_code=b.item_code
                SET a.qty_return=ABS(b.qty_supply)
                WHERE a.sysid=?",
                [$req->sysid_ref]
            );

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
                d.line_no, d.item_code, d.descriptions, ABS(d.qty_item) as qty_item,
                ABS(d.qty_supply) as qty_supply, d.mou_inventory,
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
        $pdf = PDF::loadView('inventory.WorkorderRequest-Retur', [
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
                    $join->on('b.doc_type','=',DB::raw("'IRS'"));
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
                $series = Journal1::GenerateNumber('IRS',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->doc_number,
                  'reference2'=>$data->reference,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'RBG',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'7',
                  'notes'=>'Retur Pengeluran barang '.$data->reference
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
                  'notes'=>'Retur Pengeluran barang '.$data->reference
                ]);
            }
            /* Inventory
                Cost */
            $line=0;
            $ontransfer=0;
            foreach($detail as $row){
                $line++;
                Journal2::insert([
                    'sysid'     =>$sysid_jurnal,
                    'line_no'   =>$line,
                    'no_account'=>$row->inv_account,
                    'line_memo' =>'Retur '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=> $data->doc_number,
                    'reference2'=> $data->reference,
                    'debit'     => $row->line_cost,
                    'credit'    => 0,
                    'project'   => PagesHelp::Project($data->pool_code)
                ]);
                $line++;
                Journal2::insert([
                    'sysid'     => $sysid_jurnal,
                    'line_no'   => $line,
                    'no_account'=> $row->cost_account,
                    'line_memo' =>'Retur '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                 ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=> $data->doc_number,
                    'reference2'=> $data->reference,
                    'debit'     => 0,
                    'credit'    => $row->line_cost,
                    'project'   => PagesHelp::Project($data->pool_code)
                ]);
            }
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                GoodsRequest1::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'IRS',
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

    public function showWOR(Request $request){
        $filter     = $request->filter;
        $limit      = $request->limit;
        $sorting    = ($request->descending=="true") ?'desc':'asc';
        $sortBy     = $request->sortBy ?? 'ref_date';

        $pool_code  =PagesHelp::Session()->pool_code;

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
        CONCAT(a.trans_code,'-',a.trans_series) as voucher")
        ->leftjoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
        ->where('a.pool_code',$pool_code)
        ->where('is_credit_note','0')
        ->where('is_approved','1');

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

    public function showWORDetail(Request $request){
        $sysid = $request->sysid ?? -1;
        $data= GoodsRequest1::from('t_inventory_booked1 as a')
        ->selectRaw("b.sysid,
            b.line_no,
            b.item_code,
            b.descriptions,
            b.mou_inventory,
            b.qty_item,
            b.qty_supply-b.qty_return as qty_supply,
            b.notes,
            b.qty_used,
            b.itemcost,
            b.line_cost,
            c.part_number")
        ->leftjoin('t_inventory_booked2 as b','a.sysid','=','b.sysid')
        ->leftjoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.sysid',$sysid)
        ->orderBy('b.line_no','asc')
        ->get();
        return response()->success('Success',$data);
    }
}
