<?php
namespace App\Helpers;
use App\Models\Inventory\ItemsStockPrice;
use App\Models\Inventory\ItemsStock;
use App\Models\Inventory\ItemsPrice;
use App\Models\Inventory\ItemInvoice1;
use App\Models\Inventory\ItemInvoice2;
use App\Models\Inventory\GoodsOut1;
use App\Models\Inventory\GoodsOut2;
use App\Models\Inventory\ItemCorrection1;
use App\Models\Inventory\ItemCorrection2;
use App\Models\Inventory\StockTransfer1;
use App\Models\Inventory\StockTransfer2;
use App\Models\Inventory\ItemMutation;
use App\Models\Inventory\ItemMutationYearly;
use App\Models\Service\GoodsRequest1;
use App\Models\Service\GoodsRequest2;
use Illuminate\Support\Facades\Log;



use PagesHelp;


use Illuminate\Support\Facades\DB;

class Inventory
{
    static function getPriceCode(){
        // Retrieve and increment the last price code value
        $lastInt = PagesHelp::get_data('PRICE_CODE', 'I') + 1;

        // Store the updated price code
        PagesHelp::write_data('PRICE_CODE', 'I', $lastInt);

        // Convert to hexadecimal and pad with leading zeros to 5 characters
        return str_pad(strtoupper(dechex($lastInt)), 5, "0", STR_PAD_LEFT);
    }


