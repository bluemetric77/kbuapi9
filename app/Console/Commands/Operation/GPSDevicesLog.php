<?php

namespace App\Console\Commands\Operation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Master\Vehicle;
use App\Master\Devices;
use App\Ops\DeviceLog;
use PagesHelp;

class GPSDevicesLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps:sync';

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
        if (!($token=='')) {
            $devices=Devices::select('deviceid')->get();
            foreach ($devices as $row){
                $url_realtime=$url."/api_positions/realtime/".$token.'?devices%5B0%5D%5Bid%5D='.$row->deviceid;
                $json=null;
                $log=PagesHelp::curl_data($url_realtime,null,false);
                if ($log['status']===true){
                    $json=$log['json'];
                }
                if ($json){
                    if ($json['status_code']==200) {
                        $datas=$json['data']['data'];
                        if (!($datas==null)){
                            $id=$row['deviceid'];
                            $data=$datas[$id][0];
                            if (!(DeviceLog::where('deviceid', $id)
                                ->where('update_date',$data['ldatetime'])
                                ->exists())) {
                                DeviceLog::insert([
                                    'deviceid'=>$data['adevid'],
                                    'logdate'=>$data['eventsentdatetime'],
                                    'latitude'=>$data['alat'],
                                    'longitude'=>$data['along'],
                                    'ignation_status'=>$data['ignition_status'],
                                    'road_name'=>$data['roadname'],
                                    'max_speed'=>$data['maxspeed'],
                                    'speed'=>$data['aspeed'],
                                    'update_date'=>$data['ldatetime']
                                ]);
                                Devices::where('deviceid',$id)
                                ->update([
                                   'longitude'=>$data['along'],
                                   'latitude'=>$data['alat'],
                                   'address'=>$data['roadname'],
                                   'speed'=>$data['aspeed'],
                                   'ignition_status'=>$data['ignition_status'],
                                   'update_date'=>$data['ldatetime']
                                ]);
                            }
                        }
                    } else if ($json['status_code']==403) {
                        PagesHelp::GenerateToken();    
                        $token=PagesHelp::GetToken();
                    }
                }

            }
        }
        /** Create logs*/
        $enddate=Date('Y-m-d H:i:s');
        $info=array();
        $info['command']='gps:sync';
        $info['start_date']=$startdate;
        $info['end_date']=$enddate;
        $info['respons']='OK';
        $info['is_success']=1;
        DB::table('h_schedule_job_log')->insert($info);
        return 0;
    }
}
