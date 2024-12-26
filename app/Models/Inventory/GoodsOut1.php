<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class GoodsOut1 extends Model
{
    protected $table = 't_inventory_inout1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'total' => 'float'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'PBG';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
    public static function cancel_request($sysid){
        $request=DB::table('t_item_invoice1')
        ->select('ref_document','order_sysid','order_document')
        ->where('sysid',$sysid)
        ->first();
        if ($request) {
            if ($request->order_sysid<>'-1'){
                DB::update("UPDATE t_purchase_order2 a INNER JOIN t_item_invoice2 b
                    ON a.received_no=b.order_sysid AND a.item_code=b.item_code
                    SET a.qty_received=IFNULL(a.qty_received,0) - IFNULL(b.qty_invoice,0),received_no=-1
                    WHERE a.sysid=?",[$request->order_sysid]);
            }
        }
    }
    public static function update_request($sysid){
        $request=DB::table('t_item_invoice1')
        ->select('ref_document','order_sysid','order_document')
        ->where('sysid',$sysid)
        ->first();
        if ($request) {
            if ($request->order_sysid<>'-1'){
                DB::update("UPDATE t_purchase_order2 a INNER JOIN t_item_invoice2 b
                    ON a.received_no=b.order_sysid AND a.item_code=b.item_code
                    SET a.qty_received=IFNULL(a.qty_received,0) + IFNULL(b.qty_invoice,0),received_no=b.sysid
                    WHERE a.sysid=?",[$request->order_sysid]);
            }
        }
    }
}
