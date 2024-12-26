<?php
// Place this file on the Providers folder of your project
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use App\Models\Config\UserSessions;


class ResponseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    protected $except = [
        'login',
        'logout',
        'profile',
        'pool',
        'home/item',
        'access/securitypage',
        'access/pageaccess'
    ];

    public function boot(ResponseFactory $factory)
    {
        $request = $this->app->request;
        $except = $this->except;
        $factory->macro('success', function ($message = '', $data = null, $rowcount = 0) use ($factory,$request,$except) {
            $jwt = $request->header('x_jwt');
            $uri=strtolower($request->getPathInfo());
            $ignored=false;
            foreach ($except as $value) {
                $value=strtolower('/api/'.$value);
                if ($uri==$value){
                    $ignored = true;
                }
            }

            $header = [
                'status'=>'OK',
                'error_no'=>0,
                'message'=>$message
            ];

            $info = [
                'success'=>true,
                'rowcount'=>$rowcount,
                'data'=>$data
            ];
            $format = [
                'header'=>$header,
                'contents'=>$info
            ];

            if (($jwt<>"") && ($ignored==false)) {
                $session=UserSessions::select(DB::raw('sysid,now() as curr_time,user_id,refresh,pool_code'))
                ->where('hash_code',$jwt)
                ->where('session_id',session()->getId())
                ->first();
                if ($session) {
                    if ($session->curr_time>$session->refresh){
                        $hash_code=Hash::make($session->user_id.'##'.$session->pool_code.'@@S4ltedRose'.Date('YmdHis'));
                        $refresh_date =  date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        DB::table('o_session')
                            ->where('sysid',$session->sysid)
                            ->update(['hash_code'=>$hash_code,
                                      'refresh'=>$refresh_date]);
                        $format = [
                                'header'=>$header,
                                'contents'=>$info,
                                'new_jwt'=>$hash_code
                            ];
                    }
                }
            }
            return $factory->make($format);
        });

        $factory->macro('error', function (string $message = '', $error_code = 0, $errors = []) use ($factory) {
            $header = [
                'status'=>'NOT_OK',
                'error_no'=>$error_code,
                'message'=>$message
            ];
            $info = [
                'success'=>false,
                'data'=>$errors
            ];

            $format = [
                'header'=>$header,
                'contents'=>$info
            ];
            return $factory->make($format);
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $request = $this->app->request;
    }
}
