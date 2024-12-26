<?php

namespace App\Console\Commands\Operation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Master\Vehicle;

class SnapshootVehicle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vehicle:snapshoot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Snapshoot vehicle data for keep log trayek';

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
        $data=DB::table('t_purchase_request1')
        ->selectRaw("sysid")
        ->whereRaw("ADDDATE(ref_date, INTERVAL 60 DAY) < CURRENT_DATE() AND request_status<>'Complete' AND is_cancel=0")
        ->get();
        foreach($data as $row) {
            DB::update("UPDATE `t_purchase_request2`
                        SET item_status='Canceled',line_cancel=qty_request-line_supply
                        WHERE sysid=? AND qty_request>line_supply
                        ",[$row->sysid]);
            
            DB::table('t_purchase_request1')
            ->where('sysid',$row->sysid)
            ->update(['request_status'=>'Complete']);
        }

        $data=DB::table('t_purchase_order1')
        ->selectRaw("sysid")
        ->whereRaw("ADDDATE(ref_date, INTERVAL 300 DAY) < CURRENT_DATE() AND document_status<>'C' AND is_posted=1")
        ->get();
        foreach($data as $row) {
            DB::update("UPDATE t_purchase_order2
            SET line_state='C',qty_cancel=qty_order-qty_received
            WHERE sysid=? AND line_state<>'C' AND (IFNULL(qty_order,0)-IFNULL(qty_received,0)) >0",[$row->sysid]);
            DB::table('t_purchase_order1')
            ->where('sysid',$row->sysid)
            ->update(['document_status'=>'C']);
        }

        $date=Date('Y-m-d');
        DB::table('h_vehicle_route')
        ->where('snapshoot_date',$date)->delete();
        $data=Vehicle::selectRaw("vehicle_no,default_route_id")
        ->where('default_route_id','<>','-1')
        ->where('is_active',1)
        ->get();
        foreach ($data as $row){
            $line=array();
            $line['vehicle_no']=$row['vehicle_no'];
            $line['snapshoot_date']=$date;
            $line['route_id']=$row['default_route_id'];
            DB::table('h_vehicle_route')
            ->insert($line);
        }
        $year=Date('Y');
        $month=Date('m');
        DB::delete("DELETE FROM t_summary WHERE year_period=? ABD month_period=?",[$year,$month]);
        DB::insert("INSERT INTO t_summary
            SELECT YEAR(ref_date),MONTH(ref_date),route_id,COUNT(*) AS ritase,SUM(passenger) FROM `t_operation`
            WHERE  YEAR(ref_date)=? AND MONTH(ref_date)=?
            GROUP BY  YEAR(ref_date),MONTH(ref_date),route_id",[$year,$month]);
        $this->info("Generated route history was succesfully");
        return 0;
    }
}
