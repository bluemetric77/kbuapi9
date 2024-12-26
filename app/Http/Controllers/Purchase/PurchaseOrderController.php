<?php

namespace App\Http\Controllers\Purchase;

use App\Models\Purchase\PurchaseOrder1;
use App\Models\Purchase\PurchaseOrder2;
use App\Models\Master\Partner;
use App\Models\Inventory\PurchaseRequest1;
use App\Models\Inventory\PurchaseRequest2;
use App\Models\Inventory\ItemInvoice1;
use App\Models\Inventory\ItemInvoice2;
use App\Models\Inventory\ItemPartner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use App\Models\Config\Users;
use PDF;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PurchaseOrderController extends Controller
{
    public function show(Request $request){
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ?'desc':'asc';
        $sortBy  = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $date1 = $request->date1;
        $date2 = $request->date2;
        $isopen= isset($request->isopen) ? $request->isopen:'0';
        $isapproved = isset($request->approved) ? $request->approved:'0';
        $isAll = isset($request->all) ? $request->all:'0';

        $data= PurchaseOrder1::selectRaw("sysid,doc_number,document_status,ref_date,validate_date,ref_document,
        doc_purchase_request,total,partner_code,partner_name,is_draft,is_posted,is_cancel,canceled_by,canceled_date,
        posted_date,project_title,posted_by,approved_by1,approved_date1,approved_date2,approved_by2,pool_code,warehouse_id,
        doc_name,uuid_rec");

        if ($isapproved=='1') {
            $data=$data
            ->where('document_status','<>','C')
            ->where('is_posted','1');
        } else if ($isopen=='0') {
            $data=$data->where('is_posted','0')
                ->where('is_cancel','0')
                ->where('document_status','<>','C');
        } else {
            $data=$data
                ->where('ref_date','>=',$date1)
                ->where('ref_date','<=',$date2);
        }
        if ($isAll=='0'){
            $data=$data->where('pool_code',$pool_code);
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('partner_name','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request)
    {
        $uuid = $request->uuid ?? ''; // Use null coalescing operator for cleaner syntax
        $po = PurchaseOrder1::where('uuid_rec', $uuid)->first();

        // Check if Purchase Order exists
        if (!$po) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        $sysid = $po->sysid;

        // Check for associated invoices
        $invoice = ItemInvoice1::where('order_sysid', $sysid)
            ->where('is_void', 0)
            ->first();
        if ($invoice) {
            return response()->error('', 202, 'Order pembelian tidak bisa dibatalkan, sudah ada penerimaan');
        }

        // Check posting and cancellation status
        if ($po->is_posted == '1') {
            return response()->error('', 201, 'Order pembelian sudah diposting');
        } elseif ($po->is_cancel == '1') {
            return response()->error('', 202, 'Order pembelian sudah dibatalkan');
        }
        $session=PagesHelp::Session();

        DB::beginTransaction();
        try {
            // Cancel the Purchase Order
            PurchaseOrder1::where('sysid', $sysid)->update([
                'is_cancel' => 1,
                'canceled_by' => $session->user_id,
                'canceled_date' => Date('Y-m-d H:i:s'),
                'document_status' => 'C'
            ]);

            // Update associated Purchase Order lines
            PurchaseOrder2::where('sysid', $sysid)->update([
                'line_state' => "C",
                'qty_cancel' => DB::raw("qty_order")
            ]);

            DB::commit();
            return response()->success('Success', 'Order pembelian berhasil dibatalkan');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage()); // Return error message instead of exception object
        }
    }

    public function posting(Request $request)
    {
        $data = $request->json()->all();
        $uuid = $data['uuid'];
        $level = $data['level'];
        $detail = $data['detail'];
        $userId = PagesHelp::UserID($request);

        // Retrieve purchase order information
        $po = PurchaseOrder1::selectRaw('sysid, is_cancel, is_posted')
            ->where('uuid_rec', $uuid)
            ->first();

        if (!$po) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        $sysid = $po->sysid;

        // Check if there is an associated invoice
        $invoice = ItemInvoice1::where('order_sysid', $sysid)->first();
        if ($invoice) {
            return response()->error('', 202, 'Order pembelian tidak bisa batal posting, sudah ada penerimaan');
        }

        // Check order cancellation and posting status
        if ($po->is_cancel == '1') {
            return response()->error('', 202, 'Permintaan pembelian sudah dibatalkan');
        }
        if ($po->is_posted == '1') {
            return response()->error('', 201, 'Permintaan pembelian sudah diposting/disetujui');
        }

        DB::beginTransaction();
        try {
            // Update purchase order based on approval level
            $this->approvePurchaseOrder($level, $sysid, $userId);

            // Check if the order is posted and update line items
            if (PurchaseOrder1::where('sysid', $sysid)->where('is_posted', '1')->exists()) {
                foreach ($detail as $record) {
                    PurchaseOrder2::where('sysid', $sysid)
                        ->where('line_no', $record['line_no'])
                        ->update([
                            'qty_order' => $record['qty_order'],
                            'total' => $record['total'],
                            'posted_date' => now(), // Use Carbon for better date handling
                            'posted_userid' => $userId,
                            'is_posted' => '1'
                        ]);
                }
            }

            DB::commit();
            return response()->success('Success', 'Order pembelian berhasil di-approved/disetujui');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage()); // Return a specific error message
        }
    }

    // Helper method to handle purchase order approval logic
    private function approvePurchaseOrder($level, $sysid, $userId)
    {
        switch ($level) {
            case 'MANAGER':
                PurchaseOrder1::where('sysid', $sysid)
                    ->whereRaw('posted_date IS NULL')
                    ->update([
                        'posted_date' => now(),
                        'posted_by' => $userId
                    ]);
                DB::update("UPDATE t_purchase_order1 SET is_posted = 1 WHERE sysid = ? AND approved_level = 'MANAGER'", [$sysid]);
                break;

            case 'GENERAL MANAGER':
                PurchaseOrder1::where('sysid', $sysid)
                    ->whereRaw('approved_date1 IS NULL')
                    ->update([
                        'approved_date1' => now(),
                        'approved_by1' => $userId
                    ]);
                DB::update("UPDATE t_purchase_order1 SET is_posted = 1 WHERE sysid = ? AND approved_level = 'GENERAL MANAGER'", [$sysid]);
                break;

            case 'DIREKTUR':
                PurchaseOrder1::where('sysid', $sysid)
                    ->whereRaw('approved_date2 IS NULL')
                    ->update([
                        'approved_date2' => now(),
                        'approved_by2' => $userId,
                        'is_posted' => '1'
                    ]);
                break;

            default:
                throw new Exception('Invalid approval level');
        }
    }


    public function unposting(Request $request)
    {
        $uuid = $request->uuid ?? ''; // Use null coalescing operator for cleaner syntax
        $po = PurchaseOrder1::selectRaw('sysid, is_cancel, is_posted')->where('uuid_rec', $uuid)->first();

        // Check if the Purchase Order exists
        if (!$po) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        $sysid = $po->sysid;

        // Check for associated invoices
        $invoice = ItemInvoice1::where('order_sysid', $sysid)->where('is_void', 0)->first();
        if ($invoice) {
            return response()->error('', 202, 'Order pembelian tidak bisa batal posting, sudah ada penerimaan');
        }

        // Check if the order has already been canceled
        if ($po->is_cancel == '1') {
            return response()->error('', 202, 'Permintaan pembelian sudah dibatalkan');
        }

        DB::beginTransaction();
        try {
            // Update the Purchase Order and its lines
            $this->resetPurchaseOrder($sysid);

            DB::commit();
            return response()->success('Success', 'Proses pembatalan approve/persetujuan dokumen berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage()); // Return a specific error message
        }
    }

    // Helper method to reset the purchase order status
    private function resetPurchaseOrder($sysid)
    {
        PurchaseOrder1::where('sysid', $sysid)
            ->update([
                'is_posted' => 0,
                'is_draft' => 1,
                'posted_by' => '',
                'posted_date' => null,
                'approved_by1' => '',
                'approved_date1' => null,
                'approved_by2' => '',
                'approved_date2' => null,
            ]);

        PurchaseOrder2::where('sysid', $sysid)
            ->update([
                'posted_date' => null,
                'posted_userid' => '',
                'is_posted' => '0'
            ]);
    }

    public function get(Request $request)
    {
        $uuid = $request->uuid ?? '';

        // Retrieve the purchase order header
        $header = PurchaseOrder1::selectRaw("sysid, pool_code, doc_number, document_status, warehouse_id, ref_date,
            validate_date, ref_document, purchase_request_id, doc_purchase_request, partner_code, partner_name, descriptions,
            is_draft, is_posted, is_cancel, consignment, required_date, purchase_instruction, project_title, total,
            approved_level, is_ho, posted_date, posted_by, approved_by1, approved_date1, approved_date2, approved_by2, document,
            uuid_rec")
            ->where('uuid_rec', $uuid)
            ->first();

        // Initialize response data
        $data = [
            'is_posted' => false,
            'is_cancel' => false,
            'message' => ''
        ];

        // Check if the purchase order exists
        if (!$header) {
            return response()->error('', 501, 'Data Tidak ditemukan');
        }

        // Determine order status and set appropriate messages
        if ($header->is_posted == '1') {
            $data['message'] = 'Order pembelian sudah diposting';
            $data['is_posted'] = true;
        } elseif ($header->is_cancel == '1') {
            $data['message'] = 'Order pembelian sudah dibatalkan';
            $data['is_cancel'] = true;
        } elseif ($header->is_posted == '0' && ($header->posted_by || $header->approved_by1)) {
            $data['message'] = 'Order pembelian dalam proses persetujuan';
            $data['is_posted'] = true;
        }

        // Format the document URL if available
        $header->document = $header->document ? PagesHelp::my_server_url() . '/' . $header->document : '';

        $data['header'] = $header;

        // Retrieve the purchase order details
        $data['detail'] = PurchaseOrder2::from('t_purchase_order2 as a')
            ->select('a.sysid', 'a.line_no', 'a.item_code', 'b.part_number', 'a.descriptions', 'a.qty_draft', 'a.mou_purchase',
                'a.convertion', 'a.mou_warehouse', 'a.price', 'a.prc_discount1', 'a.prc_discount2', 'a.prc_tax', 'a.total',
                'a.source_line', 'a.line_type', 'a.qty_request', 'a.qty_order', 'a.current_stock')
            ->leftJoin('m_item as b', 'a.item_code', '=', 'b.item_code')
            ->where('sysid', $header->sysid)
            ->get();

        // Retrieve the user level description
        $userId = PagesHelp::Session()->user_id;
        $userLevel = DB::table('o_users as a')
            ->selectRaw("IFNULL(b.descriptions, 'N/A') as descriptions")
            ->leftJoin('o_users_level as b', 'a.user_level', '=', 'b.sysid')
            ->where('a.user_id', $userId)
            ->first();

        $data['user_level'] = $userLevel->descriptions ?? $userId; // Use null coalescing operator

        return response()->success('Success', $data);
    }

    public function post(Request $request)
    {
        $data = $request->json()->all();
        $header = $data['header'];
        $detail = $data['detail'];

        $session = PagesHelp::Session();

        // Set pool code and warehouse ID
        $header['pool_code'] = $session->pool_code;
        $header['warehouse_id'] = $session->warehouse_code;

        if ($header['is_ho'] === '1') {
            $header['pool_code'] = 'HO';
            $header['warehouse_id'] = 'HO';
        }

        // Validate header
        $headerValidator = Validator::make($header, [
            'ref_date' => 'bail|required|date',
            'pool_code' => 'bail|required',
            'warehouse_id' => 'bail|required',
            'partner_code' => 'required'
        ], [
            'ref_date.required' => 'Tanggal harus diisi',
            'pool_code.required' => 'Pool harus diisi',
            'warehouse_id.required' => 'Gudang harus diisi',
            'partner_code.required' => 'Supplier harus diisi'
        ]);

        if ($headerValidator->fails()) {
            return response()->error('', 501, $headerValidator->errors()->first());
        }

        // Validate detail
        $detailValidator = Validator::make($detail, [
            '*.item_code' => 'bail|required|distinct|exists:m_item,item_code',
            '*.qty_draft' => 'bail|required|numeric|min:1',
            '*.price' => 'bail|required|numeric|min:1',
            '*.prc_discount1' => 'bail|required|numeric|min:0|max:100',
            '*.prc_discount2' => 'bail|required|numeric|min:0|max:100',
            '*.prc_tax' => 'bail|required|numeric|min:0|max:100'
        ], [
            '*.item_code.required' => 'Kode barang harus diisi',
            '*.item_code.exists' => 'Kode barang :input tidak ditemukan di master',
            '*.qty_draft.min' => 'Jumlah permintaan harus lebih besar dari NOL',
            '*.price.min' => 'Harga pembelian tidak boleh NOL',
            '*.item_code.distinct' => 'Kode barang :input terduplikasi (terinput lebih dari 1)',
        ]);

        if ($detailValidator->fails()) {
            return response()->error('', 501, $detailValidator->errors()->first());
        }

        // Calculate total and check limits
        $total = array_sum(array_column($detail, 'total'));
        if ($total <= 0) {
            return response()->error('', 501, "Total PO tidak boleh NOL");
        }
        if ($total > 3000000 && $header['is_ho'] === '0') {
            return response()->error('', 501, "Order pembelian diatas Rp.3.000.000,- harus melalui Head Office");
        }

        DB::beginTransaction();
        try {
            $po = PurchaseOrder1::where('uuid_rec', $header['uuid_rec'] ?? '')->first();

            // Check if the Purchase Order exists or create a new one
            if (!$po) {
                $cur_date = date('Y-m-d');
                if ($header['ref_date'] < $cur_date) {
                    return $this->handleTransactionRollback('Tanggal transaksi tidak bisa mundur');
                }

                $po = new PurchaseOrder1();
                $po->uuid_rec = Str::uuid();
                $po->doc_number = PurchaseOrder1::GenerateNumber($header['pool_code'], $header['ref_date']);
                $po->document_status = 'O';
                $po->is_draft = '1';
                $po->is_posted = '0';
                $po->is_cancel = '0';
                $po->is_printed = '0';
            } else {
                if ($po->is_posted === '1' || $po->is_cancel === '1') {
                    return $this->handleTransactionRollback('Data tidak bisa diupdate, PO sudah diposting/dibatalkan');
                }
                if ($po->ref_date > $header['ref_date']) {
                    return $this->handleTransactionRollback('Tanggal transaksi tidak bisa mundur');
                }
                PurchaseOrder2::where('sysid', $po->sysid)->delete();
            }

            // Update PO header details
            $partner = Partner::select('partner_name')->where('partner_id', $header['partner_code'])->first();
            $po->fill([
                'ref_date' => $header['ref_date'],
                'validate_date' => $header['validate_date'],
                'ref_document' => $header['ref_document'],
                'project_title' => $header['project_title'],
                'partner_code' => $header['partner_code'],
                'partner_name' => $partner->partner_name ?? '',
                'pool_code' => $header['pool_code'],
                'warehouse_id'=>$header['warehouse_id'],
                'purchase_request_id' => $header['purchase_request_id'],
                'doc_purchase_request' => $header['doc_purchase_request'],
                'purchase_instruction' => $header['purchase_instruction'],
                'total' => $header['total'],
                'is_ho' => $header['is_ho'],
                'update_timestamp' => now()
            ]);
            $po->save();
            $sysid = $po->sysid;

            // Insert PO details
            foreach ($detail as $rec) {
                PurchaseOrder2::create([
                    'sysid' => $sysid,
                    'line_no' => $rec['line_no'],
                    'warehouse_id' => $po->warehouse_id,
                    'received_no' => '-1',
                    'is_posted' => '0',
                    'item_code' => $rec['item_code'],
                    'descriptions' => $rec['descriptions'],
                    'mou_purchase' => $rec['mou_purchase'],
                    'mou_warehouse' => $rec['mou_warehouse'],
                    'convertion' => $rec['convertion'],
                    'qty_draft' => $rec['qty_draft'],
                    'qty_order' => $rec['qty_draft'],
                    'price' => $rec['price'],
                    'prc_discount1' => $rec['prc_discount1'],
                    'prc_discount2' => $rec['prc_discount2'],
                    'prc_tax' => $rec['prc_tax'],
                    'total' => $rec['total'],
                    'line_type' => $rec['line_type'],
                    'source_line' => $rec['source_line'],
                    'qty_request' => $rec['qty_request'],
                    'current_stock' => $rec['current_stock'] ?? 0,
                    'purchase_request_id' => $po->purchase_request_id,
                    'purchase_line_no' => $rec['purchase_line_no'] ?? -1,
                ]);
            }

            // Determine approved level
            $approved_level = $this->determineApprovedLevel($header['total']);
            $po->approved_level = $approved_level;
            $po->save();

            PurchaseOrder1::update_request($sysid);
            DB::commit();

            return response()->success('Success', 'Simpan data Berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }

    /**
     * Handle transaction rollback and return error response.
     */
    protected function handleTransactionRollback($message)
    {
        DB::rollback();
        return response()->error('Gagal', 501, $message);
    }

    /**
     * Determine the approved level based on the total amount.
     */
    protected function determineApprovedLevel($total)
    {
        $app_v = DB::table('o_users_level')
        ->select('descriptions', 'amount')
        ->orderBy('amount', 'desc')
        ->get();

        $approved_level = 'STAFF';
        foreach ($app_v as $row) {
            if (floatval($total) <= floatval($row->amount)) {
                $approved_level = $row->descriptions;
            }
        }
        return $approved_level;
    }

    public function print(Request $request) {
        $uuid = $request->uuid ?? null; // Use null coalescing operator

        if (!$uuid) {
            return response()->error('', 501, 'UUID is required');
        }

        // Fetching the purchase order header
        $header = PurchaseOrder1::from('t_purchase_order1 as a')
            ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.ref_date,a.validate_date,a.partner_name,
                        IFNULL(a.purchase_instruction,'') as purchase_instruction,
                        b.line_no,b.descriptions,IFNULL(b.mou_purchase,'') as mou_purchase,
                        b.qty_order,b.price,b.prc_discount1,b.prc_discount2,b.prc_tax,
                        b.total,IFNULL(c.part_number,'') as part_number,
                        d.user_name,b.current_stock,IFNULL(a.descriptions,'') as po_notes,
                        e.partner_address,e.phone_number,e.contact_person,
                        a.is_ho,a.is_posted,
                        IFNULL(a.posted_by,'') as approved_by0,
                        IFNULL(a.approved_by1,'') as approved_by1,
                        IFNULL(a.approved_by2,'') as approved_by2,
                        a.posted_date,a.approved_date1,a.approved_date2")
            ->leftJoin('t_purchase_order2 as b', 'a.sysid', '=', 'b.sysid')
            ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
            ->leftJoin('o_users as d', 'a.update_userid', '=', 'd.user_id')
            ->leftJoin('m_partner as e', 'a.partner_code', '=', 'e.partner_id')
            ->where('a.uuid_rec', $uuid)
            ->get();

        // Check if the header is empty
        if ($header->isEmpty()) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        // Fetch signatures
        $sign = [
            'manager' => '',
            'general_manager' => '',
            'director' => ''
        ];

        $home = storage_path().'/app/';
        $approvers = [
            'approved_by0' => 'manager',
            'approved_by1' => 'general_manager',
            'approved_by2' => 'director'
        ];

        foreach ($approvers as $field => $role) {
            $user = Users::selectRaw("IFNULL(sign,'') as sign")->where('user_id', $header[0]->$field)->first();
            if ($user) {
                $sign[$role] = ($user->sign=='') ? '' : $home . $user->sign;
            }
        }

        // Format dates
        $header[0]->ref_date = date_format(date_create($header[0]->ref_date), 'd-m-Y');
        $header[0]->validate_date = date_format(date_create($header[0]->validate_date), 'd-m-Y');

        // Load profile and PDF
        $profile = PagesHelp::Profile();

        try {
            $pdf = PDF::loadView('purchase.po', [
                'header' => $header,
                'profile' => $profile,
                'sign' => $sign
            ])->setPaper('A4', 'portrait');

            return $pdf->stream();
        } catch (\Exception $e) {
            return response()->error('', 500, 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function request(Request $request){
        $pool_code=PagesHelp::PoolCode($request);
        $data=PurchaseRequest1::select('sysid','doc_number',DB::raw("CONCAT(doc_number,' - ',IFNULL(descriptions,'')) as descriptions"))
        ->where('pool_code',$pool_code)
        ->where('is_posted','1')
        ->where('request_status','<>','Complete')
        ->where('is_cancel','0')
        ->orderBy('ref_date','desc')
        ->offset(0)
        ->limit(200)
        ->get();
        return response()->success('Success',$data);
    }

    public function dtlrequest(Request $request){
        $doc_number=$request->doc_number;
        $all=isset($request->all)?$request->all:'0';
        $data=PurchaseRequest1::from('t_purchase_request1 as a')
        ->select('b.line_no','b.item_code','c.part_number','b.descriptions','b.mou_purchase','b.convertion','b.mou_inventory','b.qty_request','b.line_supply','b.current_stock')
        ->leftJoin('t_purchase_request2 as b', 'a.sysid', '=', 'b.sysid')
        ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
        ->where('a.doc_number',$doc_number);
        if ($all=='0') {
            $data=$data->whereRaw('IFNULL(b.qty_request,0)>IFNULL(line_supply,0)');
        }
        $data=$data->get();
        return response()->success('Success',$data);
    }

    public function external(Request $request){
        $pool_code=PagesHelp::PoolCode($request);
        $data=ServiceExternal::selectRaw("sysid,doc_number,CONCAT(vehicle_no,' - ',police_no,'   [',doc_number,']') AS list")
        ->where('pool_code',$pool_code)
        ->where('is_closed','0')
        ->orderBy('ref_date','desc')
        ->offset(0)
        ->limit(100)
        ->get();
        return response()->success('Success',$data);
    }

    public function upload_document(Request $request)
    {
        $uuid = $request->uuid ?? ''; // Use null coalescing operator
        $uploadedFile = $request->file('file');

        // Validate that a file has been uploaded
        if (!$uploadedFile) {
            return response()->error('', 400, 'No file uploaded.');
        }

        // Validate file type and size
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,png,jpg,jpeg|max:2048', // Adjust mime types and size as needed
        ]);

        $originalFileName = $uploadedFile->getClientOriginalName();
        $timestampedFileName = now()->format('Ymd-His') . "-" . $originalFileName;

        // Fetch the corresponding document record
        $doc = PurchaseOrder1::select('sysid', 'ref_date')
            ->where('uuid_rec', $uuid)
            ->where('document_status', 'O')
            ->first();

        if (!$doc) {
            return response()->error('', 404, 'Data not found'); // Use appropriate HTTP status code
        }

        $sysid = $doc->sysid;
        $directory = "public/po/" . substr($doc->ref_date, 0, 4);

        // Store the uploaded file
        $path = $uploadedFile->storeAs($directory, $timestampedFileName);

        // Update the document record with the new file information
        PurchaseOrder1::where('sysid', $sysid)->update([
            'document' => $path,
            'doc_name' => $originalFileName,
        ]);

        return response()->success('success', [
            'path_file' => $path,
            'message' => 'Document uploaded successfully.',
        ]);
    }


    public function history(Request $request){
        $item_code=isset($request->item_code) ? $request->item_code :'';
        $data=ItemInvoice1::from('t_item_invoice2 as a')
            ->selectRaw("a.item_code,a.descriptions,a.purchase_price,a.prc_discount1,a.prc_tax,
            b.ref_date,b.partner_code,b.partner_name")
            ->leftjoin('t_item_invoice1 as b','a.sysid','=','b.sysid')
            ->orderby('b.ref_date','desc')
            ->where('a.item_code',$item_code)
            ->limit(10)
            ->get();
        return response()->success('Success',$data);
    }

    public function itempartner(Request $request){
        $item_code=isset($request->item_code) ? $request->item_code :'';
        $data=ItemPartner::from('m_item_partner as a')
        ->selectRaw("a.partner_code,a.price,a.prc_discount,a.prc_tax,a.last_purchase,
                    b.partner_name")
        ->leftjoin('m_partner as b','a.partner_code','=','b.partner_id')
        ->where('a.item_code',$item_code)
        ->get();
        return response()->success('Success',$data);
    }

    public function query(Request $request)
    {
        $filter     = $request->filter;
        $limit      = $request->limit;
        $sorting    = ($request->descending == "true") ? 'desc' : 'asc';
        $sortBy     = $request->sortBy;
        $start_date = $request->start_date;
        $end_date   = $request->end_date;
        $pool_code  = $request->pool_code;
        $start_date = $request->start_date;

        $data= PurchaseOrder1::from('t_purchase_order1 as a')
        ->selectRaw("(a.sysid*10000)+b.line_no AS _index,a.doc_number,a.ref_date,a.validate_date,a.ref_document,a.doc_purchase_request ,
            a.partner_code,a.partner_name,b.line_no,b.item_code,c.part_number,b.descriptions,b.line_state,b.qty_order,b.price,b.total,b.qty_cancel,b.qty_received,
            IFNULL(b.qty_order,0)-(IFNULL(b.qty_received,0)+IFNULL(qty_cancel,0)) AS outstanding,a.update_userid,a.update_timestamp,a.is_posted,a.document_status,
            CASE b.line_state
            WHEN 'O' THEN 'OPEN'
            WHEN 'P' THEN 'PARTIAL'
            WHEN 'C' THEN 'CLOSED' END as po_state")
        ->leftJoin('t_purchase_order2 as b','a.sysid','=','b.sysid')
        ->leftJoin('m_item as c','b.item_code','=','c.item_code')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);

        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.ref_document', 'like', $filter)
                    ->orwhere('a.partner_name', 'like', $filter)
                    ->orwhere('b.item_code', 'like', $filter)
                    ->orwhere('c.part_number', 'like', $filter)
                    ->orwhere('a.doc_purchase_request', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        $data=$data->toArray();
        $rows=array();
        foreach($data['data'] as $row){
            if ($row['document_status']=='O'){
                $row['po_state']=($row['is_posted']=='1') ? 'APPROVED':'DRAFT';
            }
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success', $data);
    }

    public function report(Request $request)
    {
        // Validate input
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start_date = $request->start_date;
        $end_date = $request->end_date;

        // Fetch data
        $data = PurchaseOrder1::from('t_purchase_order1 as a')
            ->selectRaw("(a.sysid * 10000) + b.line_no AS _index, a.doc_number, a.ref_date, a.validate_date, a.ref_document,
                a.doc_purchase_request, a.partner_code, a.partner_name, b.line_no, b.item_code, c.part_number, b.descriptions,
                b.line_state, b.qty_order, b.price, b.total, b.qty_cancel, b.qty_received,
                IFNULL(b.qty_order, 0) - (IFNULL(b.qty_received, 0) + IFNULL(qty_cancel, 0)) AS outstanding,
                a.update_userid, a.update_timestamp, a.is_posted, a.document_status,
                CASE b.line_state WHEN 'O' THEN 'OPEN' WHEN 'P' THEN 'PARTIAL' WHEN 'C' THEN 'CLOSED' END AS po_state")
            ->leftJoin('t_purchase_order2 as b', 'a.sysid', '=', 'b.sysid')
            ->leftJoin('m_item as c', 'b.item_code', '=', 'c.item_code')
            ->whereBetween('a.ref_date', [$start_date, $end_date])
            ->get();

        if ($data->isEmpty()) {
            return response()->error('', 404, 'No data found for the specified period.');
        }

        // Process data for export
        foreach ($data as $row) {
            if ($row['document_status'] == 'O') {
                $row['po_state'] = ($row['is_posted'] == '1') ? 'APPROVED' : 'DRAFT';
            }
        }

        // Create a new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        // Title and date range
        $sheet->setCellValue('A1', 'LAPORAN STATUS PO')
            ->setCellValue('A2', 'PERIODE')
            ->setCellValue('B2', ': ' . date_format(date_create($start_date), "d-m-Y") . ' s/d ' . date_format(date_create($end_date), "d-m-Y"));

        // Set headers
        $headers = [
            'No.PO', 'Tanggal', 'No.Permintaan', 'Kode Supplier', 'Nama Supplier',
            'Kode Item', 'Part Number', 'Nama Item/Barang', 'Jml Order',
            'Harga/Satuan', 'Total', 'Jml.Batal', 'Jml.Terima', 'Sisa PO',
            'Status PO', 'User Input', 'Tgl.Input'
        ];

        foreach ($headers as $key => $header) {
            $sheet->setCellValueByColumnAndRow($key + 1, 5, $header);
        }

        // Center alignment for headers
        $sheet->getStyle('A5:Q5')->getAlignment()->setHorizontal('center');

        // Fill data
        $idx = 6; // Start from row 6
        foreach ($data as $row) {
            $sheet->setCellValue('A' . $idx, $row->doc_number)
                ->setCellValue('B' . $idx, $row->ref_date)
                ->setCellValue('C' . $idx, $row->doc_purchase_request)
                ->setCellValue('D' . $idx, $row->partner_code)
                ->setCellValue('E' . $idx, $row->partner_name)
                ->setCellValue('F' . $idx, $row->item_code)
                ->setCellValue('G' . $idx, $row->part_number)
                ->setCellValue('H' . $idx, $row->descriptions)
                ->setCellValue('I' . $idx, $row->qty_order)
                ->setCellValue('J' . $idx, $row->price)
                ->setCellValue('K' . $idx, $row->total)
                ->setCellValue('L' . $idx, $row->qty_cancel)
                ->setCellValue('M' . $idx, $row->qty_received)
                ->setCellValue('N' . $idx, $row->outstanding)
                ->setCellValue('O' . $idx, $row->po_state)
                ->setCellValue('P' . $idx, $row->update_userid)
                ->setCellValue('Q' . $idx, $row->update_timestamp);
            $idx++;
        }

        // Set formats for columns
        $sheet->getStyle('B6:B' . ($idx - 1))->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D6:H' . ($idx - 1))->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('I6:N' . ($idx - 1))->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('Q6:Q' . ($idx - 1))->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');

        // Set bold for header
        $sheet->getStyle('A1:Q5')->getFont()->setBold(true);

        // Apply borders
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A5:Q' . ($idx - 1))->applyFromArray($styleArray);

        // Auto-size columns
        foreach (range('A', 'Q') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Set specific widths for certain columns
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(12);

        // Prepare response for download
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });

        $xls = "laporan_po_" . $start_date . '_' . $end_date . ".xlsx";
        PagesHelp::Response($response, $xls)->send();
    }

}
