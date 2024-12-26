<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class PurchaseOrder1 extends Model
{
    protected $table = 't_purchase_order1';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded = [];

    protected $casts = [
        'total' => 'float',
        'prc_discount1' => 'float',
        'prc_discount2' => 'float',
        'prc_tax' => 'float',
        'price' => 'float',
        'qty_order' => 'float',
        'qty_received' => 'float',
        'is_ho' => 'string',
        'is_cancel' => 'string',
        'is_posted' => 'string',
        'is_draft' => 'string',
        'is_printed' => 'string',
        'posted_date' => 'datetime:Y-m-d H:i:s',
        'approved_date1' => 'datetime:Y-m-d H:i:s',
        'approved_date2' => 'datetime:Y-m-d H:i:s',
        'update_timestamp' => 'datetime:Y-m-d H:i:s'
    ];

    public static function GenerateNumber($pool_code, $ref_date)
    {
        $PREFIX = 'POG';
        return PagesHelp::GetDocseries($pool_code, $PREFIX, $ref_date);
    }

    public static function cancel_request($sysid)
    {
        $request = DB::table('t_purchase_order1')
            ->select('ref_document', 'purchase_request_id', 'doc_purchase_request')
            ->where('sysid', $sysid)
            ->first();

        if ($request && $request->purchase_request_id <> '-1') {
            self::updatePurchaseRequestOnCancel($request, $sysid);
        }
    }

    public static function update_request($sysid)
    {
        $request = DB::table('t_purchase_order1')
            ->select('ref_document', 'purchase_request_id', 'doc_purchase_request')
            ->where('sysid', $sysid)
            ->first();

        if ($request && $request->purchase_request_id <> '-1') {
            self::updatePurchaseRequestOnUpdate($request, $sysid);
        }
    }

    private static function updatePurchaseRequestOnCancel($request, $sysid)
    {
        DB::update("
            UPDATE t_purchase_request2 a
            INNER JOIN t_purchase_order2 b ON a.sysid = b.purchase_request_id AND a.line_no = b.purchase_line_no
            SET a.line_supply = IFNULL(a.line_supply, 0) - IFNULL(b.qty_order, 0), a.po_id = -1, a.item_status = 'On Request'
            WHERE a.sysid = ? AND b.sysid = ?
        ", [$request->purchase_request_id, $sysid]);

        DB::update("
            UPDATE t_purchase_request2 a
            INNER JOIN t_purchase_order1 b ON a.sysid = b.purchase_request_id
            SET a.po_number = b.doc_number, a.po_date = b.ref_date
            WHERE a.sysid = ? AND b.sysid = ?
        ", [$request->purchase_request_id, $sysid]);

        DB::update("
            UPDATE t_purchase_request1 a
            INNER JOIN t_purchase_order1 b ON a.sysid = b.purchase_request_id
            SET is_purchase_order = 1
            WHERE a.sysid = ? AND b.sysid = ?
        ", [$request->purchase_request_id, $sysid]);

        DB::update("
            UPDATE t_purchase_request1
            SET request_status = 'Open'
            WHERE sysid = ?
        ", [$request->purchase_request_id]);

        DB::update("
            UPDATE t_purchase_request1
            SET request_status = 'Partial'
            WHERE sysid = ? AND sysid IN (
                SELECT DISTINCT sysid
                FROM t_purchase_request2
                WHERE sysid = ? AND qty_request - (line_supply + line_cancel) <= 0
            )
        ", [$request->purchase_request_id, $request->purchase_request_id]);
    }

    private static function updatePurchaseRequestOnUpdate($request, $sysid)
    {
        DB::update("
            UPDATE t_purchase_request2 a
            INNER JOIN t_purchase_order2 b ON a.sysid = b.purchase_request_id AND a.line_no = b.purchase_line_no
            SET a.line_supply = IFNULL(a.line_supply, 0) + IFNULL(b.qty_order, 0), po_id = b.sysid, item_status = 'On Order'
            WHERE a.sysid = ? AND b.sysid = ?
        ", [$request->purchase_request_id, $sysid]);

        DB::update("
            UPDATE t_purchase_request2 a
            INNER JOIN t_purchase_order1 b ON a.sysid = b.purchase_request_id
            SET a.po_number = b.doc_number, a.po_date = b.ref_date
            WHERE a.sysid = ? AND b.sysid = ?
        ", [$request->purchase_request_id, $sysid]);

        DB::update("
            UPDATE t_purchase_request1 a
            INNER JOIN t_purchase_order1 b ON a.sysid = b.purchase_request_id
            SET is_purchase_order = 1
            WHERE a.sysid = ? AND b.sysid = ?
        ", [$request->purchase_request_id, $sysid]);

        DB::update("
            UPDATE t_purchase_request1
            SET request_status = 'Complete'
            WHERE sysid = ?
        ", [$request->purchase_request_id]);

        DB::update("
            UPDATE t_purchase_request1
            SET request_status = 'Partial'
            WHERE sysid = ? AND sysid IN (
                SELECT DISTINCT sysid
                FROM t_purchase_request2
                WHERE sysid = ? AND qty_request - (line_supply + line_cancel) > 0
            )
        ", [$request->purchase_request_id, $request->purchase_request_id]);
    }
}
