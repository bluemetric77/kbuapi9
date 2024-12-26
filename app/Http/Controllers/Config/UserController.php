<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\Users;
use App\Models\Config\UsersPool;
use App\Models\Config\UsersAccess;
use App\Models\Config\ObjectItem;
use PagesHelp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filter  = $request->filter;
        $limit   = $request->limit;
        $sorting = ($request->descending=="true") ? "desc" :"asc";
        $sortBy  = $request->sortBy;
        $session = PagesHelp::Session();
        $data= Users::selectRaw("sysid,user_id,user_name,user_level,photo,email,is_group,
                is_suspend,phone,security_level,security_group,uuid_rec");

        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('user_id','like',$filter)
                    ->orwhere('user_name','like',$filter)
                    ->orwhere('email','like',$filter)
                    ->orwhere('phone','like',$filter);
            });
        }
        if (!$session->security_level=='ADMIN'){
            $data=$data->where('user_id',$userid);
        }

        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        $data=$data->toArray();
        $rows=array();
        $server=PagesHelp::my_server_url();
        foreach($data['data'] as $row){
            $row['photo']=$server.'/'.$row['photo'];
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success',$data);
    }

    public function get(Request $request)
    {
        $uuid=$request->uuid ?? '';
        $data= Users::selectRaw("sysid,user_id,user_name,user_level,photo,sign,email,is_group,
        is_suspend,phone,security_level,security_group,uuid_rec")
        ->where('uuid_rec',$uuid)
        ->first();
        $server=PagesHelp::my_server_url();
        $data->photo=($data->photo<>'') ? $server.'/'.$data->photo:'';
        $data->sign=($data->sign<>'') ? $server.'/'.$data->sign:'';
        return response()->success('',$data,1);
    }

    public function post(Request $request)
    {
        $session = PagesHelp::Session();

        $data = $request->json()->all();
        $row  = $data['data'];
        $validator=Validator::make($row,[
            'user_id'=>'bail|required',
            'user_name'=>'bail|required',
            'security_level'=>'bail|required',
        ],[
            'user_id.required'=>'User ID harus diisi',
            'user_name.required'=>'Nama pengguna harus diisi',
            'security_level.required'=>'Level akses harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $user=Users::where('uuid_rec',$row['uuid_rec']??'')->first();
            if (!($user)) {
                $user=new Users();
                $user->user_id  = $row['user_id'];
                $user->uuid_rec = Str::uuid();
                $user->password = Hash::make('12345@@S4ltedRose');
                $user->create_by= $session->user_id;
                $user->create_date= now();
            }
            $user->fill([
                'user_name' => $row['user_name'],
                'email'     => $row['email'],
                'phone'     => $row['phone'],
                'security_level' => $row['security_level'],
                'is_suspend'     => $row['is_suspend'],
                'is_group'       => $row['is_group'],
                'security_group' => $row['security_group'],
                'update_by' => $session->user_id,
                'update_date'=> now()
            ]);
            $user->save();
            $sysid=$user->sysid;

            DB::commit();
            $message['message']='Simpan berhasil ['.$row['user_name'].']';
            $message['sysid']=$sysid;
            return response()->success('success',$message);
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function save_security(Request $request){
        $session = PagesHelp::Session();

        if (!($session->security_level=='ADMIN')){
            return response()->error('',501,'You have not authorized');
        }
        $data      = $request->json()->all();
	    $uuid      = $data['uuid'];
	    $pool_code = $data['pool_code'];
        $security  = $data['access'];
        $reports   = $data['report'];
        $sysid     = Users::where('uuid_rec',$uuid)->first()->sysid;

        DB::beginTransaction();
        try{
            DB::table('o_users_access')
             ->where('sysid',$sysid)
             ->where('pool_code',$pool_code)
             ->delete();
            foreach ($security as $line) {
                $main = $line['main'];
                foreach ($main as $detail) {
                    if ($detail['header']==0) {
                        $objid = $detail['object_id'];
                        $objsecurity=json_encode($detail['security']);
                        if (!($objsecurity=='[]')) {
                            DB::table('o_users_access')
                                ->insert([
                                    'pool_code' => $pool_code,
                                    'sysid' => $sysid,
                                    'object_id'=> $objid,
                                    'security' => $objsecurity
                                ]);
                        }
                    }
                    if ($detail['header']==1){
                        $sub =$detail['detail'];
                        foreach ($sub as $subdetail) {
                            $objid = $subdetail['object_id'];
                            $objsecurity=json_encode($subdetail['security']);
                            if (!($objsecurity=='[]')) {
                                DB::table('o_users_access')
                                    ->insert([
                                        'pool_code' => $pool_code,
                                        'sysid' => $sysid,
                                        'object_id'=> $objid,
                                        'security' => $objsecurity
                                    ]);
                            }
                        }
                    }
                }
            }
            DB::delete('DELETE FROM o_user_report WHERE sysid=? AND pool_code=?',[$sysid,$pool_code]);
            foreach ($reports as $report) {
               if ($report['is_header']=='0') {
                DB::table('o_user_report')
                ->insert([
                    'pool_code'=>$pool_code,
                    'sysid'=>$sysid,
                    'report_id'=>$report['id'],
                    'is_allow'=>$report['is_selected']
                ]);
               }
            }
            DB::commit();
            return response()->success('success','Simpan data berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function delete(Request $request){
        $uuid=$request->uuid ?? '';
        $data=Users::where('uuid_rec',$uuid)->first();
        if ($data) {
            UsersPool::where('user_id',$data->user_id)
            ->delete();

            UsersAccess::where('sysid',$data->sysid)
            ->delete();

            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }

    public function changepwd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uuid_rec' => 'bail|required',
            'pwd1' => 'bail|required|min:5',
            'pwd2' => 'bail|required|min:5|same:pwd1',
        ], [
            'uuid_rec.required' => 'UUID user harus diisi',
            'pwd1.required' => 'Password baru harus diisi',
            'pwd2.required' => 'Password konfirmasi harus diisi',
            'pwd1.min' => 'Password baru harus setidaknya 5 karakter',
            'pwd2.same' => 'Konfirmasi password harus sesuai dengan password baru',
        ]);

        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->first());
        }

        $session = PagesHelp::Session();
        $user = Users::selectRaw("sysid, password, security_level")
            ->where('user_id', $session->user_id)
            ->first();

        if (!$user) {
            return response()->error('', 501, 'User tidak ditemukan');
        } elseif ($user->security_level !== 'ADMIN') {
            return response()->error('', 501, 'Akses untuk ubah password tidak ada');
        }

        DB::beginTransaction();
        try {
            Users::where('uuid_rec', $request->uuid_rec)->update([
                'password' => Hash::make($request->pwd1.'@@S4ltedRose'),
            ]);
            DB::commit();
            return response()->success('success', 'Ubah password berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }

    public function UserAuth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'bail|required',
            'password' => 'bail|required'
        ], [
            'user_id.required' => 'User Id harus diisi',
            'password.required' => 'Password harus diisi'
        ]);

        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->first());
        }

        $data = Users::selectRaw("sysid, user_id, user_name, IFNULL(password, '') as password, attemp_lock, failed_attemp")
            ->where('user_id', $request->user_id)
            ->where('is_group', '0')
            ->where('is_suspend', '0')
            ->first();

        if (!$data) {
            return response()->error('', 301, "User ID/Password salah (DOESN'T EXISTS)");
        }

        if ($data->failed_attemp >= 3 && $data->attemp_lock > now()) {
            $date = $data->attemp_lock->format('d-m-Y H:i:s');
            return response()->error('', 301, "User anda terkunci, Anda dapat login lagi $date");
        }

        $data->failed_attemp = 0;
        $data->attemp_lock = null;
        $data->save();

        $password = Hash::check($request->password . '@@S4ltedRose', $data->password);

        $pools = DB::table('o_users_pool as a')
            ->select('a.pool_code', 'a.is_allow', 'a.is_read', 'b.warehouse_code', 'b.descriptions')
            ->leftJoin('m_pool as b', 'a.pool_code', '=', 'b.pool_code')
            ->where('a.user_id', $request->user_id)
            ->where('a.is_allow', 1)
            ->get();

        if ($password) {
            if ($pools->count() === 0) {
                return response()->error('', 301, "Tidak memiliki akses pool");
            }

            $pool = $pools->first();
            $data->pool_name = $pool->descriptions;
            $data->pool_code = $pool->pool_code;
            $data->warehouse = $pool->warehouse_code;

            if ($pools->count() > 1) {
                $data->pool_code = 'XXXX';
                $data->pool_name = '';
                $data->warehouse = '';
            }

            Users::where('user_id', $request->user_id)
                ->update([
                    'failed_attemp' => 0,
                    'attemp_lock' => null,
                    'session_id' => session()->getId(),
                    'ip_number' => request()->ip(),
                    'last_login' => now()
                ]);

            $token = $this->generate_jwt($data->user_id, $data->user_name, $data->pool_code);

            return response()->success('Berhasil', [
                'user_id' => $data->user_id,
                'user_name' => $data->user_name,
                'photo' => $data->photo,
                'token' => $token,
                'pool_code' => $data->pool_code,
                'pool_name' => $data->pool_name,
                'warehouse' => $data->warehouse
            ]);
        } else {
            $failedAttempts = $data->failed_attemp + 1;
            Users::where('user_id', $request->user_id)
                ->update(['failed_attemp' => $failedAttempts]);

            if ($failedAttempts >= 3) {
                $date = now()->addMinutes(3)->format('d-m-Y H:i:s');
                Users::where('user_id', $request->user_id)
                    ->update(['attemp_lock' => now()->addMinutes(3)]);
                return response()->error('', 301, "3 kali login gagal, Anda dapat login lagi $date");
            }

            return response()->error('', 301, "User id/Password salah");
        }
    }


    public function lock(Request $request){
        $jwt = $request->header('x_jwt');
        DB::table('o_session')->where('hash_code',$jwt)->update(['is_locked'=>'1']);
    }

    public function relogin(Request $request){
        $validator=Validator::make($request->all(),[
            'password'=>'bail|required'
        ],[
            'password.required'=>'Password harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $session=PagesHelp::Session();
        $userid=PagesHelp::UserID($request);
        $password=isset($request->password) ? $request->password:'';
        $jwt = $request->header('x_jwt');

        $data=Users::selectRaw("password")->where('user_id',$session->user_id)->first();
        if (!$data){
            return response()->error('',301,"User ID tidak dikenal");
        } else {
            $password=$password.'@@S4ltedRose';
            if (Hash::check($password, $data->password)) {
                DB::table('o_session')->where('hash_code',$jwt)
                ->update(['is_locked'=>'0']);
                return response()->success('Success','Allowed');
            } else {
                return response()->error('',301,"Password salah");
            }
        }
    }

    function generate_jwt($userid,$username,$pool_code){
        $hash_code=Hash::make($userid.'##'.$pool_code.'@@S4ltedRose'.Date('YmdHis'));
        $create_date  = date('Y-m-d H:i:s');
        $expired_date =  date('Y-m-d H:i:s', strtotime('+12 hours'));
        $refresh_date =  date('Y-m-d H:i:s', strtotime('+180 minutes'));
        DB::table('o_session')->where('expired_date','<',$create_date)->delete();
        session()->regenerate();
        DB::table('o_session')
        ->insert([
            'hash_code'=>$hash_code,
            'user_id'=>$userid,
            'token_created'=>$create_date,
            'mac_address'=>'N/A',
            'ip_number'=>request()->ip(),
            'expired_date'=>$expired_date,
            'is_deleted'=>0,
            'refresh'=>$refresh_date,
            'session_id'=>session()->getId(),
            'pool_code'=>$pool_code
            ]);
        return $hash_code;
    }

    public function logout(Request $request){
        $ip  = request()->ip();
        $jwt=$request->input('x_jwt');
        $sessionid=session()->getId();
        DB::table('o_session')
         ->where('hash_code',$jwt)
         ->where('ip_number',$ip)->delete();
         session()->regenerate();
         return response()->success('Logout',null);
    }

    public function getObjAccess(Request $request){
        $uuid = $request->uuid ?? '';
        $pool_code = $request->pool_code ?? '';

        // Fetch the user based on uuid_rec
        $user = Users::select('sysid')->where('uuid_rec', $uuid)->first();

        if (!$user) {
            // Return an error if the user is not found
            return response()->error('User not found', [], 404);
        }

        // Fetch user access items
        $items = UsersAccess::selectRaw("sysid,pool_code,object_id,security")
                    ->where('sysid', $user->sysid ??-1)
                    ->where('pool_code', $pool_code)
                    ->get();
        return response()->success('Success', $items);
    }


    public function getItem(Request $request){
        $item = ObjectItem::select('id','group_id','sort_number','level','title','icon','is_header','security')
            ->where('is_active',1)
            ->distinct()
            ->orderBy('sort_number')
            ->get();
        return response()->success('Success',$item);
    }
    public function getItemAccess(Request $request){
        $item = ObjectItem::select('id','group_id','sort_number','level','title','is_header','security')
            ->where('is_active',1)
            ->where('is_header',0)
            ->distinct()
            ->orderBy('sort_number')
            ->get();
        return response()->success('Success',$item);
    }

    public function savePoolAccess(Request $request){
        $data= $request->json()->all();
        $row=$data['data'];
        $validator=Validator::make($row,[
            'user_id'=>'bail|required',
            'pool_code'=>'bail|required',
            'user_level'=>'bail|required'
        ],[
            'user_id.required'=>'Pool harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'user_level.required'=>'Level akses harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $session= PagesHelp::Session();

        DB::beginTransaction();
        try{
            $pool=UsersPool::where('uuid_rec',$row['uuid_rec']??'')->first();
            if (!$pool) {
                $pool=new UsersPool();
                $pool->uuid_rec   = Str::uuid();
                $pool->is_denny   = '1';
                $pool->created_by = $session->user_id;
                $pool->created_date = Date('Y-m-d H:i:s');
            } else {
                $pool->updated_by = $session->user_id;
                $pool->updated_date = Date('Y-m-d H:i:s');
            }
            $pool->user_id   = $row['user_id'];
            $pool->pool_code = $row['pool_code'];
            $pool->is_read   = $row['is_read'];
            $pool->is_allow  = $row['is_allow'];
            $pool->user_level= $row['user_level'];
            $pool->save();
            DB::commit();
            return response()->success('success','Simpan data berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function DeletePoolAccess(Request $request){
        $uuid= $request->uuid ??'';
        $pa = UsersPool::where('uuid_rec',$uuid)->first();

        if (!$pa) {
            return response()->error('',501,'Akses pool tidak ditemukan');
        }

        DB::beginTransaction();
        try{
            $pa-delete();
            DB::commit();
            return response()->success('success','Hapus data berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function poolaccess(Request $request)
    {
        $uuid   = isset($request->uuid) ? $request->uuid : '';
        $user   =Users::selectRaw("user_id")->where('uuid_rec',$uuid)->first();

        $filter = $request->filter;
        $limit  = $request->limit;
        $sorting= ($request->descending=="true") ?'desc':'asc';
        $sortBy = $request->sortBy;

        $data   = UsersPool::selectRaw("sysid,user_id,pool_code,is_allow,is_read,user_level,uuid_rec")
        ->where('user_id',$user->user_id ?? '')
        ->paginate($limit);

        return response()->success('Success',$data);
    }
    public function updatepool(Request $request){
        $jwt=$request->header('x_jwt');
        $data=$request->json()->all();
        $pool_code=isset($data['pool_code']) ? $data['pool_code'] :'';
        if (($pool_code=='') || ($jwt=='')) {
          return response()->error('',501,"Pool/Lokasi belum dipilih");
        }
        $data=DB::table('o_session')->select('user_id','pool_code')->where('hash_code',$jwt)->first();
        if ($data){
            $pools = DB::table('o_users_pool as a')->select('a.pool_code','a.is_allow','a.is_read','b.warehouse_code','b.descriptions')
                ->leftjoin('m_pool as b','a.pool_code','=','b.pool_code')
                ->where('a.user_id',$data->user_id)
                ->where('a.pool_code',$pool_code)
                ->where('a.is_allow',1)
                ->first();
           if ($pools){
                DB::table('o_session')
                ->where('hash_code',$jwt)
                ->update(['pool_code'=>$pool_code]);
                $respon['warehouse']=$pools->warehouse_code;
                $respon['info']='Berhasil';
                return response()->success('Success',$respon);
           } else {
              return response()->error('',501,"Tidak ada akses untuk pool tersebut");
           }
        } else {
          return response()->error('',501,"Error (Akses tidak ditemukan");
        }
    }
    public function profile(Request $request){
        $jwt=$request->header('x_jwt');
        $data=DB::table('o_session')->select('user_id','pool_code')->where('hash_code',$jwt)->first();
        if ($data){
            $data=Users::select('user_id','user_name','email','phone','photo')
            ->where('user_id',$data->user_id)
            ->first();
            if ($data){
                $server=PagesHelp::my_server_url();
                $data['photo']=$server.'/'.$data['photo'];
            }
            $pool=DB::table('o_users_pool')->where('user_id',$data->user_id)->get();
            $data['any']= ($pool->count()>1) ? true : false;
            return response()->success('Success',$data);
       } else {
            return response()->error('',501,"Error");
       }
    }

    public function user_level(Request $request)
    {
        $data=DB::table('o_users_level')->select()->get();
        return response()->success('Success',$data);
    }

    public function uploadfoto(Request $request)
    {
        $validator=Validator::make($request->all(),[
            'uuid' => 'required|string|exists:o_users,uuid_rec',
            'file' => 'required|file|mimes:jpg,jpeg,png|max:2048' // Limit to 2MB and specific image types
        ],[
            'uuid.required'=>'User ID harus diisi',
            'file.required'=>'file foto/sign tidak bole NULL',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $uuid             = $request->uuid;
        $uploadedFile     = $request->file('file');
        $originalFileName = Date('Ymd-His') . "-" . $uploadedFile->getClientOriginalName();
        $directory = 'public/photo';

        // Store the file
        $path = $uploadedFile->storeAs($directory, $originalFileName);
        // Update the database with the new file path
        Users::where('uuid_rec', $uuid)->update(['photo' => $path]);

        // Generate the full URL path for the stored file
        $server = PagesHelp::my_server_url();
        return response()->success('Success',
            ['path_file'=> $server . '/' .$path,
                'message'=>'Upload successful']
            );
    }



    public function uploadsign(Request $request)
    {
        $validator=Validator::make($request->all(),[
            'uuid' => 'required|string|exists:o_users,uuid_rec',
            'file' => 'required|file|mimes:jpg,jpeg,png|max:2048' // Limit to 2MB and specific image types
        ],[
            'uuid.required'=>'User ID harus diisi',
            'file.required'=>'file foto/sign tidak bole NULL',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $uuid             = $request->uuid;
        $uploadedFile     = $request->file('file');
        $originalFileName = Date('Ymd-His') . "-" . $uploadedFile->getClientOriginalName();
        $directory = 'public/sign';

        // Store the file
        $path = $uploadedFile->storeAs($directory, $originalFileName);

        // Update the database with the new file path
        Users::where('uuid_rec', $uuid)->update(['sign' => $path]);

        // Generate the full URL path for the stored file
        $server = PagesHelp::my_server_url();
        return response()->success('Success',
            ['path_file'=> $server . '/' .$path,
                'message'=>'Upload successful']
            );
    }

    public function post_profile(Request $request)
    {
        $data= $request->json()->all();
        $row=$data['data'];
        $where=$data['where'];

        $jwt=$request->header('x_jwt');
        $session=DB::table('o_session')->select('user_id',)->where('hash_code',$jwt)->first();
        if ($session){
           $user=Users::select('sysid')->where('user_id',$session->user_id)->first();
        }

        DB::beginTransaction();
        try{
            $sysid=$user->sysid;
            Users::where('sysid',$sysid)
            ->update([
                'user_name'=>$row['user_name'],
                'email'=>$row['email'],
                'phone'=>$row['phone'],
            ]);
            DB::commit();
            $message['message']='Update data berhasil';
            $message['sysid']=$sysid;
            return response()->success('success',$message);
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function changepassword(Request $request){
        $jwt=$request->header('x_jwt');
        $session=DB::table('o_session')->select('user_id',)->where('hash_code',$jwt)->first();
        if ($session){
           $user=Users::selectRaw('sysid,password')->where('user_id',$session->user_id)->first();
        } else {
            return response()->error('',501,'Data tidak ditemukan');
        }
        $sysid=$user->sysid;
        $old_pwd=$request->pwdold;
        $pwd1=$request->pwd1;
        $pwd2=$request->pwd2;
        if (!(Hash::check($old_pwd, $user->password))) {
            return response()->error('',501,'Password lama salah');
        }
        if (!($pwd1==$pwd2)){
            return response()->error('',501,'Konfirmasi password berbeda');
        }
        try{
            Users::where('sysid',$sysid)
                ->update(['password'=>Hash::make($pwd1.'@@S4ltedRose')]);
            return response()->success('success','Ubah password berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
public static function getDocKey($key_word = '')
{
    // Retrieve the document based on the provided key word
    $data = NoTrans::from('t_notran as a')
        ->select('a.doc_name', 'a.doc_key')
        ->where('a.doc_key', $key_word)
        ->first();

    if (!$data) {
        // Create a new instance if no record is found
        $data = new NoTrans();
        $data->doc_name = $key_word;
        $data->doc_key = 0;
    }

    // Increment and save the doc_key
    $data->doc_key++;
    $data->save();

    return $data->doc_key;
}

}
