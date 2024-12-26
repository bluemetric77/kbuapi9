<?php

namespace App\Http\Controllers\Dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Master\Busroute;
use App\Models\Master\Pools;
use App\Models\Ops\Operation;
use App\Models\Service\Service;
use App\Models\Purchase\JobInvoice1;
use PagesHelp;

class Dashboard1Controller extends Controller
{
    public function tableritase(Request $request){
        $pool_code=$request->pool_code;
        $year = $request->year;
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $charts=array();
        $data=Busroute::from('m_bus_route as a')
        ->selectRaw("a.sysid,a.route_name,a.pool_code")
        ->where('a.pool_code',$pool_code);
        if (!($sortBy=='')) {
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        } else {
            $data=$data->paginate($limit);
        }
        $data=$data->toArray();
        $rows=array();
        $server=PagesHelp::my_server_url();
        foreach($data['data'] as $line){
           $line['field1']=0;
           $line['field2']=0;
           $line['field3']=0;
           $line['field4']=0;
           $line['field5']=0;
           $line['field6']=0;
           $line['field7']=0;
           $line['field8']=0;
           $line['field9']=0;
           $line['field10']=0;
           $line['field11']=0;
           $line['field12']=0;
           $ops=DB::table('t_summary')->selectRaw("month_period as bln,ritase")
           ->where("year_period",$year)
           ->where('route_id',$line['sysid'])
           ->get();
           foreach($ops as $op){
               $field='field'.$op->bln;
               $line[$field]=$op->ritase;
           }
           $rows[]=$line;
        }
        $data['data']=$rows;
        return response()->success('Success',$data);
    }
    public function tablepoint(Request $request){
        $pool_code=$request->pool_code;
        $year = $request->year;
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $charts=array();
        $data=Busroute::from('m_bus_route as a')
        ->selectRaw("a.sysid,a.route_name,a.pool_code")
        ->where('a.pool_code',$pool_code);
        if (!($sortBy=='')) {
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        } else {
            $data=$data->paginate($limit);
        }
        $data=$data->toArray();
        $rows=array();
        $server=PagesHelp::my_server_url();
        foreach($data['data'] as $line){
           $line['field1']=0;
           $line['field2']=0;
           $line['field3']=0;
           $line['field4']=0;
           $line['field5']=0;
           $line['field6']=0;
           $line['field7']=0;
           $line['field8']=0;
           $line['field9']=0;
           $line['field10']=0;
           $line['field11']=0;
           $line['field12']=0;
           $ops=DB::table('t_summary')->selectRaw("month_period as bln,passenger as point")
           ->where("year_period",$year)
           ->where('route_id',$line['sysid'])
           ->get();
           foreach($ops as $op){
               $field='field'.$op->bln;
               $line[$field]=$op->point;
           }
           $rows[]=$line;
        }
        $data['data']=$rows;
        return response()->success('Success',$data);
   }
    public function ritase(Request $request){
       $pool_code=$request->pool_code;
       $year=$request->year;
       $charts=array();
       $pools=Pools::from('m_pool as a')->selectRaw("a.pool_code,a.descriptions")
        ->where('a.pool_code',$pool_code)
        ->get();
       foreach($pools as $pool){
           $chart['is_loaded']=true;
           $chart['pool_code']=$pool->pool_code;
           $chart['pool_name']=$pool->descriptions;
           $chart['data']=Dashboard1Controller::ritases($year,$pool->pool_code);
           $charts[]=$chart;
       }
       return response()->success('Success',$charts);
    }
    public function ritases($year,$pool_code){
       $routes=Busroute::selectRaw("sysid,sort_name")->where("pool_code",$pool_code)->get();
       $charts=array();
       foreach($routes as $route){
            $color=Dashboard1Controller::random_color();
            $chart=array();
            $chart['label']=$route->sort_name;
            $chart['borderColor']=$color;
            $chart['pointBackgroundColor']='yellow';
            $chart['borderWidth']= 1;
            $chart['pointBorderColor']=$color;
            $chart['backgroundColor']='transparent';
            $chart['fill']=true;

            $chart['data']= [0,0,0,0,0,0,0,0,0,0,0,0];
            $start_date=$year.'-01-01';
            $end_date=$year.'-12-31';
            $ritases=DB::table('t_summary')->selectRaw("month_period as bln,ritase")
            ->where("year_period",$year)
            ->where('route_id',$route->sysid)
            ->get();
            $i=-1;
            foreach($ritases as $ritase){
                $i=$i+1;
                $chart['data'][$i]=intval($ritase->ritase);
            }
            $charts[]=$chart;
            $chartdata['labels']=['January', 'February', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'Sepember', 'Oktober', 'November', 'Desember'];
            $chartdata['datasets']= $charts;
       }
        return $chartdata;
    }

