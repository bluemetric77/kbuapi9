<?php

namespace App\Http\Controllers\Inv;

use App\Models\Inventory\StockRequest1;
use App\Models\Inventory\StockRequest2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use PDF;

class StockRequestController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sortir = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy = $request->sortBy;

        $pool_code=PagesHelp::Session()->pool_code;
        $unproccess=isset($request->proccess) ? $request->proccess :'1';
        $date1 = $request->date1;
        $date2 = $request->date2;

        $data= StockRequest1::selectRaw('sysid,doc_number,pool_code,ref_document,ref_date,
            warehouse_id,warehouse_request,service_no,notes,created_by,created_date,is_authorize,
            authorize_date,authorize_by,is_processed,priority,is_canceled,canceled_date,canceled_by,
            uuid_rec')
           ->where('pool_code',$pool_code)
           ->where('is_transferstock','1');

        if ($unproccess=='0') {
            $data=$data->where('is_processed',0)
                ->where('is_canceled',0);
        } else {
            $data=$data
            ->where('ref_date','>=',$date1)
            ->where('ref_date','<=',$date2);
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('notes','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sortir)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function get(Request $request){
        $uuid=$request->uuid ?? '';
        $header=StockRequest1::
        selectRaw("
            sysid,
            doc_number,
            pool_code,
            ref_document,
            ref_date,
            notes,
            warehouse_id,
            warehouse_request,
            is_authorize,
            authorize_date,
            authorize_by,
            is_processed,
            priority,
            is_canceled,
            uuid_rec")
        ->where('uuid_rec',$uuid)->first();
        if (!$header){
            return response()->error('',501,'Data Tidak ditemukan');
        }
        if ($header->is_processed=='1') {
            $message ='Permintaan transfer barang sudah diproses';
        } else if ($header->is_canceled=='1') {
            $message ='Permintaan transfer barang sudah dibatalkan';
        }
        $detail=StockRequest2::from('t_inventory_request2 as a')
        ->select('a.sysid','a.line_no','a.item_code','a.qty_request','a.qty_stock','a.descriptions','a.qty_supply',
            'a.qty_cancel','a.mou_inventory','b.part_number')
        ->leftJoin('m_item as b','a.item_code','=','b.item_code')
        ->where('sysid',$header->sysid)->get();

        $data =[
            'header'=>$header,
            'detail'=>$detail,
            'message'=>$message ?? '',
        ];
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $uuid=$request->uuid ?? '';
        $header=StockRequest1::where('uuid_rec',$uuid)->first();
        if (!$header){
            return response()->error('',501,'Data Tidak ditemukan');
        }
        if ($data->is_processed=='1') {
            return response()->error('',201,'Permintaan transfer barang sudah diproses');
        } else if ($data->is_canceled=='1') {
            return response()->error('',202,'Permintaan transfer barang sudah dibatalkan');
        } else {
            DB::beginTransaction();
            try {
                $sysid=$header->sysid;
                StockRequest1::where('sysid',$sysid)
                ->update([
                    'is_canceled'=>1,
                    'canceled_by'=>PagesHelp::UserID($request),
                    'canceled_date'=>now()
                ]);
                DB::commit();
                return response()->success('Success','Permintaan stock barang berhasil dibatalkan');
            } catch(Exception $e){
                DB::rollback();
                return response()->error('',501,$e);
            }
        }
    }
    public function post(Request $request){
        $data   = $request->json()->all();
        $header = $data['header'];
        $detail = $data['detail'];

        $session = PagesHelp::Session();

        $header['pool_code']        = $session->pool_code;
        $header['warehouse_request']= $session->warehouse_code;

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'warehouse_id'=>'bail|required',
            'warehouse_request'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'warehouse_id.required'=>'Gudang tujuan harus diisi',
            'warehouse_request.required'=>'Gudang yang minta harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        if ($header['warehouse_request']==$header['warehouse_id']){
            return response()->error('',501,'Gudang tujuan permintaan tidak boleh sama');
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.qty_request'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty_request.min'=>'Jumlah permintaan harus diisi/lebih besar dari NOL',
            '*.item_code.distinct'=>'Kode harga :input terduplikasi (terinput lebih dari 1)',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $req1=StockRequest1::where('uuid_rec',$header['uuid_rec']??'')->first();
            if (!$req1) {
                $req1 = new StockRequest1();
                $req1->uuid_rec   = Str::uuid();
                $req1->doc_number = StockRequest1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $req1->is_processed  ='0';
                $req1->is_closed     ='0';
                $req1->is_authorize  ='0';
                $req1->is_transferstock='1';
                $req1->created_by=$session->user_id;
                $req1->created_date=Date('Y-m-d H:i:s');
            } else {
                if ($req1->is_canceled=='1') {
                    return response()->error('',202,'Permintaan stock sudah dibatalkan');
                } else if ($req1->is_processed=='1') {
                    return response()->error('',201,'permintaan sudah di proses');
                }
               StockRequest2::where('sysid',$req1->sysid)->delete();
            }

            $req1->fill([
                'ref_date'=>$header['ref_date'],
                'ref_document'=>$header['ref_document'] ?? '',
                'warehouse_id'=>$header['warehouse_id'],
                'warehouse_request'=>$header['warehouse_request'],
                'notes'=>$header['notes'] ?? '',
                'priority'=>$header['priority'],
                'update_userid'=>$session->user_id,
                'update_timestamp'=>Date('Y-m-d H:i:s')
            ]);
            $req1->save();

            foreach($detail as $line) {
                StockRequest2::insert([
                    'sysid'        => $req1->sysid,
                    'line_no'      => $line['line_no'],
                    'item_code'    => $line['item_code'],
                    'descriptions' => $line['descriptions'],
                    'mou_inventory'=> $line['mou_inventory'],
                    'qty_request'  => $line['qty_request'],
                    'qty_stock'    => 0,
                    'warehouse_id' => $req1->warehouse_id
                ]);
            }
            DB::update(
                "UPDATE t_inventory_request2 a
                INNER JOIN m_item_stock b ON a.item_code = b.item_code AND a.warehouse_id = b.warehouse_id
                SET a.qty_stock = b.on_hand
                WHERE a.sysid =?",[$req1->sysid]
            );
            DB::commit();
            $respon=[
                'uuid'=>$req1->uuid_rec,
                'message'=>'Simpan data Berhasil'
            ];
            return response()->success('Success',$respon);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function print(Request $request){
        $uuid=$request->uuid ?? '';

        $header=StockRequest1::from('t_inventory_request1 as a')
        ->selectRaw("a.sysid,a.doc_number,a.pool_code,a.warehouse_id,a.ref_date,a.notes,b.descriptions as warehouse_name,
                    c.descriptions as pool_name,d.user_name,a.update_timestamp")
        ->leftJoin('m_warehouse as b','a.warehouse_id','=','b.warehouse_id')
        ->leftJoin('m_pool as c','a.pool_code','=','c.pool_code')
        ->leftJoin('o_users as d','a.update_userid','=','d.user_id')
        ->where('a.uuid_rec',$uuid)->first();

        if (!$header) {
            return response()->error('',501,'Data Tidak ditemukan');
        }

        $header->ref_date         = date_format(date_create($header->ref_date),'d-m-Y');
        $header->update_timestamp = date_format(date_create($header->update_timestamp),'d-m-Y H:i:s');
        $detail =StockRequest2::from("t_inventory_request2 as a")
        ->selectRaw("a.line_no,a.item_code,a.descriptions,a.qty_request,a.qty_stock,a.mou_inventory,b.part_number")
        ->join('m_item as b','a.item_code','=','b.item_code')
        ->where('a.sysid',$header->sysid)
        ->get();

        $profile=PagesHelp::Profile();
        $pdf = PDF::loadView('inventory.Stockrequest',
        ['header'=>$header,
         'detail'=>$detail,
         'profile'=>$profile]
        )->setPaper('A4','potriat');
        return $pdf->stream();
    }
}