    static function FIFO($sysid,$source,$operation){
        $respon = [
            'success' => true,
            'message' => ''
        ];
        $operation = strtolower($operation);
        DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_price");

        DB::statement("
            CREATE TEMPORARY TABLE IF NOT EXISTS tmp_price
            ENGINE=Memory
            AS
            SELECT warehouse_id, item_code, price_code
            FROM m_item_stock_price
            WHERE sysid IS NULL
        ");

        /* Rollback Stock if exists transaction*/
        $warehouse_id = ItemsPrice::where('sysid', $sysid)
        ->where('doc_type', $source)
        ->value('warehouse_id') ?? '';

        if (!($warehouse_id=='')) {
            DB::update(
                "UPDATE m_item_stock_price a
                INNER JOIN t_item_price b
                    ON a.warehouse_id = b.warehouse_id
                    AND a.item_code   = b.item_code
                    AND a.price_code  = b.price_code
                SET
                a.on_hand  = IFNULL(a.on_hand, 0) - IFNULL(b.qty, 0),
                last_activity  = now(),
                update_timestamp= now()
                WHERE a.warehouse_id=?
                    AND b.sysid = ?
                    AND b.doc_type = ?
                    AND IFNULL(b.is_deleted, 0) = 0",
                [$warehouse_id,$sysid, $source]
            );

            DB::insert(
                "INSERT INTO tmp_price
                SELECT  a.warehouse_id, a.item_code, a.price_code
                FROM m_item_stock_price a
                    INNER JOIN t_item_price b
                        ON a.warehouse_id = b.warehouse_id
                        AND a.item_code   = b.item_code
                        AND a.price_code  = b.price_code
                WHERE a.warehouse_id=?
                    AND b.sysid = ?
                    AND b.doc_type = ?
                    AND IFNULL(b.is_deleted, 0) = 0",
                [$warehouse_id,$sysid, $source]
            );
        }

        /*Calculate FIFO Inventory*/
        if (!($operation=='deleted')) {
            $gs = Inventory::generateSource($sysid,$source);

            $opname = $gs['opname'];
            $add    = $gs['add'];
            $minus  = $gs['minus'];

            ItemsPrice::where('sysid',$sysid)
            ->where('doc_type',$source)
            ->update([
                'is_deleted' =>'1'
            ]);

            if ($opname) {
                Inventory::UpdateOpname($sysid,$source,$opname);
            } else if ($source=='IRS') {
                Inventory::UpdateAddStockRetur($sysid,$source,$add);
            } else if ($add) {
                Inventory::UpdateAddStock($sysid,$source,$add);
            } else if (($minus) && ($source=='RPB')) {
                $respon=Inventory::UpdateMinusStockRetur($sysid,$source,$minus);
            } else if ($minus) {
                $respon=Inventory::UpdateMinusStock($sysid,$source,$minus);
            }
            /*check if Update stock price not correct */
            if (!($respon['success'])) {
                return $respon;
                exit(0);
            }

            $warehouse_id = ItemsPrice::where('sysid', $sysid)
            ->where('doc_type', $source)
            ->value('warehouse_id') ?? '';
        } else {
            ItemsPrice::where('sysid',$sysid)
            ->where('doc_type',$source)
            ->update([
                'is_deleted' =>'1'
            ]);
        }
        /*Update m_item_stock_price*/
        DB::update(
            "UPDATE m_item_stock_price a
            INNER JOIN t_item_price b
                ON a.item_code = b.item_code
                AND a.warehouse_id = b.warehouse_id
                AND a.price_code = b.price_code
            SET a.on_hand  = IFNULL(a.on_hand, 0) + IFNULL(b.qty, 0),
            last_activity  = now(),
            update_timestamp= now()
            WHERE a.warehouse_id=?
                AND b.sysid = ?
                AND b.doc_type = ?
                AND IFNULL(b.is_deleted, 0) = 0",
            [$warehouse_id,$sysid, $source]
        );

        DB::insert(
            "INSERT INTO tmp_price
            SELECT  a.warehouse_id, a.item_code, a.price_code
            FROM m_item_stock_price a
                INNER JOIN t_item_price b
                    ON a.warehouse_id = b.warehouse_id
                    AND a.item_code   = b.item_code
                    AND a.price_code  = b.price_code
            WHERE a.warehouse_id=?
                AND b.sysid = ?
                AND b.doc_type = ?
                AND IFNULL(b.is_deleted, 0) = 0",
            [$warehouse_id,$sysid, $source]
        );

        $negative = ItemsStockPrice::from("m_item_stock_price as pc")
        ->selectRaw("pc.item_code,pc.price_code,pc.on_hand,pc.price,itm.descriptions")
        ->join("tmp_price as tmp",function ($join)  {
            $join->on("pc.warehouse_id","=","tmp.warehouse_id");
            $join->on("pc.item_code","=","tmp.item_code");
            $join->on("pc.price_code","=","tmp.price_code");
        })
        ->join("m_item as itm","pc.item_code","=","itm.item_code")
        ->where("pc.warehouse_id","=",$warehouse_id)
        ->whereRaw("IFNULL(pc.on_hand,0)<0")
        ->first();

        if ($negative) {
            $respon = [
                'success' => false,
                'message' => sprintf(
                    "%s - %s [ %s ], Stock menjadi minus (UNIT FIFO - MINUS)",
                    $negative->item_code,
                    $negative->descriptions,
                    number_format($negative->on_hand, 2, ',', '.')
                    )
            ];
        }
        return $respon;
    }

    static function generateSource($sysid,$source)
    {
        $respon = [
            'add' => null,
            'minus' => null,
            'opname'=>null
        ];
        // Define common joins and select fields
        if (($source == 'LPB') || ($source == 'RPB')) {
            $selectFields = "a.warehouse_id, a.item_code, a.descriptions, IFNULL(a.price_code, c.price_code) as price_code";
        } else if (($source == 'ITI') || ($source == 'ITO')) {
            $selectFields = "a.warehouse_src as warehouse_id, a.item_code, a.descriptions, IFNULL(c.price_code, '') as price_code";
        } else {
            $selectFields = "a.warehouse_id, a.item_code, a.descriptions, IFNULL(c.price_code, '') as price_code";
        }
        if ($source == 'LPB') {
            $respon['add'] = ItemInvoice2::from('t_item_invoice2 as a')
                ->selectRaw("b.ref_date, $selectFields, a.inventory_update as qty, a.price_cost as price, YEAR(b.ref_date) as year_period")
                ->join('t_item_invoice1 as b', 'a.sysid', '=', 'b.sysid')
                ->leftJoin('t_item_price as c', function($join) {
                    $join->on('a.sysid', '=', 'c.sysid')
                        ->on('a.item_code', '=', 'c.item_code')
                        ->on('a.price_code', '=', 'c.price_code')
                        ->on('c.doc_type', '=', DB::raw("'LPB'"))
                        ->on('c.is_deleted', '=', DB::raw("0"));
                })
                ->where('a.sysid', $sysid)
                ->distinct()
                ->get();
        } else if ($source == 'RPB') {
            $respon['minus'] = ItemInvoice2::from('t_item_invoice2 as a')
                ->selectRaw("b.ref_date, $selectFields, ABS(a.inventory_update) as qty, ABS(a.price_cost) as price, YEAR(b.ref_date) as year_period")
                ->join('t_item_invoice1 as b', 'a.sysid', '=', 'b.sysid')
                ->leftJoin('t_item_price as c', function($join) {
                    $join->on('a.sysid', '=', 'c.sysid')
                        ->on('a.item_code', '=', 'c.item_code')
                        ->on('a.price_code', '=', 'c.price_code')
                        ->on('c.doc_type', '=', DB::raw("'RPB'"))
                        ->on('c.is_deleted', '=', DB::raw("0"));
                })
                ->where('a.sysid', $sysid)
                ->where('b.is_credit_note','1')
                ->distinct()
                ->get();
        } elseif ($source == 'PBG') {
            $respon['minus'] = GoodsOut2::selectRaw('warehouse_id, item_code, descriptions, qty_item')
                ->where('sysid', $sysid)
                ->get();
        } elseif ($source == 'ITI') {
            $respon['add'] = StockTransfer2::from('t_inventory_transfer2 as a')
                ->selectRaw("b.ref_date, $selectFields, a.qty_item as qty, a.itemcost as price, YEAR(b.ref_date) as year_period")
                ->join('t_inventory_transfer1 as b', 'a.sysid', '=', 'b.sysid')
                ->leftJoin('t_item_price as c', function($join) {
                    $join->on('a.sysid', '=', 'c.sysid')
                        ->on('a.item_code', '=', 'c.item_code')
                        ->on('c.doc_type', '=', DB::raw("'ITI'"))
                        ->on('c.is_deleted', '=', DB::raw("0"));
                })
                ->where('a.sysid', $sysid)
                ->distinct()
                ->get();
        } elseif ($source == 'ITO') {
            $respon['minus'] = StockTransfer2::selectRaw('warehouse_src as warehouse_id, item_code, descriptions, qty_item')
                ->where('sysid', $sysid)
                ->get();
        } elseif ($source == 'IOS') {
            $respon['minus'] = GoodsRequest2::selectRaw('warehouse_id, item_code, descriptions, qty_supply as qty_item')
                ->where('sysid', $sysid)
                ->where('qty_supply', '<>', 0)
                ->get();
        } elseif ($source == 'IRS') {
            $respon['add'] =GoodsRequest2::from('t_inventory_booked2 as b')
                ->join('t_inventory_booked1 as a','a.sysid','=','b.sysid')
                ->selectRaw('b.warehouse_id, b.item_code, c.price_code,b.descriptions, ABS(c.qty) as qty,
                            IFNULL(c.item_cost,0) as price, YEAR(a.ref_date) as year_period')
                ->join('t_item_price as c', function($join) {
                    $join->on('b.sysid', '=', 'c.sysid')
                        ->on('b.item_code', '=', 'c.item_code')
                        ->on('c.doc_type', '=', DB::raw("'IRS'"))
                        ->on('c.is_deleted', '=', DB::raw("0"));
                })
                ->where('b.sysid', $sysid)
                ->where('b.qty_supply', '<>', 0)
                ->get();
        } elseif ($source == 'ISO') {
            $respon['opname'] = ItemCorrection2::from('t_inventory_correction2 as a')
                ->selectRaw("b.ref_date, a.warehouse_id, a.price_code, a.item_code, a.descriptions, a.end_stock as qty,
                            a.cost_adjustment as price, YEAR(b.ref_date) as year_period")
                ->leftJoin('t_inventory_correction1 as b', 'a.sysid', '=', 'b.sysid')
                ->where('a.sysid', $sysid)
                ->get();
        }
        return $respon;
    }

    static function UpdateItemStockPrice($date,$item_code,$warehouse_id,$price_code,$price=0) {
        $hour  = Date('H');
        $minute= Date('i');
        $second= Date('i');
        $date  = $date.''.Date('H:i:s');
        $date  = date_create($date);
        $date->modify("+$hour hour +$minute minute +$hour second");
        $ItemPrice=ItemsStockPrice::SelectRaw("sysid,warehouse_id,item_code,price_code,on_hand,
        price_date,is_hold,is_allow_negatif,last_activity,update_timestamp,update_userid")
        ->where('item_code',$item_code)
        ->where('warehouse_id',$warehouse_id)
        ->where('price_code',$price_code)
        ->first();

        if (!($ItemPrice)) {
            $ItemPrice= new ItemsStockPrice();
            $ItemPrice->item_code   = $item_code;
            $ItemPrice->warehouse_id= $warehouse_id;
            $ItemPrice->price_code  = $price_code;
        }
        $ItemPrice->price_date = date_format($date,"Y-m-d H:i:s");
        $ItemPrice->price      = $price;
        $ItemPrice->save();
    }

    static function AppendStockAndYearMutation($item_code,$warehouse_id,$year_period) {
        $stock=ItemsStock::selectRaw("sysid,warehouse_id,item_code")
        ->where('item_code',$item_code)
        ->where('warehouse_id',$warehouse_id)
        ->first();

        if (!($stock)) {
            ItemsStock::
            insert([
                'item_code'=>$item_code,
                'warehouse_id'=>$warehouse_id
            ]);
        }

        /* Stock Yearly*/
        $YearlyMutation=ItemMutationYearly::where('item_code',$item_code)
        ->where('warehouse_id',$warehouse_id)
        ->where('year_period',$year_period)
        ->first();

        if (!($YearlyMutation)) {
            ItemMutationYearly::
            insert([
                'item_code'=>$item_code,
                'warehouse_id'=>$warehouse_id,
                'year_period'=>$year_period
            ]);
        }

    }

    static function UpdateOpname($sysid,$source,$opname)
    {
        $line_id=0;
        foreach($opname as $row){
            $line_id++;
            $price_code=$row->price_code;
            ItemsPrice::
            insert([
                'sysid'      => $sysid,
                'item_code'  => $row->item_code,
                'price_code' => $price_code,
                'doc_type'   => $source,
                'qty'        => $row->qty,
                'line_id'    => $line_id,
                'item_cost'   => $row->price,
                'warehouse_id'=> $row->warehouse_id,
                'created_date'=> Date('Y-m-d H:i:s')
            ]);

            /* Stock Price*/
            Inventory::UpdateItemStockPrice($date,$row->item_code,$row->warehouse_id,$row->price_code,$row->price);

            /* Stock */
            Inventory::AppendStockAndYearMutation($row->item_code,$row->warehouse_id,$row->year_period);
        }
    }

    static function UpdateAddStock($sysid,$source,$add){
        $line_id=0;
        foreach($add as $row){
            $line_id++;
            ItemsPrice::
            insert([
                'sysid'      => $sysid,
                'item_code'  => $row->item_code,
                'price_code' => $row->price_code,
                'doc_type'   => $source,
                'qty'        => $row->qty,
                'line_id'    => $line_id,
                'item_cost'   => $row->price,
                'warehouse_id'=> $row->warehouse_id,
                'created_date'=> Date('Y-m-d H:i:s')
            ]);
            /* Stock Price*/
            Inventory::UpdateItemStockPrice($row->ref_date,$row->item_code,$row->warehouse_id,$row->price_code,$row->price);

            /* Stock */
            Inventory::AppendStockAndYearMutation($row->item_code,$row->warehouse_id,$row->year_period);
        }
    }

    static function UpdateAddStockRetur($sysid,$source,$add){
        $line_id=0;
        Log::info('Init :'.$add);
        if ((!$add)|| (count($add)<=0))  {
            $retur = GoodsRequest1::selectRaw("sysid_ref,warehouse_id,YEAR(ref_date) as year_period")->where('sysid',$sysid)->first();
            $year_period = $retur->year_period;
            $add = ItemsPrice::selectRaw("item_code,price_code,ABS(qty) as qty,item_cost as price,warehouse_id,line_id, $year_period as year_period")
            ->where('sysid',$retur->sysid_ref)
            ->where('doc_type','IOS')
            ->where('is_deleted',0)
            ->get();
        }
        Log::info('Update :'.$add);
        foreach($add as $row){
            $line_id++;
            ItemsPrice::
            insert([
                'sysid'      => $sysid,
                'item_code'  => $row->item_code,
                'price_code' => $row->price_code,
                'doc_type'   => $source,
                'qty'        => $row->qty,
                'line_id'    => $line_id,
                'item_cost'   => $row->price,
                'warehouse_id'=> $row->warehouse_id,
                'created_date'=> Date('Y-m-d H:i:s')
            ]);
            /* Stock Price*/
            Inventory::UpdateItemStockPrice($row->ref_date,$row->item_code,$row->warehouse_id,$row->price_code,$row->price);

            /* Stock */
            Inventory::AppendStockAndYearMutation($row->item_code,$row->warehouse_id,$row->year_period);
        }
    }

    static function UpdateMinusStock($sysid,$source,$minus){
        $line_id=0;
        foreach($minus as $row){
            $item_code=trim($row->item_code);
            $qty = floatval($row->qty_item);

            $pc=ItemsStockPrice::selectRaw(
                "sysid,
                 warehouse_id,
                 item_code,
                 price_code,
                 on_hand,price,
                 price_date,
                 is_hold,
                 is_allow_negatif,
                 last_activity")
                ->where('warehouse_id',$row->warehouse_id)
                ->where('item_code',$row->item_code)
                ->where('on_hand','>',0)
                ->orderBy('price_date')
                ->get();

            if ($pc->isNotEmpty()) {
                $rec = count($pc);
                $i = 0;
                while ($qty>0) {
                    $price  = $pc[$i];
                    $update = min(floatval($qty),floatval($price->on_hand));
                    if ($update <= 0){
                        $respon = [
                            'success' => false,
                            'message' => sprintf(
                                "%s - %s [ %s ], Stock tidak mencukupi Gudang %s (UNIT FIFO -0)",
                                $row->item_code,
                                $row->descriptions,
                                number_format($qty, 2, ',', '.'),
                                $row->warehouse_id
                            ),
                        ];
                        return $respon;
                        exit(0);
                    }
                    $line_id++;
                    ItemsPrice::
                    insert([
                        'sysid'     => $sysid,
                        'item_code' => $row->item_code,
                        'price_code'=> $price->price_code,
                        'doc_type'  => $source,
                        'qty'       => -$update,
                        'line_id'   => $line_id,
                        'item_cost' => $price->price,
                        'warehouse_id'=> $row->warehouse_id,
                        'created_date'=> Date('Y-m-d H:i:s')
                    ]);
                    $i= $i + 1;
                    $qty=floatval($qty)-floatval($update);
                    if (($i==$rec) && ($qty>0)) {
                        $respon = [
                            'success' => false,
                            'message' => sprintf(
                                "%s - %s [ %s ], Stock tidak mencukupi Gudang %s (UNIT FIFO-1)",
                                $row->item_code,
                                $row->descriptions,
                                number_format($qty, 2, ',', '.'),
                                $row->warehouse_id
                            ),
                        ];
                        return $respon;
                        exit(0);
                    }
                }
            } else {
                $respon = [
                    'success' => false,
                    'message' => sprintf(
                        "%s - %s [ %s ], Stock tidak mencukupi Gudang %s (UNIT FIFO-2)",
                        $row->item_code,
                        $row->descriptions,
                        number_format(0, 2, ',', '.'),
                        $row->warehouse_id
                    ),
                ];
                return $respon;
                exit(0);
            }
        }
        $respon = [
            'success' => true,
            'message' => ''
        ];
        return $respon;
    }

    static function UpdateMinusStockRetur($sysid,$source,$minus){
        $line_id=0;
        foreach($minus as $row){
            $line_id++;
            $price_code=$row->price_code;
            ItemsPrice::
            insert([
                'sysid'      => $sysid,
                'item_code'  => $row->item_code,
                'price_code' => $price_code,
                'doc_type'   => $source,
                'qty'        => - $row->qty,
                'line_id'    => $line_id,
                'item_cost'   => $row->price,
                'warehouse_id'=> $row->warehouse_id,
                'created_date'=> Date('Y-m-d H:i:s')
            ]);

        }
        $respon = [
            'success' => true,
            'message' => ''
        ];
        return $respon;
    }
    public static function ItemCard($sysid,$source,$operation,$cogs,$adjustment,$flager=false) {
        $respon = [
            'success' => true,
            'message' => '',
        ];

        $operation = strtolower($operation);
        DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_stock");

        DB::statement("
            CREATE TEMPORARY TABLE IF NOT EXISTS tmp_stock
            ENGINE=Memory
            AS
            SELECT warehouse_id, item_code, qty_in AS stock
            FROM t_item_mutation
            WHERE sysid IS NULL
        ");
        $data=ItemMutation::select('warehouse_id')
        ->where('sysid',$sysid)
        ->where('doc_type',$source)
        ->first();

        if (in_array($operation, ['updated', 'deleted']) && ($data)) {
            DB::insert(
                "INSERT INTO tmp_stock
                SELECT DISTINCT warehouse_id, item_code, 0
                FROM t_item_mutation
                WHERE sysid = ? AND doc_type = ? AND is_deleted = 0",
                [$sysid, $source]
            );

            $query = ItemMutation::where('sysid', $sysid)->where('doc_type', $source);

            if ($operation === 'updated') {
                $query->delete();
            } else {
                $flager ? $query->update(['is_deleted' => '1']) : $query->delete();
            }
        }

        $FIPOFeedback=Inventory::FIFO($sysid,$source,$operation);
        if ($FIPOFeedback['success']== false){
            return [
                'success'=>false,
                'message'=>$FIPOFeedback['message']
            ];
            exit(0);
        }

        if (in_array($operation, ['inserted', 'updated'])) {
            $sourceMappings = [
                'LPB' => [
                    'table' => 't_item_invoice1',
                    'qty_in' => 'ABS(b.qty)',
                    'qty_out' => '0',
                    'line_notes' => "CONCAT('PENERIMAAN BARANG [', a.doc_number, '] ', a.partner_name, '/', a.ref_document)",
                    'warehouse_id' => 'a.warehouse_id'
                ],
                'RPB' => [
                    'table' => 't_item_invoice1',
                    'qty_in' => '0',
                    'qty_out' => 'ABS(b.qty)',
                    'line_notes' => "CONCAT('RETUR PEMBELIAN BARANG [', a.doc_number, '] ', a.partner_name, '/', a.ref_document)",
                    'warehouse_id' => 'a.warehouse_id'
                ],
                'PBG' => [
                    'table' => 't_inventory_inout1',
                    'qty_in' => '0',
                    'qty_out' => 'ABS(b.qty)',
                    'line_notes' => "CONCAT('PENGELUARAN BARANG [', a.doc_number, '] ', IFNULL(a.reference, ''), '/', IFNULL(a.vehicle_no, '-'))",
                    'warehouse_id' => 'a.warehouse_id'
                ],
                'ITO' => [
                    'table' => 't_inventory_transfer1',
                    'qty_in' => '0',
                    'qty_out' => 'ABS(b.qty)',
                    'line_notes' => "CONCAT('TRANSFER STOCK KELUAR [', a.doc_number, '] ', IFNULL(a.reference, ''), '/', IFNULL(a.warehouse_dest, '-'))",
                    'warehouse_id' => 'a.warehouse_src'
                ],
                'ITI' => [
                    'table' => 't_inventory_transfer1',
                    'qty_in' => 'ABS(b.qty)',
                    'qty_out' => '0',
                    'line_notes' => "CONCAT('TRANSFER STOCK MASUK [', a.doc_number, '] ', IFNULL(a.reference, ''), '/', IFNULL(a.warehouse_dest, '-'))",
                    'warehouse_id' => 'a.warehouse_src'
                ],
                'IOS' => [
                    'table' => 't_inventory_booked1',
                    'qty_in' => '0',
                    'qty_out' => 'ABS(b.qty)',
                    'line_notes' => "CONCAT('PEMAKAIAN BENGKEL [', a.doc_number, '] ', IFNULL(a.reference, ''), '/', IFNULL(a.warehouse_id, '-'))",
                    'warehouse_id' => 'a.warehouse_id'
                ],
                'IRS' => [
                    'table' => 't_inventory_booked1',
                    'qty_in' => 'ABS(b.qty)',
                    'qty_out' => '0',
                    'line_notes' => "CONCAT('RETUR PEMAKAIAN BENGKEL [', a.doc_number, '] ', IFNULL(a.reference, ''), '/', IFNULL(a.warehouse_id, '-'))",
                    'warehouse_id' => 'a.warehouse_id'
                ],
                'ISO' => [
                    'table' => 't_inventory_correction1',
                    'qty_in' => '0',
                    'qty_out' => '0',
                    'qty_adjustment' => 'ABS(b.qty)',
                    'line_notes' => "CONCAT('KOREKSI STOCK [', a.doc_number, '] ', IFNULL(a.reference1, ''))",
                    'warehouse_id' => 'a.warehouse_id'
                ]
            ];

            if (isset($sourceMappings[$source])) {
                $mapping = $sourceMappings[$source];

                $qtyIn         = $mapping['qty_in'] ?? '0';
                $qtyOut        = $mapping['qty_out'] ?? '0';
                $qtyAdjustment = $mapping['qty_adjustment'] ?? '0';
                $lineNotes     = $mapping['line_notes'];
                $table         = $mapping['table'];
                $warehouse     = $mapping['warehouse_id'];

                DB::insert(
                    "INSERT INTO t_item_mutation(
                        sysid, doc_type, line_no, doc_number, item_code, price_code, warehouse_id,
                        qty_in, qty_out, qty_adjustment, inventory_cost, total_cost, ref_date, posting_date,
                        line_notes, update_userid, update_timestamp
                    )
                    SELECT
                        ?, ?, b.line_id, a.doc_number, b.item_code, b.price_code, $warehouse,
                        $qtyIn, $qtyOut, $qtyAdjustment, b.item_cost, b.item_cost * b.qty,
                        a.ref_date, NOW(), $lineNotes, '', NOW()
                    FROM $table a
                    INNER JOIN t_item_price b ON a.sysid = b.sysid AND b.doc_type = ?
                    WHERE a.sysid = ? AND b.is_deleted = 0",
                    [$sysid, $source, $source, $sysid]
                );
            }
        }

        $warehouse_id = ItemMutation::where('sysid', $sysid)
        ->where('doc_type', $source)
        ->value('warehouse_id') ?? '-';


        DB::update(
            "UPDATE m_item_stock a
            INNER JOIN (
                SELECT warehouse_id,item_code, SUM(IFNULL(on_hand, 0)) AS on_hand
                FROM m_item_stock_price
                WHERE warehouse_id = ?
                GROUP BY warehouse_id,item_code
            ) b ON a.item_code = b.item_code AND a.warehouse_id = b.warehouse_id
            SET a.on_hand = b.on_hand,
            last_activity=now()
            WHERE a.warehouse_id=?",
            [$warehouse_id, $warehouse_id]);

        if ($cogs) {
            if (!$adjustment) {
                DB::update(
                    "UPDATE m_item a
                    INNER JOIN (
                        SELECT item_code, SUM((qty_in - qty_out) + qty_adjustment) AS updated, SUM(total_cost) AS total_cost
                        FROM t_item_mutation
                        WHERE sysid = ? AND doc_type = ? AND IFNULL(is_deleted, 0) = 0
                        GROUP BY item_code
                    ) b ON a.item_code = b.item_code
                    SET a.cost_average = ((a.on_hand * a.cost_average) + b.total_cost) / (a.on_hand + b.updated)
                    WHERE (a.on_hand + b.updated) <> 0
                ", [$sysid, $source]);
            } else {
                // Update average cost based on inventory cost
                DB::update(
                    "UPDATE m_item a
                    INNER JOIN t_item_mutation b ON a.item_code = b.item_code
                    SET a.cost_average = b.inventory_cost
                    WHERE b.sysid = ? AND b.doc_type = ? AND is_deleted = 0
                ", [$sysid, $source]);

                // Update item stock location based on inventory correction
                DB::update(
                    "UPDATE m_item_stock a
                    INNER JOIN t_inventory_correction2 b
                    ON  a.item_code = b.item_code
                    AND a.warehouse_id = b.warehouse_id
                    SET a.location = b.location
                    WHERE b.sysid = ?
                ", [$sysid]);
            }
        }

        // Update the total `on_hand` stock in `m_item` from `m_item_stock`.
        DB::update(
            "UPDATE m_item a
            INNER JOIN (
                SELECT item_code, SUM(on_hand) AS on_hand
                FROM m_item_stock
                GROUP BY item_code
            ) b ON a.item_code = b.item_code
            SET a.on_hand = b.on_hand
            WHERE a.item_code IN (
                SELECT DISTINCT item_code
                FROM t_item_mutation
                WHERE sysid =? AND doc_type=? AND is_deleted = 0
            )
        ",[$sysid,$source]);

        // Insert unique warehouse_id and item_code pairs into a temporary table `tmp_stock` for stock checks.
        DB::statement("INSERT INTO tmp_stock
            SELECT DISTINCT warehouse_id, item_code, 0 AS stock
            FROM t_item_mutation
            WHERE sysid = ? AND doc_type = ? AND is_deleted=0", [$sysid, $source]);

        // Retrieve stock information, joining `tmp_stock`, `m_item_stock`, and `m_item` for item descriptions.
        $stock = DB::table('m_item_stock as a')
            ->select('a.item_code', 'a.on_hand', 'd.descriptions as item_name')
            ->join('tmp_stock as c', function ($join) {
                $join->on('a.item_code', '=', 'c.item_code')
                    ->on('a.warehouse_id', '=', 'c.warehouse_id');
            })
            ->join('m_item as d', 'a.item_code', '=', 'd.item_code')
            ->distinct()
            ->get();

        // Drop temporary table `tmp_stock` after use.
        DB::statement("DROP TEMPORARY TABLE IF EXISTS tmp_stock");

        // Check for negative stock levels and set response accordingly.
        foreach ($stock as $row) {
            if ((float)$row->on_hand < 0) {
                return [
                    'success' => false,
                    'message' => "Item {$row->item_code} - {$row->item_name} Stock tidak mencukupi/stock Minus"
                ];
            }
        }

        // Return a successful response if no negative stock was found.
        return [
            'success' => true,
            'message' => ""
        ];
   }
}
