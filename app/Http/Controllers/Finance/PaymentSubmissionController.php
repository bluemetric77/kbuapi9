<?php

namespace App\Http\Controllers\Finance;

use App\Models\Finance\PaymentSubmission1;
use App\Models\Finance\PaymentSubmission2;
use App\Models\Master\Bank;
use App\Models\Master\Partner;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Config\Users;
use PagesHelp;
use Accounting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class PaymentSubmissionController extends Controller
{
    public function show(Request $request)
    {
        $filter      = $request->filter;
        $limit       = $request->limit;
        $sorting     = $request->descending === "true" ? 'desc' : 'asc';
        $sortBy      = $request->sortBy;
        $start_date  = $request->start_date;
        $end_date    = $request->end_date;
        $outstanding = $request->outstanding ?? '0';

        $data = PaymentSubmission1::from('t_payment_submission1 as a')
        ->selectRaw("a.sysid, a.doc_number, a.ref_date, a.action_date, a.reference,
                    a.paid_id, a.partner_id, a.partner_name, a.total, a.payment,
                    a.is_void, a.is_approved, b.descriptions,
                    a.approved_by1, a.approved_date1, a.approved_by2,
                    a.approved_date2, a.approved_by3, a.approved_date3,
                    a.uuid_rec,c.doc_number as doc_payment,c.ref_date as payment_date")
        ->leftJoin('m_cash_operation as b', 'a.paid_id', '=', 'b.sysid')
        ->leftJoin('t_outpayment1 as c', 'a.sysid_payment', '=', 'c.sysid');

        if ($outstanding === '0') {
            $data = $data->where('a.is_realization', '0')
                ->where('a.is_void', '0');
        } else {
            $data = $data->whereBetween('a.ref_date', [$start_date, $end_date]);
        }

        if (!empty($filter)) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.reference', 'like', $filter)
                ->orWhere('a.doc_number', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }


    public function destroy(Request $request)
    {
        $uuid = $request->uuid ?? '';
        $sub=PaymentSubmission1::where('uuid_rec',$uuid)->first();
        if (!$sub){
            return response()->success('Success', "Dokumen pengajuan pembayaran tidak ditemukan");
        }
        if ($sub->is_void=='1'){
            return response()->error('',501,'Pengajuan pembayaran sudah dibatalkan');
        } else if ($sub->is_realization=='1'){
            return response()->error('',501,'Pengajuan pembayaran sudah ada realisasi pembayaran, tidak bisa diubah');
        } else if ($sub->is_approved=='1'){
            return response()->error('',501,'Pengajuan pembayaran sudah disetujui,tidak bisa dibatalkan');
        } else if ($sub->approved_by1!=''){
                return response()->error('',501,'Pengajuan pembayaran dalam proses persetujuan, tidak bisa dibatalkan');
        }
        PaymentSubmission1::where('sysid',$sub->sysid)
        ->update([
            'is_void'=>'1',
            'void_by'=>PagesHelp::Session()->user_id,
            'void_date'=>Date('Y-m-d')
        ]);
        return response()->success('Success', "Pembatalan dokumen pengajuan pembayaran berhasil");
    }


    public function get(Request $request)
    {
        $uuid = $request->uuid ?? '';
        $header = PaymentSubmission1::
        selectRaw("
            sysid,
            doc_number,
            ref_date,
            action_date,
            reference,
            paid_id,
            partner_id,
            partner_name,
            total,
            payment,
            descriptions,
            is_void,
            is_approved,
            approved_by1,
            approved_date1,
            approved_by2,
            approved_date2,
            approved_by3,
            approved_date3,
            is_realization,
            uuid_rec")
        ->where('uuid_rec',$uuid)->first();

        if ($header) {
            $sysid=$header->sysid;
            $detail=PaymentSubmission2::from('t_payment_submission2 as ps')
            ->selectRaw("ps.line_no,ps.ref_sysid,ps.payment,ps.doc_number,ps.reference,ps.ref_date,ps.total,ps.paid,ps.notes,ta.uuid_invoice,ta.uuid_rec")
            ->join('t_customer_account as ta','ps.ref_sysid','=','ta.sysid')
            ->where('ps.sysid',$sysid)
            ->orderBy("ps.line_no","asc")
            ->get();
        }

        $user=PagesHelp::Session()->user_id;

        $lvl=DB::table('o_users as a')->selectRaw("IFNULL(b.descriptions,'N/A') as descriptions")
        ->leftjoin('o_users_level as b','a.user_level','=','b.sysid')
        ->where('a.user_id',$user)->first();

         $data= [
            'header'=>$header,
            'detail'=>$detail,
            'user_level'=>$lvl->descriptions ?? $user
         ];
        return response()->success('Success', $data);
    }

    public function post(Request $request)
    {
        $data = $request->json()->all();
        $header = $data['header'];
        $detail = $data['detail'];

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'partner_id'=>'bail|required',
            'paid_id'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'partner_id.required'=>'Supplier harus diisi',
            'paid_id.required'=>'Bank/Kas harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.payment'=>'bail|required',
        ],[
            '*.payment.required'=>'Rencana pembayaran harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }


        $sub=PaymentSubmission1::where('uuid_rec',$header['uuid_rec'] ?? '')->first();
        if ($sub){
            if ($sub->is_void=='1'){
                return response()->error('',501,'Pengajuan pembayaran sudah dibatalkan,tidak bisa diubah');
            } else if ($sub->is_realization=='1'){
                return response()->error('',501,'Pengajuan pembayaran sudah ada realisasi pembayaran, tidak bisa diubah');
            } else if ($sub->is_approved=='1'){
                return response()->error('',501,'Pengajuan pembayaran sudah disetujui,tidak bisa diubah');
            }else if ($sub->approved_by1!=''){
                return response()->error('',501,'Pengajuan pembayaran dalam proses persetujuan, tidak bisa diubah');
            }
        }

        DB::beginTransaction();
        try {
            $realdate = date_create($header['ref_date']);
		    $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');

            $session = PagesHelp::Session();

            $sub=PaymentSubmission1::where('uuid_rec',$header['uuid_rec'] ?? '')->first();
            if (!$sub) {
                $sub = new PaymentSubmission1();
                $sub->uuid_rec   = Str::uuid();
                $sub->doc_number = PaymentSubmission1::GenerateNumber($header['ref_date']);
                $sub->is_void    =   0;
                $sub->is_approved  = 0;
                $sub->approved_by1 = '';
                $sub->approved_by2 = '';
                $sub->approved_by3 = '';
            } else {
               PaymentSubmission2::where('sysid',$sub->sysid)->delete();
            }
            $partner=Partner::select('partner_name')->where('partner_id',$header['partner_id'])->first();

            $sub->fill([
                'ref_date'=>$header['ref_date'],
                'reference'=>$header['reference'],
                'paid_id'=>$header['paid_id'],
                'partner_id'=>$header['partner_id'],
                'partner_name'=>$partner->partner_name ?? '',
                'total'=>$header['total'],
                'descriptions'=>$header['descriptions'] ?? '',
                'update_userid'=>$session->user_id,
                'update_timestamp'=>Date('Y-m-d H:i:s')
            ]);
            $sub->save();
            $sysid = $sub->sysid;
            foreach($detail as $row){
                PaymentSubmission2::insert([
                    "sysid"=>$sysid,
                    'line_no'=>$row['line_no'],
                    'ref_sysid'=>$row['ref_sysid'],
                    'payment'=>$row['payment'],
                    'doc_number'=>$row['doc_number'],
                    'reference'=>$row['reference'],
                    'ref_date'=>$row['ref_date'],
                    'total'=>$row['total'],
                    'paid'=>$row['paid'],
                    'payment'=>$row['payment'],
                    'notes'=>$row['notes'] ?? ''
                ]);
            }

            $app_v=DB::table('o_users_level')->selectRaw('descriptions,amount')
            ->orderBy('amount','desc')->get();
            $approved_level='STAFF';
            $po =floatval($header['total']);
            if ($app_v){
                foreach($app_v as $row){
                    $nominal=floatval($row->amount);
                    if ($po<=$nominal){
                        $approved_level=$row->descriptions;
                    }
                }
            }
            if ($approved_level=='MANAGER') {
                $approved_level='STAFF';
            }
            PaymentSubmission1::where('sysid',$sysid)
            ->update(['approved_level'=>$approved_level]);

            DB::commit();
            return response()->success('Success', 'Simpan data berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }

    public function posting(Request $request)
    {
        $data = $request->json()->all();
        $uuid = $data['uuid'];
        $level = $data['level'];
        $detail = $data['detail'];

        $sub = PaymentSubmission1::select('sysid', 'is_approved', 'is_void')->where('uuid_rec', $uuid)->first();

        if (!$sub) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        if ($sub->is_void == '1') {
            return response()->error('', 202, 'Pengajuan pembayaran sudah dibatalkan');
        } else if ($sub->is_realization=='1'){
            return response()->error('',501,'Pengajuan pembayaran sudah ada realisasi pembayaran, tidak bisa diubah');
        } elseif ($sub->is_approved == '1') {
            return response()->error('', 201, 'Pengajuan pembayaran sudah diposting/disetujui');
        }

        DB::beginTransaction();

        try {
            $userid = PagesHelp::Session()->user_id;
            $currentTimestamp = date('Y-m-d H:i:s');

            switch ($level) {
                case 'STAFF':
                    PaymentSubmission1::where('sysid', $sub->sysid)
                        ->whereNull('approved_date1')
                        ->update([
                            'approved_date1' => $currentTimestamp,
                            'approved_by1' => $userid,
                            'approved_level' => 'STAFF',
                        ]);
                    break;

                case 'GENERAL MANAGER':
                    PaymentSubmission1::where('sysid', $sub->sysid)
                        ->whereNull('approved_date2')
                        ->update([
                            'approved_date2' => $currentTimestamp,
                            'approved_by2' => $userid,
                            'approved_level' => 'GENERAL MANAGER',
                        ]);
                    break;

                case 'DIREKTUR':
                    PaymentSubmission1::where('sysid', $sub->sysid)
                        ->whereNull('approved_date3')
                        ->update([
                            'approved_date3' => $currentTimestamp,
                            'approved_by3' => $userid,
                            'is_approved' => '1',
                            'approved_level' => 'DIREKTUR',
                        ]);
                    break;

                default:
                    DB::rollback();
                    return response()->error('', 400, 'Level persetujuan tidak valid');
            }

            DB::commit();
            return response()->success('Success', 'Pengajuan pembayaran hutang berhasil di-approved/disetujui');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }

    public function unposting(Request $request)
    {
        $uuid = $request->uuid ?? '';
        $sub = PaymentSubmission1::select('sysid', 'is_void', 'is_approved')->where('uuid_rec', $uuid)->first();

        if (!$sub) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        if ($sub->is_void == '1') {
            return response()->error('', 202, 'Pengajuan pembayaran sudah dibatalkan');
        } else if ($sub->is_realization=='1'){
            return response()->error('',501,'Pengajuan pembayaran sudah ada realisasi pembayaran, tidak bisa diubah');
        }

        DB::beginTransaction();
        try {
            PaymentSubmission1::where('sysid', $sub->sysid)
                ->update([
                    'is_approved' => 0,
                    'approved_by1' => '',
                    'approved_date1' => null,
                    'approved_by2' => '',
                    'approved_date2' => null,
                    'approved_by3' => '',
                    'approved_date3' => null,
                ]);

            DB::commit();
            return response()->success('Success', 'Proses pembatalan approve/persetujuan dokumen berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }


    public function getAP(Request $request)
    {
        $partner_id = $request->partner_id ?? '-';
        $accounts = CustomerAccount::selectRaw("
                sysid,
                ref_sysid,
                doc_source,
                doc_number,
                reference,
                ref_date,
                amount,
                total_paid as paid,
                amount - total_paid as unpaid,
                no_account,
                uuid_invoice
            ")
            ->where('partner_id', $partner_id)
            ->where('is_approved', '1')
            ->whereRaw('IFNULL(amount, 0) - IFNULL(total_paid, 0) > 0')
            ->get();

        return response()->success('Success', $accounts);
    }


    public function print(Request $request)
    {
        $uuid = $request->uuid ?? '';
        $header = PaymentSubmission1::from('t_payment_submission1 as a')
            ->selectRaw("a.sysid, a.doc_number, a.ref_date, a.partner_id, a.partner_name, a.total, a.payment,
                b.line_no, b.doc_number as invoice_number, b.reference, b.total as invoice_total,
                b.payment AS plan, c.descriptions AS payment_method, a.action_date, b.notes,
                a.update_userid, d.user_name, a.approved_by1, a.approved_by2, a.approved_date1, a.approved_date2,
                a.update_timestamp, IFNULL(e.bank_name, '') as bank_name, IFNULL(e.bank_account, '') as bank_account,
                IFNULL(e.account_name, '') as account_name, e.default_payment")
            ->leftJoin('t_payment_submission2 as b', 'a.sysid', '=', 'b.sysid')
            ->leftJoin('m_cash_operation as c', 'a.paid_id', '=', 'c.sysid')
            ->leftJoin('o_users as d', 'a.update_userid', '=', 'd.user_id')
            ->leftJoin('m_partner as e', 'a.partner_id', '=', 'e.partner_id')
            ->where('a.uuid_rec', $uuid)
            ->orderBy('a.sysid')
            ->orderBy('b.line_no')
            ->get();

        if ($header->isEmpty()) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }

        $home = storage_path().'/app/';
        $sign = [
            'user' => '',
            'general_manager' => '',
            'director' => '',
        ];

        $userSignatures = [
            'user' => $header[0]->update_userid,
            'general_manager' => $header[0]->approved_by1,
            'director' => $header[0]->approved_by2
        ];

        foreach ($userSignatures as $role => $userId) {
            $user = Users::select('sign')->where('user_id', $userId)->first();
            $sign[$role] = isset($user->sign) ? $home . $user->sign : '';
        }

        $header[0]->ref_date = date('d-m-Y', strtotime($header[0]->ref_date));
        $header[0]->update = $header[0]->update_timestamp->format('d-m-Y H:i:s');
        $header[0]->approved1 = $header[0]->approved_date1 ? $header[0]->approved_date1->format('d-m-Y H:i:s') : null;
        $header[0]->approved2 = $header[0]->approved_date2 ? $header[0]->approved_date2->format('d-m-Y H:i:s') : null;

        $profile = PagesHelp::Profile();
        $pdf = PDF::loadView('finance.outpayment_draft', [
            'header' => $header,
            'profile' => $profile,
            'sign' => $sign
        ])->setPaper("A4", 'portrait');

        return $pdf->stream();
    }

}
