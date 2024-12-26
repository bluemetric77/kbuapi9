<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Partner;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Partner::select('partner_id','partner_name','partner_address','invoice_address','invoice_handler','phone_number','fax_number','email','contact_person','contact_phone','tax_number',
        'partner_type','fee_of_storage','percent_of_storage','minimum_tonase','fee_of_tonase','due_interval','is_active','cash_id','format_id','ar_account','ap_account','dp_account','is_document');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data
               ->where('partner_type','C')
               ->where(function($q) use ($filter) {
                    $q->where('partner_name','like',$filter)
                        ->orwhere('partner_address','like',$filter)
                        ->orwhere('phone_number','like',$filter)
                        ->orwhere('email','like',$filter);
               });
        } else {
            $data=$data
               ->where('partner_type','C');
        }
        if ($sortBy<>''){
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        }
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $partner_id=$request->partner_id;
        $data=Partner::where('partner_id',$partner_id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $personal_id=$request->partner_id;
        $data=Partner::where('partner_id',$personal_id)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        try{
            if ($opr=='updated'){
                $rec['partner_type']='C';
                Partner::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                $rec['partner_type']='C';
                Partner::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function lookup(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Partner::select('partner_id','partner_name','partner_address','invoice_address','invoice_handler','phone_number','fax_number','email','contact_person','contact_phone','tax_number',
        'partner_type','fee_of_storage','percent_of_storage','minimum_tonase','fee_of_tonase','due_interval','is_active','cash_id','format_id','ar_account','ap_account','dp_account','is_document');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data
               ->where('partner_type','C')
               ->where('is_active','1')
               ->where(function($q) use ($filter) {
                    $q->where('partner_name','like',$filter)
                        ->orwhere('partner_address','like',$filter)
                        ->orwhere('phone_number','like',$filter)
                        ->orwhere('email','like',$filter);
               });
        } else {
            $data=$data
               ->where('partner_type','C')
               ->where('is_active','1');
        }
        if ($sortBy<>''){
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        }
        return response()->success('Success',$data);
    }
}