    public function point(Request $request){
       $pool_code=$request->pool_code;
       $year=$request->year;
       $charts=array();
       $pools=Pools::from('m_pool as a')->selectRaw("a.pool_code,a.descriptions")
        ->where('a.pool_code',$pool_code)
        ->get();

        foreach($pools as $pool){
           $chart['is_loaded']=true;
           $chart['pool_code']=$pool->pool_code;
           $chart['pool_name']=$pool->descriptions;
           $chart['data']=Dashboard1Controller::points($year,$pool->pool_code);
           $charts[]=$chart;
       }
       return response()->success('Success',$charts);
    }
    public function points($year,$pool_code){
       $routes=Busroute::selectRaw("sysid,sort_name")->where("pool_code",$pool_code)->get();
       $charts=array();
       foreach($routes as $route){
            $color=Dashboard1Controller::random_color();
            $chart=array();
            $chart['label']=$route->sort_name;
            $chart['borderColor']=$color;
            $chart['pointBackgroundColor']='yellow';
            $chart['borderWidth']= 1;
            $chart['pointBorderColor']=$color;
            $chart['backgroundColor']='transparent';
            $chart['fill']=true;

            $chart['data']= [0,0,0,0,0,0,0,0,0,0,0,0];
            $start_date=$year.'-01-01';
            $end_date=$year.'-12-31';
            $ritases=DB::table('t_summary')->selectRaw("month_period as bln,passenger as point")
            ->where("year_period",$year)
            ->where('route_id',$route->sysid)
            ->get();
            $i=-1;
            foreach($ritases as $ritase){
                $i=$i+1;
                $chart['data'][$i]=intval($ritase->point);
            }
            $charts[]=$chart;
            $chartdata['labels']=['January', 'February', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'Sepember', 'Oktober', 'November', 'Desember'];
            $chartdata['datasets']= $charts;
       }
        return $chartdata;
    }

    public function service(Request $request){
        $pool_code=$request->pool_code;
        $curr_date=$request->date;
        $date=date_create($curr_date);
        $year=date_format($date,"Y");
        $month=date_format($date,"m");
        $charts=array();
        $chart['is_loaded']=true;
        $chart['title']='Tahun '.$year." (jutaan)";
        $chart['data']=Dashboard1Controller::services($year,$month,$pool_code,'YEARLY');
        $charts[]=$chart;
        $chart['is_loaded']=true;
        $chart['title']="Bulan Lalu (jutaan)";
        $chart['data']=Dashboard1Controller::services($year,$month,$pool_code,'BEFORE');
        $charts[]=$chart;
        $chart['is_loaded']=true;
        $chart['title']="Bulan ini (jutaan)";
        $chart['data']=Dashboard1Controller::services($year,$month,$pool_code,'CURRENT');
        $charts[]=$chart;
        return response()->success('Success',$charts);
    }

    public function services($year,$month,$pool_code,$flag){
        $chart=array();
        $chart['borderWidth']=5;
        $chart['borderRadius']=4;
        $chart['hoverOffset']=2;
        $chart['backgroundColor']=['rgb(255, 99, 132)','rgb(54, 162, 235)','rgb(255, 205, 86)','rgb(100, 205, 86)'];
        $chart['data']= [0,0,0,0];
        $services=Service::from("t_workorder_service as a")
        ->selectRaw("d.item_group,SUM(c.line_cost ) AS line_cost")
        ->join("t_inventory_booked1 as b","a.doc_number","=","b.service_no")
        ->join("t_inventory_booked2 as c","b.sysid","=","c.sysid")
        ->join("m_item as d","c.item_code","=","d.item_code");
        if ($flag=='YEARLY') {
            $services=$services->whereRaw("YEAR(a.ref_date)=?",[$year]);
        } else if ($flag=='BEFORE') {
            $month=intval($month);
            if ($month==1){
                $month=12;
                $year=$year-1;
            } else {
                $month=$month - 1;
            }
            $services=$services->whereRaw("YEAR(a.ref_date)=?",[$year])->whereRaw("MONTH(a.ref_date)=?",[$month]);
        } else if ($flag=='CURRENT') {
            $month=intval($month);
            $services=$services->whereRaw("YEAR(a.ref_date)=?",[$year])->whereRaw("MONTH(a.ref_date)=?",[$month]);
        }

        $services=$services->where("a.pool_code",$pool_code)
        ->groupByRaw("d.item_group")
        ->orderBy("d.item_group")
        ->get();
        $i=-1;
        foreach($services as $service){
            $i=$i+1;
            $chart['data'][$i]=intval($service->line_cost/1000000);
        }
        $jobs=Service::from('t_workorder_service as a')
        ->selectRaw("SUM(b.total) as total")
        ->join("t_job_invoice1 as b","a.doc_number","=","b.service_no")
        ->where('a.pool_code',$pool_code);
        if ($flag=='YEARLY') {
            $jobs=$jobs->whereRaw("YEAR(a.ref_date)=?",[$year]);
        } else if ($flag=='BEFORE') {
            $month=intval($month);
            if ($month==1){
                $month=12;
                $year=$year-1;
            } else {
                $month=$month - 1;
            }
            $jobs=$jobs->whereRaw("YEAR(a.ref_date)=?",[$year])->whereRaw("MONTH(a.ref_date)=?",[$month]);
        } else if ($flag=='CURRENT') {
            $month=intval($month);
            $jobs=$jobs->whereRaw("YEAR(a.ref_date)=?",[$year])->whereRaw("MONTH(a.ref_date)=?",[$month]);
        }
        $jobs=$jobs->where("a.pool_code",$pool_code)->first();
        if ($jobs){
            $i=$i+1;
            $chart['data'][$i]=intval($jobs->total/1000000);
        }
        $charts[]=$chart;
        $chartdata['labels']=['Sparepart','Ban','Pelumas','Service Luar'];
        $chartdata['datasets']= $charts;
        return $chartdata;
    }

    function random_color(){
        $rand = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f');
        $color = '#'.$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)].$rand[rand(0,15)];
        return $color;
    }
}

