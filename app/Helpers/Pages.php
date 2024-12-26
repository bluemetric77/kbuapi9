<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Config\UserLogs;
use App\Models\Config\UserSessions;
use App\Models\Master\Pools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class Pages
{
    public static function DataDef($id)
    {
      $data = DB::table('o_object_items')
         ->select('id', 'column_def', 'title_page', 'api_link', 'security')
         ->where('url_link', $id)
         ->first();
      return $data;
    }

    public static function userinfo($request)
    {
      $jwt = $request->header('x_jwt');
      return $jwt;
    }

    public static function messages()
    {
      return null;
    }

    public static function Session() {
        $request = request();
        $jwt     = $request->header('x_jwt');
        $session=UserSessions::from('o_session as a')
        ->selectRaw("a.sysid,a.hash_code,a.user_id,a.token_created,a.ip_number,a.refresh,a.pool_code,a.is_locked,
        b.user_name,b.is_suspend,b.is_group,b.security_level,b.email,b.phone,b.user_level,
        pl.warehouse_code,pl.project_code,pl.warehouse_code as warehouse_id")
        ->leftjoin("o_users as b","a.user_id","=","b.user_id")
        ->leftjoin('m_pool as pl','a.pool_code','=','pl.pool_code')
        ->where("a.hash_code",isset($jwt) ? $jwt:'')->first();
        if (!($session)) {
            $session= new UserSessions();
            $session->user_id ='N/A';
        }
        return $session;
    }

    public static function UserID($request){
        $jwt = $request->header('x_jwt');
        $data=DB::table('o_session')->selectRaw("user_id")->where('hash_code',$jwt)->first();
        return isset($data->user_id) ? $data->user_id :'N/A';
    }

   public static function PoolCode($request){
        $jwt = $request->header('x_jwt');
        $data = DB::table('o_session')
        ->select('pool_code')->where('hash_code', $jwt)->first();
        return isset($data->pool_code) ? $data->pool_code :'N/A';
   }

   public static function Project($poolcode){
      $data=Pools::select('project_code')->where('pool_code',$poolcode)->first();
      return $data->project_code ?? '00';
   }

   public static function Warehouse($poolcode){
      $data=Pools::select('warehouse_code')->where('pool_code',$poolcode)->first();
      return $data->warehouse_code ?? '00';
   }
   public static function Profile(){
      $data = DB::table('m_profile')->selectRaw('name,address,url,photo,city,phone,folder_api')->where('sysid',1)->first();
      return $data;
   }

    public static function getDocSeries($poolCode, $prefix, $docDate)
    {
        $yearPeriod = date('Y', strtotime($docDate));
        $monthPeriod = date('m', strtotime($docDate));

        $data = DB::table('o_series_document')
            ->select('numbering')
            ->where([
                ['pool_code', $poolCode],
                ['prefix_code', $prefix],
                ['year_period', $yearPeriod],
                ['month_period', $monthPeriod]
            ])->first();

        $counter = $data ? intval($data->numbering) + 1 : 1;

        if ($data) {
            DB::table('o_series_document')
                ->where([
                    ['pool_code', $poolCode],
                    ['prefix_code', $prefix],
                    ['year_period', $yearPeriod],
                    ['month_period', $monthPeriod]
                ])
                ->update(['numbering' => $counter]);
        } else {
            DB::table('o_series_document')->insert([
                'pool_code' => $poolCode,
                'prefix_code' => $prefix,
                'year_period' => $yearPeriod,
                'month_period' => $monthPeriod,
                'numbering' => $counter
            ]);
        }

        $year = substr($yearPeriod, 2, 2);
        $series = ($poolCode === '-' || $poolCode === '')
            ? "{$prefix}-{$year}{$monthPeriod}" . str_pad((string) $counter, 4, '0', STR_PAD_LEFT)
            : "{$poolCode}.{$prefix}-{$year}{$monthPeriod}" . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);

        return $series;
    }


    public static function getVoucherSeries($prefix, $docDate)
    {
        $yearPeriod = date('Y', strtotime($docDate));
        $monthPeriod = date('m', strtotime($docDate));

        $data = DB::table('o_series_jurnal')
            ->select('counter')
            ->where([
                ['series_code', $prefix],
                ['fiscal_year', $yearPeriod],
                ['fiscal_month', $monthPeriod]
            ])->first();

        $counter = $data ? intval($data->counter) + 1 : 1;

        if ($data) {
            DB::table('o_series_jurnal')
                ->where([
                    ['series_code', $prefix],
                    ['fiscal_year', $yearPeriod],
                    ['fiscal_month', $monthPeriod]
                ])
                ->update(['counter' => $counter]);
        } else {
            DB::table('o_series_jurnal')->insert([
                'series_code' => $prefix,
                'fiscal_year' => $yearPeriod,
                'fiscal_month' => $monthPeriod,
                'counter' => $counter
            ]);
        }

        $year = substr($yearPeriod, 2, 2);
        $series = $year . $monthPeriod . '-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);

        return $series;
    }


    public static function get_data($code, $type = 'C')
    {
        $value = null;
        $data = DB::table('o_system')->where('key_word', $code)->first();

        if (!$data) {
            DB::table('o_system')->insert([
                'key_word' => $code,
                'key_type' => 'C',
                'key_length' => 1000
            ]);

            switch ($type) {
                case 'C':
                    $value = '';
                    break;
                case 'I':
                    $value = -1;
                    break;
                case 'N':
                    $value = 0;
                    break;
                case 'D':
                    $value = date('Y-m-d');
                    break;
                case 'B':
                    $value = false;
                    break;
            }
        } else {
            switch ($type) {
                case 'C':
                    $value = $data->key_value_nvarchar;
                    break;
                case 'I':
                    $value = (int)$data->key_value_integer;
                    break;
                case 'N':
                    $value = (float)$data->key_value_decimal;
                    break;
                case 'D':
                    $value = date_create($data->key_value_date);
                    break;
                case 'B':
                    $value = (bool)$data->key_value_boolean;
                    break;
            }
        }

        return $value;
    }


   public static function write_data($code,$type,$value){
      $data=DB::table('o_system')
            ->where('key_word',$code)
            ->first();
      if (!($data)){
         DB::table('o_system')->insert([
            'key_word'=>$code,
            'key_type'=>'C',
            'key_length'=>1000
         ]);
      }
      $rec= array();
      if ($type=='C'){
         $rec=array('key_value_nvarchar'=>$value);
      } else if ($type=='I'){
         $rec=array('key_value_integer'=>$value);
      } else if ($type=='N'){
         $rec=array('key_value_decimal'=>$value);
      } else if ($type=='D'){
         $rec=array('key_value_date'=>$value);
      } else if ($type=='B'){
         $rec=array('key_value_boolean'=>$value);
      }
      DB::table('o_system')
         ->where('key_word',$code)
         ->update($rec);
   }

    public static function my_server_url()
    {
        $profile = DB::table('m_profile')->select('folder_api')->first();
        $folder = $profile ? '/' . ltrim($profile->folder_api, '/') : '';

        $serverName = $_SERVER['SERVER_NAME'];
        $port = in_array($_SERVER['SERVER_PORT'], [80, 443]) ? '' : ":$_SERVER[SERVER_PORT]";

        $scheme = (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == '1')) ? 'https' : 'http';

        return "{$scheme}://{$serverName}{$port}{$folder}";
    }

    public static function month($index)
    {
        $months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        return isset($months[$index - 1]) ? $months[$index - 1] : null;
    }


   public static function Terbilang( $num ,$dec=4){
    $stext = array(
        "Nol",
        "Satu",
        "Dua",
        "Tiga",
        "Empat",
        "Lima",
        "Enam",
        "Tujuh",
        "Delapan",
        "Sembilan",
        "Sepuluh",
        "Sebelas"
    );
    $say  = array(
        "Ribu",
        "Juta",
        "Milyar",
        "Triliun",
        "Biliun", // remember limitation of float
        "--apaan---" ///setelah biliun namanya apa?
    );
    $w = "";

    if ($num <0 ) {
        $w  = "Minus ";
        //make positive
        $num *= -1;
    }

    $snum = number_format($num,$dec,",",".");
    $strnum =  explode(".",substr($snum,0,strrpos($snum,",")));
    //parse decimalnya
    $koma = substr($snum,strrpos($snum,",")+1);

    $isone = substr($num,0,1)  ==1;
    if (count($strnum)==1) {
        $num = $strnum[0];
        switch (strlen($num)) {
            case 1:
            case 2:
                if (!isset($stext[$strnum[0]])){
                    if($num<19){
                        $w .=$stext[substr($num,1)]." Belas";
                    }else{
                        $w .= $stext[substr($num,0,1)]." Puluh ".
                            (intval(substr($num,1))==0 ? "" : $stext[substr($num,1)]);
                    }
                }else{
                    $w .= $stext[$strnum[0]];
                }
                break;
            case 3:
                $w .=  ($isone ? "Seratus" : Pages::terbilang(substr($num,0,1)) .
                    " Ratus").
                    " ".(intval(substr($num,1))==0 ? "" : Pages::terbilang(substr($num,1)));
                break;
            case 4:
                $w .=  ($isone ? "Seribu" : Pages::terbilang(substr($num,0,1)) .
                    " Ribu").
                    " ".(intval(substr($num,1))==0 ? "" : Pages::terbilang(substr($num,1)));
                break;
            default:
                break;
        }
    }else{
        $text = $say[count($strnum)-2];
        $w = ($isone && strlen($strnum[0])==1 && count($strnum) <=3? "Se".strtolower($text) : Pages::terbilang($strnum[0]).' '.$text);
        array_shift($strnum);
        $i =count($strnum)-2;
        foreach ($strnum as $k=>$v) {
            if (intval($v)) {
                $w.= ' '.Pages::Terbilang($v).' '.($i >=0 ? $say[$i] : "");
            }
            $i--;
        }
    }
    $w = trim($w);
    if ($dec = intval($koma)) {
        $w .= " Koma ". Pages::Terbilang($koma);
    }
    return trim($w);
   }

   public static function Response($response,$filename='download.xlsx')
   {
      $attachment='attachment; filename="'.$filename.'"';
      $response->setStatusCode(200);
      $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
      $response->headers->set('Content-Disposition', $attachment);
      $response->headers->set('Access-Control-Allow-Credentials', true);
      $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:8080');
      $response->headers->set('Access-Control-Expose-Headers', '*');
      return $response;
   }
   public static function GenerateToken(){
        $config=DB::table('o_system')->selectRaw("key_word,key_value_nvarchar")
        ->whereRaw("LEFT(key_word,3)='GPS'")
        ->get();
        $url="";
        $username="";
        $password="";
        $token = "";
        foreach ($config as $row){
          if ($row->key_word=='GPS_USERNAME') {
              $username=$row->key_value_nvarchar;
          } else if ($row->key_word=='GPS_PASSWORD') {
              $password=$row->key_value_nvarchar;
          } else if ($row->key_word=='GPS_URL') {
              $url=$row->key_value_nvarchar;
          }
        }
        $form = array(
		    'username' => $username,
			'password' => $password
        );
        $login=$url."/api_users/login";
        $respon=Pages::curl_data($login,$form);
        if ($respon['status']==true){
           $token=$respon['json']['token'];
           DB::table('o_system')->where('key_word','GPS_TOKEN')->update(['key_value_nvarchar'=>$token]);
        }
   }
   public static function GetToken(){
      $config=DB::table('o_system')
      ->select('key_value_nvarchar')
      ->where('key_word','GPS_TOKEN')
      ->first();
      if ($config){
         return $config->key_value_nvarchar;
      } else {
         return '';
      }
   }

   public static function curl_data($url,$form,$post=true) {
      $info['status']=true;
      $info['message']='';
      $info['data']=null;

      $ip="192.168.43.2";
      $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
      $header[0]  = "Accept: text/xml,application/xml,application/xhtml+xml,";
      $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
      $header[] = "Cache-Control: max-age=0";
      $header[] = "Connection: keep-alive";
      $header[] = "Keep-Alive: 300";
      $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
      $header[] = "Accept-Language: en-us,en;q=0.5";
      $header[] = "Pragma: "; // browsers = blank
      $header[] = "X_FORWARDED_FOR: " . $ip;
      $header[] = "REMOTE_ADDR: " . $ip;
      $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      if ($post){
         curl_setopt($ch, CURLOPT_POST, true);
      }
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
      if (!($form==null)){
         curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
      }
		$output = curl_exec($ch);
		if ($output==false)	{
         $info['status']=false;
			$info['message']=curl_error($ch);
		} else {
         $output = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $output);
         $info['json']=json_decode($output,true);
         $info['message']=json_last_error_msg();
		}
      curl_close($ch);
      return $info;
   }

   public static function write_log(Request $request,$sysid,$doc_number,$message) {
      $uri = $request->path();
      $data=$request->json()->all();
      $data=json_encode($data);
      $user_name=Pages::UserID($request);
      $route = Route::current(); // Illuminate\Routing\Route
      $name = Route::currentRouteName(); // string
      $action = Route::currentRouteAction(); // string
      UserLogs::insert([
         'create_date'=>Date('Y-m-d H:i:s'),
         'user_name'=>$user_name,
         'module'=>$name.'^'.$action,
         'action'=>$request->method(),
         'uri_link'=>url()->full(),
         'document_sysid'=>$sysid,
         'document_number'=>$doc_number,
         'descriptions'=>$message,
         'data'=>$data

      ]);
   }

}
