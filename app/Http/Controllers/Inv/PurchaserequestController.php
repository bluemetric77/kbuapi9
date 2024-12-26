<?php

namespace App\Http\Controllers\Inv;

use App\Models\Inventory\PurchaseRequest1;
use App\Models\Inventory\PurchaseRequest2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;
use Illuminate\Support\Str;

class PurchaserequestController extends Controller
{
    public function show(Request $request){
        $filter   = $request->filter;
        $limit    = $request->limit;
        $sorting  = ($request->descending=="true") ? 'desc':'asc';
        $sortBy   = $request->sortBy;

        $pool_code=PagesHelp::Session()->pool_code;
        $date1 = $request->date1;
        $date2 = $request->date2;

        $isapproved = isset($request->approved) ? $request->approved:'0';
        $open = isset($request->open) ? $request->open:'0';

        $data= PurchaseRequest1::selectRaw("sysid,pool_code,doc_number,warehouse_id,ref_date,request_status,descriptions,is_draft,is_posted,
          is_purchase_order,is_cancel,is_closed,priority,user_posted,posted_date,user_canceled,canceled_date,uuid_rec");

        if ($isapproved=='1') {
            $data=$data
            ->where('request_status','<>','Complete')
            ->where('is_posted','1')
            ->where('is_cancel','0');
        } else if ($open=='1') {
            $data=$data
            ->where('pool_code',$pool_code)
            ->where('request_status','<>','Complete')
            ->where('is_cancel','0');
        } else {
            $data=$data
            ->where('pool_code',$pool_code)
            ->where('ref_date','>=',$date1)
            ->where('ref_date','<=',$date2);
        }

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('descriptions','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $uuid_rec=isset($request->uuid_rec) ? $request->uuid_rec :'';
        $PR=PurchaseRequest1::where('uuid_rec',$uuid_rec)->first();

        if (!($PR)) {
            return response()->error('',501,'Data tidak ditemukan');
        }

        if ($PR->is_posted=='1') {
            return response()->error('',201,'permintaan pembelian sudah diposting');
        } else if ($PR->is_cancel=='1') {
            return response()->error('',202,'permintaan pembelian sudah dibatalkan');
        }

        DB::beginTransaction();
        try {
            $sysid=$PR->sysid;
            PurchaseRequest1::where('sysid',$sysid)
            ->update([
                'is_cancel'=>1,
                'request_status'=>'Canceled',
                'user_canceled'=>PagesHelp::Session()->user_id,
                'canceled_date'=>now()
            ]);
            PurchaseRequest2::where('sysid',$sysid)
            ->update(['item_status'=>"Canceled",
                    'line_cancel'=>DB::raw("qty_request")]);
            DB::commit();
            return response()->success('Success','permintaan pembelian berhasil dibatalkan');
        } catch(Exception $e){
            DB::rollback();
            return response()->error('',501,$e);
        }

    }
    public function unposting(Request $request){
        $uuid_rec=isset($request->uuid_rec) ? $request->uuid_rec :'';

        $PR=PurchaseRequest1::where('uuid_rec',$uuid_rec)->first();
        if (!($PR)) {
            return response()->error('',501,'Data tidak ditemukan');
        }

        if ($PR->is_cancel=='1') {
            return response()->error('',202,'permintaan pembelian sudah dibatalkan');
        } else if ($PR->is_posted=='0') {
            return response()->error('',201,'permintaan pembelian belum diposting');
        } else if ($PR->is_purchase_order=='1') {
            return response()->error('',203,'permintaan pembelian sudah dibuatkan PO');
        }

        DB::beginTransaction();
        try {
            $sysid=$PR->sysid;
            PurchaseRequest1::where('sysid',$sysid)
            ->update([
                'is_posted'=>'0',
                'is_draft'=>'1',
                'user_posted'=>'',
                'posted_date'=>null
            ]);
            DB::commit();
            return response()->success('Success','permintaan pembelian berhasil diunposting');
        } catch(Exception $e){
            DB::rollback();
            return response()->error('',501,$e);
        }

    }

    public function get(Request $request){
        $uuid_rec=isset($request->uuid_rec) ? $request->uuid_rec :'';
        $header=PurchaseRequest1::where('uuid_rec',$uuid_rec)->first();

        $is_posted = false;
        $is_cancel = false;
        $message   = '';

        if (!($header)){
            return response()->error('',501,'Data Tidak ditemukan');
        }

        if ($header->is_posted=='1') {
            $message   = 'permintaan pembelian sudah diposting';
            $is_posted = true;
        } else if ($header->is_cancel=='1') {
            $message   = 'permintaan pembelian sudah dibatalkan';
            $is_cancel = true;
        }
        $data=[
            'header'=>$header,
            'detail'=>PurchaseRequest2::where('sysid',$header->sysid)->get(),
            'is_posted'=>$is_posted,
            'is_cancel'=>$is_cancel,
            'message'=>$message
        ];
        return response()->success('Success',$data);
    }

    public function post(Request $request){
        $data= $request->json()->all();
        $header=$data['header'];
        $detail=$data['detail'];

        $is_posted = isset($data['is_posted']) ? boolval($data['is_posted']) : false;

        $session = PagesHelp::Session();

        $header['pool_code']       = $session->pool_code;
        $header['warehouse_id']    = $session->warehouse_code;

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'warehouse_id'=>'bail|required',
            'priority'=>'required'
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'warehouse_id.required'=>'Gudang harus diisi',
            'priority.required'=>'Prioritas harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.qty_request'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_request.min'=>'Jumlah permintaan harus diisi/lebih besar dari NOL',
            '*.item_code.distinct'=>'Kode barang :input terduplikasi (terinput lebih dari 1)',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $pr=PurchaseRequest1::where('uuid_rec',isset($header['uuid_rec'])?$header['uuid_rec']:'')->first();
            if (!($pr)) {
                $pr = new PurchaseRequest1();
                $pr->uuid_rec   = Str::uuid();
                $pr->doc_number = PurchaseRequest1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $pr->is_draft   = '1';
                $pr->is_posted  = '0';
                $pr->is_purchase_order  = '0';
                $pr->is_cancel  = '0';
                $pr->is_closed  = '0';
                $pr->request_status  = 'Open';
                $pr->pool_code       = $header['pool_code'];
                $pr->warehouse_id    = $header['warehouse_id'];
            } else {
               PurchaseRequest2::where('sysid',$pr->sysid)->delete();
            }
            $pr->ref_date         = $header['ref_date'];
            $pr->descriptions     = $header['descriptions'];
            $pr->priority         = $header['priority'];
            if ($is_posted==false){
                $pr->update_userid    = $session->user_id;
                $pr->update_timestamp = Date('Y-m-d H:i:s');
            }
            $pr->save();
            $sysid =$pr->sysid;

            foreach($detail as $rec) {
                PurchaseRequest2::insert([
                    'sysid'       => $pr->sysid,
                    'item_status' => 'On Request',
                    'line_no'     => $rec['line_no'],
                    'item_code'   => $rec['item_code'],
                    'descriptions'=> $rec['descriptions'],
                    'mou_purchase'=> $rec['mou_purchase'],
                    'qty_request' => $rec['qty_request'],
                    'mou_inventory'=>$rec['mou_inventory'],
                    'po_id'       =>-1,
                    'po_number'   => '',
                    'invoice_id'  => '',
                    'invoice_no'  => '',
                    'current_stock'=>0,
                    'notes'=>$rec['notes'],
                    'purchase_note'=>$rec['notes'],
                    'warehouse_id'=>$pr->warehouse_id
                ]);
            }

            if ($is_posted){
                PurchaseRequest1::where('sysid',$sysid)
                ->update([
                    'is_posted'=>1,
                    'is_draft'=>0,
                    'user_posted'=>$session->user_id,
                    'posted_date'=>now()
                ]);
            } else {
                DB::update(
                    "UPDATE t_purchase_request2 a
                    INNER JOIN m_item_stock b ON a.item_code = b.item_code AND a.warehouse_id = b.warehouse_id
                    SET a.current_stock = b.on_hand
                    WHERE a.sysid =?",[$sysid]
                );
            }
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function print(Request $request){
        $uuid_rec=$request->uuid_rec ?? '';

        $header=PurchaseRequest1::from('t_purchase_request1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.pool_code,a.warehouse_id,a.descriptions,
        b.descriptions as warehouse_name,c.descriptions as pool_name,user_posted,posted_date,
        d.user_name,a.priority,a.update_timestamp")
        ->leftJoin('m_warehouse as b','a.warehouse_id','=','b.warehouse_id')
        ->leftJoin('m_pool as c','a.pool_code','=','c.pool_code')
        ->leftJoin('o_users as d','a.update_userid','=','d.user_id')
        ->where('a.uuid_rec',$uuid_rec)->first();

        if (!($header)){
            return response()->error('',501,'Data Tidak ditemukan');
        }
        $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
        $header->update_timestamp=date_format(date_create($header->update_timestamp),'d-m-Y h:i:s');

        $warehouse = $header->warehouse_id;


        $detail=PurchaseRequest2::from('t_purchase_request2 as a')
        ->selectRaw("a.line_no,a.item_code,b.part_number,a.descriptions,a.mou_purchase,
                    a.qty_request,a.mou_inventory,a.convertion,a.current_stock")
        ->leftjoin('m_item as b','a.item_code','=','b.item_code')
        ->where('a.sysid',$header->sysid)->get();

        $profile=PagesHelp::Profile();

        $pdf = PDF::loadView('inventory.Purchaserequest',
        [
            'header'=>$header,
            'detail'=>$detail,
            'profile'=>$profile
        ])->setPaper('a4','potrait');
        return $pdf->stream();
    }

}
