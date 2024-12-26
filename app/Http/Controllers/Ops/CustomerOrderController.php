<?php

namespace App\Http\Controllers\Ops;

use App\Models\Ops\CustomerOrder;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerOrderController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data = CustomerOrder::from('t_customer_order as a')
            ->select(
                'a.*',
                'b.work_order_no',
                'b.vehicle_no',
                'b.police_no',
                'b.driver_name',
                'b.work_status',
                'c.partner_name'
            )
            ->leftJoin('t_fleet_order as b', 'a.order_no', '=', 'b.order_no')
            ->leftJoin('m_partner as c', 'a.partner_id', '=', 'c.partner_id')
            ->where('a.order_date', '>=', $start_date)
            ->where('a.order_date', '<=', $end_date);
        if (!($pool_code == '')) {
            $data = $data->where('a.pool_code', $pool_code);
        }
        if (!($filter == '')) {
            if ((strtolower($filter)=='open') || (strtolower($filter)=='close')){
                if (strtolower($filter)=='open') {
                    $data=$data->where('is_transactionlink','0');
                } else if (strtolower($filter)=='close') {
                    $data=$data->where('is_transactionlink','1');
                }
            } else{
                $filter = '%' . trim($filter) . '%';
                $data = $data->where(function ($q) use ($filter) {
                    $q->where('b.work_order_no', 'like', $filter)
                        ->orwhere('a.origins', 'like', $filter)
                        ->orwhere('a.destination', 'like', $filter)
                        ->orwhere('c.partner_name', 'like', $filter)
                        ->orwhere('a.customer_no', 'like', $filter);
                });
            }
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

    public function destroy(Request $request)
    {
        $id = $request->transid;
        $data = CustomerOrder::from('t_customer_order as a')
            ->select('a.is_transactionlink', 'a.order_no', 'b.work_order_no')
            ->leftJoin('t_fleet_order as b', 'a.order_no', '=', 'b.order_no')
            ->where('a.transid', $id)
            ->first();
        if ($data->is_transactionlink == 1) {
            return response()->error('', 501, 'Order ' . $data->order_no . ' sudah diproses dengan nomor SPJ ' . $data->work_order_no);
        }
        DB::beginTransaction();
        try {
            $data = CustomerOrder::where('transid', $id);
            if (!($data == null)) {
                $data->delete();
                DB::commit();
                return response()->success('Success', 'Order berhasil dihapus');
            } else {
                DB::rollback();
                return response()->error('', 501, 'Data tidak ditemukan');
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public function get(Request $request)
    {
        $id = $request->id;
        $data = CustomerOrder::from('t_customer_order as a')
            ->select('a.*', 'c.partner_name')
            ->leftJoin('m_partner as c', 'a.partner_id', '=', 'c.partner_id')
            ->where('transid', $id)->first();
        return response()->success('Success', $data);
    }
    public function post(Request $request)
    {
        $data = $request->json()->all();
        $opr = $data['operation'];
        $where = $data['where'];
        $rec = $data['data'];
        $validator = Validator::make($rec, [
            'entry_date' => 'required',
            'order_date' => 'required',
            'order_time' => 'required',
            'pool_code' => 'required',
            'partner_id' => 'required',
            'vehicle_type' => 'required',
            'origins' => 'required',
            'destination' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->all());
        }
        if ($opr == 'updated') {
            $data = CustomerOrder::from('t_customer_order as a')
                ->select('a.is_transactionlink', 'a.order_no', 'b.work_order_no')
                ->leftJoin('t_fleet_order as b', 'a.order_no', '=', 'b.order_no')
                ->where('a.transid', $rec['transid'])
                ->first();
            if ($data->is_transactionlink == 1) {
                return response()->error('', 501, 'Order ' . $data->order_no . ' sudah diproses dengan nomor SPJ ' . $data->work_order_no);
            }
        }
        DB::beginTransaction();
        try {
            $sysid = $rec['transid'];
            if ($opr == 'updated') {
                CustomerOrder::where($where)
                    ->update($rec);
            } else if ($opr = 'inserted') {
                $rec['transid']  = CustomerOrder::max('transid') + 1;
                $rec['order_no'] = CustomerOrder::GenerateNumber($rec['entry_date']);
                $sysid = CustomerOrder::insertGetId($rec);
            }
            DB::commit();
            return response()->success('Success', 'Simpan data Berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public function cancel(Request $request)
    {
        $userid = PagesHelp::UserID($request);
        $data = $request->json()->all();
        $where = $data['where'];
        $rec = $data['data'];

        $validator = Validator::make($rec, [
            'notes' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->all());
        }
        $data = CustomerOrder::from('t_customer_order as a')
            ->select('a.is_transactionlink', 'a.order_no', 'b.work_order_no')
            ->leftJoin('t_fleet_order as b', 'a.order_no', '=', 'b.order_no')
            ->where('a.transid', $where['transid'])
            ->first();
        if ($data->is_transactionlink == 1) {
            return response()->error('', 501, 'Order ' . $data->order_no . ' sudah diproses dengan nomor SPJ ' . $data->work_order_no);
        }
        DB::beginTransaction();
        try {
            CustomerOrder::where($where)
                ->update([
                    'cancel_note' => $rec['notes'],
                    'cancel_userid' => $userid,
                    'order_status' => 'Cancel',
                    'cancel_date' => date('Y-m-d H:i:s')
                ]);
            DB::commit();
            return response()->success('Success', 'Order berhasil dibatalkan');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public function summary1(Request $request)
    {
        $years = $request->year;
        $bulan = array(
            'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Juni',
            'Juli', 'Agst', 'Sept', 'Okt', 'Nov', 'Des'
        );
        $data = DB::table('t_customer_order')
            ->select(DB::raw('MONTH(entry_date) as id,COUNT(*) AS joborder,ROUND(SUM(standart_price)/1000000,0) AS revenue,ROUND(SUM(fleet_cost)/1000000,0)  AS cost'))
            ->whereRaw("YEAR(entry_date)=? AND IFNULL(order_status,'')<>'?'", [$years, 'Cancel'])
            ->groupBy(DB::raw('YEAR(entry_date),MONTH(entry_date)'))
            ->get();
        $info = array();
        foreach ($data as $value) {
            $row = (array) $value;
            $row['id'] = $bulan[$row['id'] - 1];
            $row['revenue'] = floatval($row['revenue']);
            $row['cost'] = floatval($row['cost']);
            $info[] = $row;
        }
        $summary['yearly'] = $info;
        $sum = DB::table('t_customer_order')
            ->select('partner_id', DB::raw('SUM(standart_price) AS omset'))
            ->whereRaw("YEAR(entry_date)=? AND IFNULL(order_status,'')<>'?'", [$years, 'Cancel'])
            ->groupBy('partner_id');
        $top15 = DB::table('m_partner', 'a')
            ->joinSub($sum, 'b', function ($join) {
                $join->on('a.partner_id', '=', 'b.partner_id');
            })
            ->select('a.partner_id', 'a.partner_name', 'b.omset')
            ->limit(15)
            ->orderBy('b.omset', 'desc')
            ->get();
        $summary['top15'] = $top15;
        return response()->success('Success', $summary);
        /*SELECT a.partner_id,a.partner_name,b.omset FROM m_partner a
INNER JOIN
(SELECT partner_id,SUM(standart_price) AS omset FROM t_customer_order
WHERE YEAR(order_date)=2019
GROUP BY partner_id
ORDER BY omset DESC
LIMIT 15) b ON a.partner_id=b.partner_id */
    }
}
