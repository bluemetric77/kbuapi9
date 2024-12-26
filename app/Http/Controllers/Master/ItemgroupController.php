<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Itemgroups;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PagesHelp;
use Illuminate\Support\Str;

class ItemgroupController extends Controller
{
    public function show(Request $request){
        $filter     = $request->filter;
        $limit      = $request->limit;
        $descending = $request->descending=="true";
        $sortBy     = $request->sortBy;

        $data= Itemgroups::selectRaw("sysid,item_group,descriptions,account_no,expense_account,
        variant_account,cogs_account,item_type,record_stock,uuid_rec");

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('item_group','like',$filter)
               ->orwhere('descriptions','like',$filter);
        }

        $data=$data->orderBy($sortBy,($descending) ? 'desc':'asc')->paginate($limit);

        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $uuid=$request->uuid;
        $data=Itemgroups::where('uuid_rec',$uuid)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                Itemgroups::where('sysid',$data->sysid)->delete();
                DB::table('m_item_group_account')->where('item_group',$data-item_group)->delete();
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
        $uuid=$request->uuid;
        $data['header']=Itemgroups::selectRaw("sysid,item_group,descriptions,account_no,expense_account,
            variant_account,cogs_account,item_type,record_stock,uuid_rec")
            ->where('uuid_rec',$uuid)->first();

        $data['account']=DB::table('m_item_group_account')
            ->selectRaw('pool_code,inv_account,cost_account,variant_account')
            ->where('item_group',isset($data['header']->item_group) ? $data['header']->item_group:'')->get();

        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data   = $request->json()->all();
        $rec    = $data['data'];
        $account= $data['account'];
        $validator=Validator::make($rec,[
            'item_group'=>'bail|required',
            'descriptions'=>'bail|required'
        ],[
            'item_group.required'=>'Kode grup inventory harus diisi',
            'descriptions.required'=>'Grup inventory harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $validator=Validator::make($account,[
            '*.pool_code'=>'bail|required',
            '*.inv_account'=>'bail|required|exists:m_account,account_no',
            '*.cost_account'=>'bail|required|exists:m_account,account_no',
            '*.variant_account'=>'bail|required|exists:m_account,account_no'
        ],[
            '*.pool_code.required'=>'Kode pool harus diisi',
            '*.inv_account.required'=>'Akun persediaan inventory harus diisi',
            '*.cost_account.required'=>'Akun biaya inventory harus diisi',
            '*.variant_account.required'=>'Akun variant inventory harus diisi',
            '*.inv_account.exists'=>'No.akun persediaan [ :input ] tidak ditemukan dimaster akun',
            '*.cost_account.exists'=>'No.akun biaya [ :input ] tidak ditemukan dimaster akun',
            '*.variant_account.exists'=>'No.akun variant [ :input ] tidak ditemukan dimaster akun'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            $group =Itemgroups::where('uuid_rec',isset($rec['uuid_rec']) ? $rec['uuid_rec'] :'')->first();
            if (!($group)) {
                $group = new Itemgroups();
                $group->uuid_rec = Str::uuid();
            } else {
                DB::table('m_item_group_account')->where('item_group',$group->item_group)->delete();
            }
            $group->item_group       = $rec['item_group'];
            $group->descriptions     = $rec['descriptions'];
            $group->account_no       = isset($rec['account_no']) ? $rec['account_no'] :'';;
            $group->expense_account  = isset($rec['expense_account']) ? $rec['expense_account'] :'';;
            $group->variant_account  = isset($rec['variant_account']) ? $rec['variant_account'] :'';;
            $group->cogs_account     = isset($rec['cogs_account']) ? $rec['cogs_account'] :'';;
            $group->item_type        = isset($rec['item_type']) ? $rec['item_type'] :'Inventory';;
            $group->record_stock     = isset($rec['record_stock']) ? $rec['record_stock'] :'0';;
            $group->update_userid    = PagesHelp::Session()->user_id;
            $group->update_timestamp = Date('Y-m-d H:i:s');
            $group->save();

            foreach($account as $row){
                DB::table('m_item_group_account')->insert([
                    'item_group'    => $rec['item_group'],
                    'pool_code'     => $row['pool_code'],
                    'inv_account'   => $row['inv_account'],
                    'cost_account'  => $row['cost_account'],
                    'variant_account'=>$row['variant_account'],
                    'update_userid'=> $group->update_userid,
                    'create_date'  =>Date('Y-m-d H:i:s')
                ]);
            }
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getItemgroups(Request $request){
        $data=Itemgroups::select('item_group',DB::raw('CONCAT(descriptions, " - ", item_group) AS descriptions'))
            ->orderBy('descriptions')
            ->get();
        return response()->success('Success',$data);
    }
}
