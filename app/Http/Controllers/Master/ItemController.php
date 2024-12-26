<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Items;
use App\Models\Inventory\ItemMutation;
use App\Models\Inventory\ItemMutationYearly;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ItemController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Items::from('m_item as a')
        ->selectRaw(" a.item_code,a.part_number,a.descriptions,a.mou_purchase,a.convertion,a.is_stock_record,a.is_calculate_order,
           a.is_hold,a.is_active,a.mou_warehouse,a.last_purchase,a.purchase_price,a.purchase_discount,a.on_hand,
           b.descriptions AS group_name,a.is_active")
        ->leftjoin('m_item_group as b','a.item_group','=','b.item_group');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.item_code','like',$filter)
               ->orwhere('a.part_number','like',$filter)
               ->orwhere('a.descriptions','like',$filter);
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

    public function destroy(Request $request){
        $itemcode=$request->itemcode;
        $data=Items::where('item_code',$itemcode)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                PagesHelp::write_log($request,-1,$data->item_code,'Delete recods ['.$data->item_code.'-'.$data->descriptions.']');
                Items::where('item_code',$itemcode)->delete();
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch (Exception $e) {
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }

    public function get(Request $request){
        $itemcode=$request->itemcode;
        $data= Items::from('m_item as a')
        ->selectRaw(" a.item_code,a.part_number,a.descriptions,a.mou_purchase,a.convertion,a.item_group,
           a.mou_warehouse,a.last_purchase,a.purchase_price,a.purchase_discount,a.on_hand,b.descriptions AS group_name,
           a.is_stock_record,a.is_calculate_order,a.is_hold,a.is_active")
        ->leftjoin('m_item_group as b','a.item_group','=','b.item_group')
        ->where("a.item_code",$itemcode)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,
        [
            'item_code'=>'bail|required',
            'part_number'=>'bail|required',
            'descriptions'=>'bail|required',
            'mou_purchase'=>'bail|required',
            'convertion'=>'bail|required',
            'mou_warehouse'=>'bail|required',
            'item_group'=>'bail|required',
        ],[
            'item_code.required'=>'Kode inventory harus diisi',
            'part_number.required'=>'Nomor part harus  diisi',
            'descriptions.required'=>'Nama inventory harus diisi',
            'mou_purchase.required'=>'Satuan beli harus diisi',
            'convertion.required'=>'Konvesi stock harus diisi',
            'mou_warehouse.required'=>'Satuan simpan harus diisi',
            'item_group.required'=>'Grup inventory harus diisi',
        ]);
        if ($validator->fails()) {
            return response()->error('',501,$validator->errors()->first());
        }
        unset($rec['group_name']);
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Items::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Items::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['item_code'],'Add/Update recods ['.$rec['item_code'].'-'.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getItems(Request $request){
        $pool=Items::select('item_code',DB::raw('CONCAT(item_code, " - ", descriptions," (",part_number,")") AS descriptions'))
             ->where('is_active','1')
             ->get();
        return response()->success('Success',$pool);
    }

    public function lookup(Request $request){
        $filter     = $request->filter;
        $limit      = $request->limit;
        $sorting    = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy     = isset($request->sortBy) ? $request->sortBy :'';

        $warehouse  = isset($request->warehouse) ? $request->warehouse :'0';
        $warehouse_code  = isset($request->warehouseid) ? $request->warehouseid :'';

        $warehouse_id    = ($warehouse=='1') ? Pageshelp::Session()->warehouse_code :'';
        $warehouse_id    = ($warehouse_code!='') ? $warehouse_code : $warehouse_id;


        if (!($warehouse_id=="")){
            $data=Items::from('m_item as a')
            ->select('a.item_code','a.part_number','a.descriptions','a.mou_warehouse','a.mou_purchase',
            'a.convertion',DB::raw('IFNULL(b.on_hand,0) as on_hand'))
            ->leftJoin('m_item_stock as b',function($join) use($warehouse_id){
                $join->on('a.item_code','=','b.item_code');
                $join->on('b.warehouse_id','=',DB::raw("'$warehouse_id'"));
            });
        } else {
            $data= Items::from('m_item as a')
                ->select('a.item_code','a.part_number','a.descriptions','a.mou_warehouse','a.mou_purchase','a.convertion');
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('a.item_code','like',$filter)
                    ->orwhere('a.part_number','like',$filter)
                    ->orwhere('a.descriptions','like',$filter);
            });
        }
        $data=$data->where('a.is_active',1)
        ->where('item_group','<>','400');

        $data=$data->orderBy('a.'.$sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function joblookup(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy =isset($request->sortBy) ? $request->sortBy :'';
        $data= Items::from('m_item as a')
            ->select('a.item_code','a.descriptions');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('a.item_code','like',$filter)
                    ->orwhere('a.descriptions','like',$filter);
            });
        }
        $data=$data->where('a.is_active',1)
        ->where('item_group','400');
        if ($sortBy<>''){
            if ($descending) {
                $data=$data->orderBy('a.'.$sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy('a.'.$sortBy,'asc')->paginate($limit);
            }
        }
        return response()->success('Success',$data);
    }

    public function price(Request $request){
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy =isset($request->sortBy) ? $request->sortBy :'';
        $warehouse_id =isset($request->warehouse_id) ? $request->warehouse_id :'';
        $item_code =isset($request->item_code) ? $request->item_code :'';
        $data= DB::table('m_item_stock_price')
            ->select('price_code','price_date','price','on_hand')
            ->where('item_code',$item_code)
            ->where('warehouse_id',$warehouse_id)
            ->where('on_hand','<>','0');
        if ($sortBy<>''){
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        }
        return response()->success('Success',$data);
    }

    public function stock(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = "a.".$request->sortBy;
        $poolcode=PagesHelp::PoolCode($request);
        //$warehouse=Pageshelp::Warehouse($poolcode);
        $warehouse=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $data= Items::from('m_item as a')
        ->selectRaw("a.item_code,a.descriptions,a.mou_warehouse,IFNULL(b.on_hand,0) as on_hand,IFNULL(b.price,0) as price,b.price_code,
            a.part_number,IFNULL(c.maximum_stock,0) as maximum_stock,IFNULL(c.minimum_stock,0) as minimum_stock,b.last_activity,
            c.stock_location,a.last_purchase,IFNULL(b.on_hand,0)*IFNULL(b.price,0) as net_price,
            CONCAT(a.item_code,IFNULL(b.price_code,'')) as key_item")
        ->leftJoin('m_item_stock_price as b', function($join) use ($warehouse)
            {
                $join->on('a.item_code', '=', 'b.item_code')
                ->on('b.warehouse_id','=',DB::raw("'".$warehouse."'"))
                ->on(DB::raw('IFNULL(b.on_hand,0)'),'>',DB::raw('0'));
            })
        ->leftJoin('m_item_stock as c', function($join)
            {
                $join->on('b.item_code', '=', 'c.item_code')
                ->on('b.warehouse_id', '=', 'c.warehouse_id');
            })
        ->where('a.is_active',1);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.item_code','like',$filter)
               ->orwhere('a.part_number','like',$filter)
               ->orwhere('a.descriptions','like',$filter);
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

    public function stockcard(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = "a.".$request->sortBy;
        $poolcode=PagesHelp::PoolCode($request);
        $warehouse=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $month=isset($request->month) ? $request->month:1;
        $year=isset($request->year) ? $request->year:1899;
        $start_year=date_create($year."-01-01");
        $start_date=date_create($year."-".$month."-01");
        if ($month==12){
            $end_date=date_create($year."-12-31");
        } else {
            $month=$month+1;
            $end_date=date_create($year."-".$month."-01");
            $end_date->modify("-1 day");
        }

        $data= Items::from('m_item as a')
        ->selectRaw("a.item_code,a.part_number,a.descriptions,a.mou_warehouse,
        0.0000 AS qty_start,0.0000 AS qty_in,0.0000 AS qty_out,
        0.0000 AS qty_opname,0.0000 AS qty_last,0.00 AS price,0.00 AS net_price")
        ->where('a.is_active',1);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.item_code','like',$filter)
               ->orwhere('a.part_number','like',$filter)
               ->orwhere('a.descriptions','like',$filter);
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
        $i=-1;
        foreach($data as $row){
            ++$i;
            $data[$i]["qty_start"]=(float)$data[$i]["qty_start"];
            $data[$i]["qty_in"]=(float)$data[$i]["qty_in"];
            $data[$i]["qty_out"]=(float)$data[$i]["qty_out"];
            $data[$i]["qty_opname"]=(float)$data[$i]["qty_opname"];
            $data[$i]["qty_last"]=(float)$data[$i]["qty_last"];
            $data[$i]["price"]=(float)$data[$i]["price"];
            $data[$i]["net_price"]=(float)$data[$i]["net_price"];
            /*Get from yearly stock*/
            $begbal=DB::table('t_item_mutation_yearly')
            ->selectRaw('item_code,SUM(begining_balance) AS begining_balance,SUM(stock_value) AS stock_value')
            ->where('item_code',$row['item_code'])
            ->where('warehouse_id',$warehouse)
            ->where('year_period',$year)
            ->groupBy('item_code')
            ->first();
            if ($begbal){
                $data[$i]["net_price"]=(float)$begbal->stock_value;
                $data[$i]["qty_start"]=(float)$data[$i]["qty_start"] + (float) $begbal->begining_balance;
            }

            /*Get begining stock from daily mutation*/
            $daily=DB::table('t_item_mutation')
            ->selectRaw('item_code,SUM((IFNULL(qty_in,0)-IFNULL(qty_out,0))+IFNULL(qty_adjustment,0)) as mutation,SUM(total_cost) AS total_cost')
            ->where('item_code',$row['item_code'])
            ->where('warehouse_id',$warehouse)
            ->where('ref_date','>=',$start_year)
            ->where('ref_date','<',$start_date)
            ->where('is_deleted','0')
            ->groupBy('item_code')
            ->first();
            if ($daily){
                $data[$i]["qty_start"]=$data[$i]["qty_start"] + (float) $daily->mutation;
                $data[$i]["net_price"]=$data[$i]["net_price"] + (float) $daily->total_cost;
            }
            /*Get from daily stock*/
            $line=DB::table('t_item_mutation')
            ->selectRaw('item_code,SUM(IFNULL(qty_in,0)) AS qty_in,SUM(IFNULL(qty_out,0)) AS qty_out,SUM(IFNULL(qty_adjustment,0)) AS qty_opname,SUM(total_cost) AS total_cost')
            ->where('item_code',$row['item_code'])
            ->where('warehouse_id',$warehouse)
            ->where('ref_date','>=',$start_date)
            ->where('ref_date','<=',$end_date)
            ->where('is_deleted','0')
            ->groupBy('item_code')
            ->first();
            if ($line){
                $data[$i]["qty_in"]=(float) $line->qty_in;
                $data[$i]["qty_out"]=(float) $line->qty_out;
                $data[$i]["qty_opname"]=(float) $line->qty_opname;
                $data[$i]["net_price"]=$data[$i]["net_price"] + (float) $line->total_cost;
            }
            $data[$i]["qty_last"] = ($data[$i]["qty_start"]+$data[$i]["qty_in"])-$data[$i]["qty_out"];
            $data[$i]["qty_last"] = $data[$i]["qty_last"]+$data[$i]["qty_opname"];
            if ($data[$i]["qty_last"]>0){
                $data[$i]["price"] = $data[$i]["net_price"] /$data[$i]["qty_last"];
            } else {
                $data[$i]["price"] = 0;
                $data[$i]["net_price"] = 0;
            }
        }
        return response()->success('Success',$data);
    }

    public function card_detail(Request $request){
        $item_code=isset($request->item_code) ? $request->item_code:'';
        $poolcode=PagesHelp::PoolCode($request);
        //$warehouse=Pageshelp::Warehouse($poolcode);
        $warehouse=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $month=isset($request->month) ? $request->month:1;
        $year=isset($request->year) ? $request->year:1899;
        $start_year=date_create($year."-01-01");
        $start_date=date_create($year."-".$month."-01");
        if ($month==12){
            $end_date=date_create($year."-12-31");
        } else {
            $month=$month+1;
            $end_date=date_create($year."-".$month."-01");
            $end_date->modify("-1 day");
        }
        $data=DB::table('t_item_mutation')
            ->selectRaw('CONCAT(sysid,doc_type,line_no) AS key_line,sysid,doc_type,doc_number,
                line_notes,qty_in as qty_start,qty_in,
                qty_out,qty_adjustment as qty_opname,qty_out as qty_last,inventory_cost,total_cost,total_cost as accumulation,ref_date')
            ->where('item_code','=',$item_code)
            ->where('ref_date','>=',$start_date)
            ->where('ref_date','<=',$end_date)
            ->where('warehouse_id',$warehouse)
            ->where('is_deleted','0')
            ->orderBy('ref_date','asc')
            ->orderBy('posting_date','asc')
            ->get();
        /*Get from yearly stock*/
        $saldo=0;
        $inventory_cost=0;
        $total_cost=0;
        $begbal=DB::table('t_item_mutation_yearly')
            ->selectRaw('item_code,SUM(begining_balance) AS begining_balance,SUM(stock_value) AS stock_value')
            ->where('item_code',$item_code)
            ->where('warehouse_id',$warehouse)
            ->where('year_period',$year)
            ->groupBy('item_code')
            ->first();
        if ($begbal){
            $total_cost=(float)$begbal->stock_value;
            $saldo=(float) $begbal->begining_balance;
        }

        /*Get Begining Balance from daily stock*/
        $daily=DB::table('t_item_mutation')
            ->selectRaw('item_code,SUM((IFNULL(qty_in,0)-IFNULL(qty_out,0))+IFNULL(qty_adjustment,0)) as mutation,SUM(total_cost) AS total_cost')
            ->where('item_code',$item_code)
            ->where('warehouse_id',$warehouse)
            ->where('ref_date','>=',$start_year)
            ->where('ref_date','<',$start_date)
            ->where('is_deleted','0')
            ->groupBy('item_code')
            ->first();
        if ($daily){
            $total_cost=$total_cost+(float) $daily->total_cost;
            $saldo=$saldo + (float) $daily->mutation;
        }

        $firstline['key_line']='-1';
        $firstline['sysid']='-1';
        $firstline['ref_date']=date_format($start_date->modify('-1 day'),"Y-m-d");
        $firstline['doc_number']='SLDAWAL';
        $firstline['line_notes']='Saldo sampai tanggal '.date_format($start_date->modify('-1 day'),"d-m-Y");
        $firstline['qty_start']=0;
        $firstline['qty_in']=0;
        $firstline['qty_out']=0;
        $firstline['qty_opname']=0;
        $firstline['qty_last']=$saldo;
        $firstline['inventory_cost']=$inventory_cost;
        $firstline['total_cost']=$total_cost;
        $firstline['accumulation']=$total_cost;
        $i=-1;
        $saldoawal=$saldo;
        foreach($data as $row){
            ++$i;
            $row=(array)$row;
            $row['qty_start']=$saldoawal;
            $saldoawal =  $saldoawal+(float)$row['qty_in']-(float)$row['qty_out'];
            $saldoawal = $saldoawal + (float)$row['qty_opname'];
            $row['qty_last']=$saldoawal;
            $row['accumulation'] = (float)$row['total_cost']+$total_cost;
            $total_cost = (float)$row['total_cost']+$total_cost;
            $data[$i+1]=$row;
        }
        $data[0]=$firstline;
        return response()->success('Success',$data);
    }

    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $pool_code = isset($request->pool_code) ? $request->pool_code :'';
        $state=$request->state;
        $sortBy=($sortBy=='ref_date') ? 'item_code':$sortBy;
        //$warehouseid=PagesHelp::Warehouse($pool_code);
        $warehouseid=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $data= Items::from('m_item as a')
        ->selectRaw("a.item_code,a.part_number,a.descriptions,a.mou_warehouse,IFNULL(b.on_hand,0) as on_hand,IFNULL(b.price,0) as price,IFNULL(b.total,0) as total")
        ->leftjoin(DB::raw("(SELECT  warehouse_id,item_code,SUM(on_hand) AS on_hand,SUM(price *on_hand)/SUM(on_hand) AS price,SUM(price*on_hand) AS total FROM m_item_stock_price
                    GROUP BY warehouse_id,item_code
                    HAVING SUM(on_hand)>0) as b"),
                function($join) use($warehouseid)
                {
                $join->on('a.item_code', '=', 'b.item_code')
                ->on('b.warehouse_id','=',DB::raw("'$warehouseid'"));
                });
        $data=$data->where('item_group','<>','400');
        if ($state=='2'){
            $data=$data->whereRaw('IFNULL(b.on_hand,0)>0');
        } else if ($state=='3'){
            $data=$data->whereRaw('IFNULL(b.on_hand,0)=0');
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.item_code', 'like', $filter)
                    ->orwhere('a.part_number', 'like', $filter)
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
            //$data=$data->tosql();
        }
        return response()->success('Success', $data);
    }
    public function report(Request $request)
    {
        $pool_code = isset($request->pool_code) ? $request->pool_code :'';
        $state=$request->state;
        $warehouseid=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $data= Items::from('m_item as a')
        ->selectRaw("a.item_code,a.part_number,a.descriptions,a.mou_warehouse,IFNULL(b.on_hand,0) as on_hand,IFNULL(b.price,0) as price,IFNULL(b.total,0) as total")
          ->leftjoin(DB::raw("(SELECT  item_code,SUM(on_hand) AS on_hand,SUM(price*on_hand)/SUM(on_hand) AS price,SUM(price*on_hand) AS total FROM m_item_stock_price
                    WHERE warehouse_id='$warehouseid'
                    GROUP BY item_code
                    HAVING SUM(on_hand)>0) as b"),
                function($join) use($warehouseid)
                {
                $join->on('a.item_code', '=', 'b.item_code');
                });
        $data=$data->where('item_group','<>','400');
        if ($state=='2'){
            $data=$data->whereRaw('IFNULL(b.on_hand,0)>0');
        } else if ($state=='3'){
            $data=$data->whereRaw('IFNULL(b.on_hand,0)=0');
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN STOCK (UPTODATE)');
        $sheet->setCellValue('A2', 'GUDANG');
        $sheet->setCellValue('B2', ': '.$warehouseid);

        $sheet->setCellValue('A5', 'Kode Item');
        $sheet->setCellValue('B5', 'Barcode');
        $sheet->setCellValue('C5', 'Nama Barang');
        $sheet->setCellValue('D5', 'Stock');
        $sheet->setCellValue('E5', 'Satuan');
        $sheet->setCellValue('F5', 'Total Harga');
        $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValueExplicit('A'.$idx,$row->item_code,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$idx, $row->part_number);
            $sheet->setCellValue('C'.$idx, $row->descriptions);
            $sheet->setCellValue('D'.$idx, $row->on_hand);
            $sheet->setCellValue('E'.$idx, $row->mou_warehouse);
            $sheet->setCellValue('F'.$idx, $row->total);
        }

        $sheet->getStyle('A6:C'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('E'.$idx, "TOTAL");
        $sheet->setCellValue('F'.$idx, "=SUM(F6:F$last)");
        $sheet->getStyle('D6:D'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        $sheet->getStyle('F6:F'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        // Formater
        $sheet->getStyle('A1:F5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'F'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:F'.$idx)->applyFromArray($styleArray);
        foreach(range('C','F') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_stock_uptodate.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public function stock_query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ? "desc" :"asc";
        $sortBy = $request->sortBy;
        $state=$request->state;
        $sortBy=($sortBy=='ref_date') ? 'item_code':$sortBy;
        $warehouseid=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $start_date=isset($request->start_date) ? $request->start_date : Date('Y-m-d');
        $end_date=isset($request->end_date) ? $request->end_date : Date('Y-m-d');
        $year_period=date_format(date_create($start_date),'Y');
        $begbal=ItemMutationYearly::selectRaw("item_code,SUM(begining_balance) as begining_balance,SUM(stock_value) as stock_value")
        ->where("warehouse_id",$warehouseid)
        ->where("year_period",$year_period)
        ->groupBy("item_code");

        $date1=date_create($start_date);
        $date1->setDate($date1->format('Y'), 1, 1);
        $date2=date_create($start_date);
        $date2->modify('-1 day');
        $sub_begining=ItemMutation::selectRaw("item_code,SUM((qty_in-qty_out)+qty_adjustment) as beg_bal,SUM(total_cost) as beg_bal_cost")
        ->where("ref_date",">=",$date1)
        ->where("ref_date","<=",$date2)
        ->where("warehouse_id",$warehouseid)
        ->groupBy("item_code");

        $sub=ItemMutation::selectRaw("item_code,SUM(qty_in) as qty_in,SUM(qty_out) as qty_out,SUM(qty_adjustment) as qty_opname,SUM(total_cost) as inv_cost")
        ->where("ref_date",">=",$start_date)
        ->where("ref_date","<=",$end_date)
        ->where("warehouse_id",$warehouseid)
        ->groupBy("item_code");

        $data= Items::from('m_item as a')
        ->selectRaw("a.item_code as _index,a.item_code,a.part_number,a.descriptions,a.mou_warehouse,
        IFNULL(b.qty_in,0) as stock_in,IFNULL(b.qty_out,0) as stock_out,IFNULL(b.qty_opname,0) as stock_so,
        IFNULL(c.begining_balance,0)+IFNULL(d.beg_bal,0)+(IFNULL(b.qty_in,0)-IFNULL(b.qty_out,0))+IFNULL(b.qty_opname,0) as end_stock,
        IFNULL(c.stock_value,0)+IFNULL(b.inv_cost,0)+IFNULL(d.beg_bal_cost,0) as inventory_value,
        IFNULL(c.begining_balance,0)+IFNULL(d.beg_bal,0) as begining_stock")
        ->leftjoinsub($sub,"b",function($join) {
            $join->on("a.item_code","=","b.item_code");
        })
        ->leftjoinsub($begbal,"c",function($join) {
            $join->on("a.item_code","=","c.item_code");
        })
        ->leftjoinsub($sub_begining,"d",function($join) {
            $join->on("a.item_code","=","d.item_code");
        })
        ->where('item_group','<>','400');

        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.item_code', 'like', $filter)
                    ->orwhere('a.part_number', 'like', $filter)
                    ->orwhere('a.descriptions', 'like', $filter);
            });
        }
        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);
        return response()->success('Success', $data);
    }

    public function stock_report(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ? "desc" :"asc";
        $sortBy = $request->sortBy;
        $state=$request->state;
        $warehouseid=isset($request->warehouse_id) ? $request->warehouse_id : 'XXX';
        $start_date=isset($request->start_date) ? $request->start_date : Date('Y-m-d');
        $end_date=isset($request->end_date) ? $request->end_date : Date('Y-m-d');
        $year_period=date_format(date_create($start_date),'Y');
        $begbal=ItemMutationYearly::selectRaw("item_code,SUM(begining_balance) as begining_balance,SUM(stock_value) as stock_value")
        ->where("warehouse_id",$warehouseid)
        ->where("year_period",$year_period)
        ->groupBy("item_code");

        $date1=date_create($start_date);
        $date1->setDate($date1->format('Y'), 1, 1);
        $date2=date_create($start_date);
        $date2->modify('-1 day');
        $sub_begining=ItemMutation::selectRaw("item_code,SUM((qty_in-qty_out)+qty_adjustment) as beg_bal,SUM(total_cost) as beg_bal_cost")
        ->where("ref_date",">=",$date1)
        ->where("ref_date","<=",$date2)
        ->where("warehouse_id",$warehouseid)
        ->groupBy("item_code");

        $sub=ItemMutation::selectRaw("item_code,SUM(qty_in) as qty_in,SUM(qty_out) as qty_out,SUM(qty_adjustment) as qty_opname,SUM(total_cost) as inv_cost")
        ->where("ref_date",">=",$start_date)
        ->where("ref_date","<=",$end_date)
        ->where("warehouse_id",$warehouseid)
        ->groupBy("item_code");

        $data= Items::from('m_item as a')
        ->selectRaw("a.item_code as _index,a.item_code,a.part_number,a.descriptions,a.mou_warehouse,
        IFNULL(b.qty_in,0) as stock_in,IFNULL(b.qty_out,0) as stock_out,IFNULL(b.qty_opname,0) as stock_so,
        IFNULL(c.begining_balance,0)+IFNULL(d.beg_bal,0)+(IFNULL(b.qty_in,0)-IFNULL(b.qty_out,0))+IFNULL(b.qty_opname,0) as end_stock,
        IFNULL(c.stock_value,0)+IFNULL(b.inv_cost,0)+IFNULL(d.beg_bal_cost,0) as inventory_value,
        IFNULL(c.begining_balance,0)+IFNULL(d.beg_bal,0) as begining_stock")
        ->leftjoinsub($sub,"b",function($join) {
            $join->on("a.item_code","=","b.item_code");
        })
        ->leftjoinsub($begbal,"c",function($join) {
            $join->on("a.item_code","=","c.item_code");
        })
        ->leftjoinsub($sub_begining,"d",function($join) {
            $join->on("a.item_code","=","d.item_code");
        })
        ->where('a.item_group','<>','400')
        ->orderBy("a.item_code")
        ->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN STOCK (PER PERIODE)');
        $sheet->setCellValue('A2', 'GUDANG');
        $sheet->setCellValue('B2', ': '.$warehouseid);
        $sheet->setCellValue('A3', 'PERIODE');
        $sheet->setCellValue('B3', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));

        $sheet->setCellValue('A5', 'Kode Item');
        $sheet->setCellValue('B5', 'Part Number');
        $sheet->setCellValue('C5', 'Nama Barang');
        $sheet->setCellValue('D5', 'Saldo Awal');
        $sheet->setCellValue('E5', 'Masuk');
        $sheet->setCellValue('F5', 'Keluar');
        $sheet->setCellValue('G5', 'Opname');
        $sheet->setCellValue('H5', 'Saldo Akhir');
        $sheet->setCellValue('I5', 'Nilai Stock');
        $sheet->getStyle('A5:I5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValueExplicit('A'.$idx,$row->item_code,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$idx,$row->part_number,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('C'.$idx, $row->descriptions);
            $sheet->setCellValue('D'.$idx, $row->begining_stock);
            $sheet->setCellValue('E'.$idx, $row->stock_in);
            $sheet->setCellValue('F'.$idx, $row->stock_out);
            $sheet->setCellValue('G'.$idx, $row->stock_so);
            $sheet->setCellValue('H'.$idx, $row->end_stock);
            $sheet->setCellValue('I'.$idx, $row->inventory_value);
        }

        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('C'.$idx, "TOTAL");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->getStyle('D6:I'.$idx)->getNumberFormat()->setFormatCode('#,##0.#0;[RED](#,##0.#0)');
        // Formater
        $sheet->getStyle('A1:I5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'I'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:I'.$idx)->applyFromArray($styleArray);
        foreach(range('C','I') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_stock_per_periode.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
