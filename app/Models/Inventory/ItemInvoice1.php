<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class ItemInvoice1 extends Model
{
    protected $table = 't_item_invoice1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    protected $guarded = [];
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'discount1'=>'float',
        'discount2' => 'float',
        'payment_discount' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'payment' => 'float',
        'unpaid'=>'float',
        'qty_request'=>'float',
        'current_stock'=>'float',
        'purchase_price'=>'float',
        'prc_discount1'=>'float',
        'prc_discount2'=>'float',
        'prc_tax'=>'float',
        'is_void'=>'string',
        'is_credit_note'=>'string',
        'qty_invoice'=>'float',
        'qty_retur'=>'float',
        'convertion'=>'float',
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        return PagesHelp::GetDocseries($pool_code,'LPB',$ref_date);
    }

    public static function GenerateRetur($pool_code,$ref_date){
        return PagesHelp::GetDocseries($pool_code,'RPB',$ref_date);
    }

    public static function cancel_order($sysid) {
        $request = DB::table('t_item_invoice1')
            ->select('ref_document', 'order_sysid', 'order_document')
            ->where('sysid', $sysid)
            ->first();

        if ($request && $request->order_sysid != -1) {
            // Adjust quantities and reset fields in `t_purchase_order2`
            DB::update("UPDATE t_purchase_order2 a
                        INNER JOIN t_item_invoice2 b ON a.sysid = b.order_sysid AND a.item_code = b.item_code
                        SET a.line_state = '0',
                            a.qty_received = COALESCE(a.qty_received, 0) - COALESCE(b.qty_invoice, 0),
                            a.received_no = -1,
                            a.last_received = NULL
                        WHERE a.sysid = ? AND b.sysid = ?",
                        [$request->order_sysid, $sysid]);

            // Update document status in `t_purchase_order1`
            DB::update("UPDATE t_purchase_order1
                        SET document_status = CASE
                            WHEN EXISTS (SELECT 1 FROM t_purchase_order2 WHERE sysid = ? AND qty_received = 0) THEN 'O'
                            WHEN EXISTS (SELECT 1 FROM t_purchase_order2 WHERE sysid = ? AND qty_received > 0 AND qty_order > qty_received) THEN 'P'
                            WHEN NOT EXISTS (SELECT 1 FROM t_purchase_order2 WHERE sysid = ? AND line_state = 'O') THEN 'C'
                        END
                        WHERE sysid = ?",
                        [$request->order_sysid, $request->order_sysid, $request->order_sysid, $request->order_sysid]);

            // Set line state based on received quantities in a single query
            DB::update("UPDATE t_purchase_order2
                        SET line_state = CASE
                            WHEN qty_received = 0 THEN 'O'
                            WHEN qty_received > 0 AND qty_received < qty_order THEN 'P'
                            WHEN qty_received >= qty_order THEN 'C'
                        END
                        WHERE sysid = ?",
                        [$request->order_sysid]);
        }
    }

    public static function update_order($sysid) {
        $request = DB::table('t_item_invoice1')
            ->select('ref_document', 'order_sysid', 'order_document')
            ->where('sysid', $sysid)
            ->first();

        if ($request && $request->order_sysid != -1) {
            // Update quantity and received details in `t_purchase_order2`
            DB::update("UPDATE t_purchase_order2 a
                        INNER JOIN t_item_invoice2 b ON a.sysid = b.order_sysid AND a.item_code = b.item_code
                        SET a.line_state = 'P',
                            a.qty_received = COALESCE(a.qty_received, 0) + COALESCE(b.qty_invoice, 0),
                            a.received_no = b.sysid,
                            a.last_received = CURRENT_DATE()
                        WHERE a.sysid = ? AND b.sysid = ?",
                        [$request->order_sysid, $sysid]);

            // Update document status and line states in `t_purchase_order2`
            DB::update("UPDATE t_purchase_order2
                        SET line_state = CASE
                            WHEN qty_received = 0 THEN 'O'
                            WHEN qty_received > 0 AND qty_received < qty_order THEN 'P'
                            WHEN qty_received >= qty_order THEN 'C'
                        END
                        WHERE sysid = ?",
                        [$request->order_sysid]);

            // Update `document_status` in `t_purchase_order1`
            DB::update(
                "UPDATE t_purchase_order1
                SET document_status='C'
            WHERE sysid=?",[$request->order_sysid]);

            DB::update(
                "UPDATE t_purchase_order1
                SET document_status='P'
                WHERE sysid=? AND  sysid IN (
                 SELECT DISTINCT sysid FROM t_purchase_order2
                 WHERE sysid=? AND line_state<>'C')
                ",[$request->order_sysid,$request->order_sysid]);

        }
    }
}

