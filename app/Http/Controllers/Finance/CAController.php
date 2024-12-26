<?php

namespace App\Http\Controllers\Finance;

use App\Models\Master\Partner;
use App\Models\Finance\CustomerAccount;
use App\Models\Purchase\JobInvoice1;
use App\Models\Purchase\JobInvoice2;
use App\Models\Inventory\ItemInvoice1;
use App\Models\Inventory\ItemInvoice2;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use App\Models\Config\Users;
use Illuminate\Support\Str;
use PDF;

class CAController extends Controller
{
    public function show(Request $request) {
        $filter = $request->filter;
        $limit = $request->limit ?? 10; // Set a default limit
        $sorting = $request->descending === "true" ? 'desc' : 'asc';
        $sortBy = $request->sortBy ?? 'a.ref_date'; // Set a default sortBy field
        $date1 = $request->date1;
        $date2 = $request->date2;
        $approved = $request->approved ?? '0';

        // Start the query
        $data = CustomerAccount::from("t_customer_account as a")
            ->selectRaw("
                a.sysid, a.ref_sysid, a.doc_source, a.doc_number, a.reference,
                a.ref_date, a.due_date, a.partner_id, a.amount, a.total_paid,
                a.tax, a.discount, a.last_payment, a.doc_payment, a.is_void,
                a.is_approved, a.approved_by, a.approved_date, a.is_paid,
                b.partner_name, invoice_path, '0' as is_approve, a.uuid_rec,
                a.uuid_invoice, doc_name
            ")
            ->leftJoin("m_partner as b", "a.partner_id", "=", "b.partner_id");

        // Apply filters for approval and date range
        if ($approved === '1') {
            $data->where('a.is_approved', '0')
                ->where('a.is_void', '0');
        } else {
            $data->whereBetween('a.ref_date', [$date1, $date2])
                ->where('a.is_void', '0');
        }

        // Exclude records with an amount of zero
        $data->where('a.amount', '<>', '0');

        // Apply text filters if provided
        if (!empty($filter)) {
            $filterPattern = '%' . trim($filter) . '%';
            $data->where(function ($query) use ($filterPattern) {
                $query->where('a.doc_number', 'like', $filterPattern)
                    ->orWhere('b.partner_name', 'like', $filterPattern)
                    ->orWhere('a.reference', 'like', $filterPattern);
            });
        }

        // Set ordering and pagination
        $data = $data->orderBy($sortBy, $sorting)
                    ->paginate($limit);

        return response()->success('Success', $data);
    }


    public function post(Request $request){
        $uuid=$request->uuid ?? '';
        DB::beginTransaction();
        try{
            $ca=CustomerAccount::where('uuid_rec',$uuid)->first();
            if (!$ca){
                DB::rollback();
                return response()->error('',501,'Data Customer Account tidak ditemukan');
            }
            $ca->is_approved   = '1';
            $ca->approved_by   = PagesHelp::Session()->user_id;
            $ca->approved_date = Date('Y-m-d H:i:s');
            $ca->save();

            DB::commit();
            $respon=[
                'uuid'=>$uuid,
                'message'=>"Simpan data berhasil"
            ];
            return response()->success('Success', $respon);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function post_all(Request $request) {
        $data = $request->json('data');
        DB::beginTransaction();
        try {
            foreach ($data as $row) {
                $uuid = $row['uuid'];
                $verified = $row['verified'];

                if ($verified === '1') {
                    CustomerAccount::where('uuid_rec', $uuid)
                        ->where('is_approved', '0')
                        ->update([
                            'is_approved' => '1',
                            'approved_by' => PagesHelp::UserID($request),
                            'approved_date' => now()
                        ]);
                }
            }

            DB::commit();
            return response()->success('Success', [
                'uuid' => '',
                'message' => "Simpan data berhasil"
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->error('Terjadi kesalahan saat menyimpan data', 501, $e->getMessage());
        }
    }

    public function print(Request $request){
        $uuid= $request->uuid ?? '';
        $doc=CustomerAccount::selectRaw("sysid,ref_sysid,doc_source,doc_number")->where('uuid_rec',$uuid)->first();
        if ($doc){
            if ($doc->doc_source=='LPB'){
                return CAController::printinvoice($doc->ref_sysid);
            } else if ($doc->doc_source=='SPK'){
               return CAController::printSPK($doc->ref_sysid);
            }
        }
    }

    static function printinvoice($sysid = -1) {
        $header = ItemInvoice1::from('t_item_invoice1 as a')
            ->selectRaw("
                a.sysid, a.doc_number, a.ref_document, a.order_document, a.partner_name,
                a.ref_date, a.due_date, a.amount, a.discount1, a.tax,
                a.total as net_total, concat(a.trans_code, '-', a.trans_series) as voucher,
                a.pool_code, a.update_timestamp, b.line_no, b.item_code, b.descriptions,
                b.mou_purchase, b.qty_invoice, b.purchase_price, b.prc_discount1, b.prc_tax,
                b.total, IFNULL(c.part_number, '') as part_number, d.user_name,
                IFNULL(e.descriptions, '') as warehouse_name
            ")
            ->leftJoin('t_item_invoice2 as b', 'a.sysid', '=', 'b.sysid')
            ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
            ->leftJoin('o_users as d', 'a.update_userid', '=', 'd.user_id')
            ->leftJoin('m_warehouse as e', 'a.warehouse_id', '=', 'e.warehouse_id')
            ->where('a.sysid', $sysid)
            ->orderBy('b.line_no', 'asc')
            ->get();

        // Check if data is found
        if ($header->isEmpty()) {
            return response()->error('Data tidak ditemukan', 501);
        }

        // Format dates
        $header[0]->ref_date = date('d-m-Y', strtotime($header[0]->ref_date));
        $header[0]->due_date = date('d-m-Y', strtotime($header[0]->due_date));

        // Get profile and user signature
        $profile = PagesHelp::Profile();
        $homePath =  storage_path().'/app/';
        $user = Users::selectRaw("IFNULL(sign, '') as sign")
                    ->where('user_id', $header[0]->update_userid)
                    ->first();

        $sign = ['user_sign' => $user && $user->sign ? $homePath . $user->sign : ''];

        // Generate PDF
        $pdf = PDF::loadView('finance.invoice', [
            'header' => $header,
            'profile' => $profile,
            'sign' => $sign
        ])->setPaper('A4', 'portrait');

        return $pdf->stream();
    }

    static function printSPK($sysid = 1) {
        // Fetch header and item details
        $header = JobInvoice1::from('t_job_invoice1 as a')
            ->selectRaw("
                a.sysid, a.doc_number, a.ref_document, a.partner_name,
                a.ref_date, a.due_date, a.amount, a.discount, a.tax,
                a.total as net_total, CONCAT(a.trans_code, '-', a.trans_series) AS voucher,
                a.pool_code, IFNULL(a.vehicle_no, '') AS vehicle_no, a.service_no,
                c.user_name, a.update_timestamp, b.line_no, b.descriptions,
                b.qty_invoice, b.price, b.discount, b.total
            ")
            ->leftJoin('t_job_invoice2 as b', 'a.sysid', '=', 'b.sysid')
            ->leftJoin('o_users as c', 'a.update_userid', '=', 'c.user_id')
            ->where('a.sysid', $sysid)
            ->orderBy('b.line_no', 'asc')
            ->get();

        // Check if data is found
        if ($header->isEmpty()) {
            return response()->error('Data tidak ditemukan', 501);
        }

        // Format dates
        $header[0]->ref_date = date('d-m-Y', strtotime($header[0]->ref_date));
        $header[0]->due_date = date('d-m-Y', strtotime($header[0]->due_date));

        // Get profile and user signature
        $profile = PagesHelp::Profile();
        $homePath = storage_path().'/app/';
        $user = Users::selectRaw("IFNULL(sign, '') as sign")
                    ->where('user_id', $header[0]->update_userid)
                    ->first();

        $sign = ['user_sign' => $user && $user->sign ? $homePath . $user->sign : ''];

        // Generate PDF
        $pdf = PDF::loadView('finance.invoicejob', [
            'header' => $header,
            'profile' => $profile,
            'sign' => $sign
        ])->setPaper('A4', 'portrait');

        return $pdf->stream();
    }

    public function upload(Request $request) {
        $uuid = $request->uuid ?? '';
        $userid = PagesHelp::Session()->user_id;

        try {
            // Check if file is uploaded
            if ($uploadedFile = $request->file('pdf')) {
                $originalFileName = now()->format('YmdHis') . '_' . $uploadedFile->getClientOriginalName();
                $originalDocName = $uploadedFile->getClientOriginalName();
                $directory = "public/invoice/" . now()->year;
                $path = $uploadedFile->storeAs($directory, $originalFileName);
                $fullPath = $directory . '/' . $originalFileName;

                // Update CustomerAccount with file path and original document name
                CustomerAccount::where('uuid_rec', $uuid)->update([
                    'invoice_path' => $fullPath,
                    'doc_name' => $originalDocName
                ]);

                // Update associated records based on doc_source
                $ap = CustomerAccount::selectRaw("ref_sysid, doc_source")
                    ->where('uuid_rec', $uuid)
                    ->first();

                if ($ap) {
                    $updateData = [
                        'invoice_path' => $fullPath,
                        'doc_name' => $originalDocName
                    ];

                    if ($ap->doc_source === 'LPB') {
                        ItemInvoice1::where('sysid', $ap->ref_sysid)->update($updateData);
                    } elseif ($ap->doc_source === 'SPK') {
                        JobInvoice1::where('sysid', $ap->ref_sysid)->update($updateData);
                    }
                }

                return response()->success('Success', 'Upload dokumen invoice berhasil');
            }

            return response()->error('No file uploaded', 400);

        } catch (\Exception $e) {
            return response()->error('Upload failed', 501, $e->getMessage());
        }
    }

    public function download(Request $request) {
        $uuid = $request->uuid ?? '';

        // Retrieve file details
        $data = CustomerAccount::selectRaw("invoice_path, doc_name")
            ->where('uuid_rec', $uuid)
            ->first();

        if ($data) {
            return Storage::download($data->invoice_path, $data->doc_name);
        } else {
            return response()->error('Dokumen tidak ditemukan', 404);
        }
    }

}
