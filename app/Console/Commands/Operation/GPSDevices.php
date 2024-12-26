<?php

namespace App\Console\Commands\Operation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Master\Vehicle;
use App\Master\Devices;
use PagesHelp;

class GPSDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps:device';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize GPS Devices';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startdate=Date('Y-m-d H:i:s');
        $config=DB::table('o_system')->selectRaw("key_word,key_value_nvarchar")
        ->whereRaw("key_word='GPS_URL'")->first();

        $url=$config->key_value_nvarchar;
        $token=PagesHelp::GetToken();
        if ($token==''){
            PagesHelp::GenerateToken();    
            $token=PagesHelp::GetToken();
        }
        $devices=null;
        if (!($token=='')) {
            $urldevice=$url."/api_devices/load_device/".$token;
            $respon=PagesHelp::curl_data($urldevice,null,false);
            if ($respon['status']==true){
                $devices=$respon['json'];
            }
        }
        $devices_unit=null;
        if ($devices){
            if ($devices['status_code']==200){
                $devices_unit=$devices['data'][0]['devices'];                
            } else if ($devices['status_code']==403){
                PagesHelp::GenerateToken();    
            }
        }
        foreach ($devices_unit as $unit){
            if (Devices::where('deviceid', $unit['deviceid'])->exists()) {
                Devices::where('deviceid',$unit['deviceid'])
                ->update([
                    'deviceid'=>$unit['deviceid'],
                    'devicename'=>$unit['name'],
                    'make'=>$unit['make'],
                    'model'=>$unit['model'],
                    'register_name'=>$unit['reg'],
                    'voltage'=>$unit['voltage'],
                    'gsm_phone'=>$unit['gsm_phone'],
                    'installed_by'=>$unit['installed_by'],
                    'installed_date'=>$unit['date_installed'],
                    'tested_by'=>$unit['tested_by'],
                    'tested_date'=>$unit['date_tested'],
                    'ign_marker'=>$unit['ign_marker'],
                    'mdt'=>$unit['mdt'],
                    'serial_sim'=>$unit['sim_serial'],
                    'real_device_id'=>$unit['real_device_id'],
                    'update_timestamp'=>$unit['updated_at']
                    ]);
            } else {
                Devices::insert([
                    'deviceid'=>$unit['deviceid'],
                    'devicename'=>$unit['name'],
                    'make'=>$unit['make'],
                    'model'=>$unit['model'],
                    'register_name'=>$unit['reg'],
                    'voltage'=>$unit['voltage'],
                    'gsm_phone'=>$unit['gsm_phone'],
                    'installed_by'=>$unit['installed_by'],
                    'installed_date'=>$unit['date_installed'],
                    'tested_by'=>$unit['tested_by'],
                    'tested_date'=>$unit['date_tested'],
                    'ign_marker'=>$unit['ign_marker'],
                    'mdt'=>$unit['mdt'],
                    'serial_sim'=>$unit['sim_serial'],
                    'real_device_id'=>$unit['real_device_id'],
                    'update_timestamp'=>$unit['updated_at']
                    ]);
            }                
        }
        /** Create logs*/
        $enddate=Date('Y-m-d H:i:s');
        $info=array();
        $info['command']='gps:device';
        $info['start_date']=$startdate;
        $info['end_date']=$enddate;
        $info['respons']=$respon['message'];
        $info['is_success']=1;
        DB::table('h_schedule_job_log')->insert($info);
        return 0;
    }
}
