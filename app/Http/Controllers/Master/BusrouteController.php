<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Busroute;
use App\Models\Ops\Busroutepoint;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BusrouteController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc':'asc';
        $sortBy = $request->sortBy;
        $pool_code= PagesHelp::PoolCode($request);
        $data = Busroute::from('m_bus_route as a')
            ->select(
                'a.sysid','a.route_id','a.route_name','a.est_distance','a.is_active',
                'b.station_name as origins','c.station_name as destination','rate','target'
            )
            ->leftJoin('m_station as b', 'a.start_station', '=', 'b.sysid')
            ->leftJoin('m_station as c', 'a.dest_station', '=', 'c.sysid')
            ->where('pool_code',$pool_code);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.route_id','like',$filter)
               ->orwhere('a.route_name','like',$filter)
               ->orwhere('b.station_name','like',$filter)
               ->orwhere('c.station_name','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        DB::beginTransaction();
        try {
            $data = Busroute::find($id);
            if ($data) {
                Busroute::find($id)->delete();
                DB::table('m_bus_route_checkpoint')
                    ->where('sysid',$id)
                    ->delete();
                PagesHelp::write_log($request,$id,$data->route_id,'Delete recods ['.$data->route_id.'-'.$data->route_name.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } else {
                DB::rollback();
                return response()->success('Success','Data Tidak ditemukan');
            }

        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function get(Request $request){
        $route_id=isset($request->id) ? $request->id : -1;
        $vehicle_no= isset($request->vehicle_no) ? $request->vehicle_no : '';
        $sysid=isset($request->sysid) ? $request->sysid : '-1';
        if ($vehicle_no==''){
            $data['header']=Busroute::find($route_id);
            $data['go_data']=Busroutepoint::from('m_bus_route_checkpoint as a')
                ->select('a.sysid','a.checkpoint_sysid','b.checkpoint_name','a.sort_number','a.factor_point','a.point')
                ->leftJoin('m_checkpoint as b', 'a.checkpoint_sysid', '=', 'b.sysid')
                ->where('a.sysid',$route_id)
                ->where('a.enum_route','GO')
                ->orderBy('a.sort_number','asc')
                ->get();
            $data['return_data']=Busroutepoint::from('m_bus_route_checkpoint as a')
                ->select('a.sysid','a.checkpoint_sysid','b.checkpoint_name','a.sort_number','a.factor_point','a.point')
                ->leftJoin('m_checkpoint as b', 'a.checkpoint_sysid', '=', 'b.sysid')
                ->where('a.sysid',$route_id)
                ->where('a.enum_route','BACK')
                ->orderBy('a.sort_number','asc')
                ->get();
        } else {
            $data['header']=Busroute::from('m_bus_route as a')
            ->selectRaw("a.sysid,a.route_id,a.route_name,a.start_station,a.dest_station,a.est_distance,IFNULL(b.target,0) as target,a.rate,IFNULL(b.target_min,0) as target_min,
            IFNULL(b.target_min2,0) as target_min2,a.start_factor_go,a.start_factor_end,a.dest_factor_go,a.dest_factor_end,
            IFNULL(b.start_point_go,0) as start_point_go,IFNULL(b.start_point_end,0)as start_point_end,IFNULL(b.dest_point_go,0) as dest_point_go,IFNULL(b.dest_point_end,0) as dest_point_end,
            IFNULL(b.default_model,'POINT') as default_model,IFNULL(b.is_active,0) as is_active")
            ->leftJoin('m_vehicle_routepoint as b', function($join) use($vehicle_no)
                {
                    $join->on('a.sysid', '=', 'b.route_id');
                    $join->on('b.vehicle_no','=',DB::raw("'$vehicle_no'"));
                })
            ->where('a.sysid',$route_id)->first();
            $data['go_data']=Busroutepoint::from('m_bus_route_checkpoint as a')
                ->select('a.sysid','a.checkpoint_sysid','b.checkpoint_name','a.sort_number','a.factor_point',DB::raw('IFNULL(c.point,0) as point'))
                ->leftJoin('m_checkpoint as b', 'a.checkpoint_sysid', '=', 'b.sysid')
                ->leftJoin('m_vehicle_routepoint_detail as c', function($join) use($vehicle_no,$route_id)
                    {
                        $join->on('a.checkpoint_sysid', '=', 'c.checkpoint');
                        $join->on('a.enum_route', '=', 'c.flag_route');
                        $join->on('c.route_id','=',DB::raw("'$route_id'"));
                        $join->on('c.vehicle_no','=',DB::raw("'$vehicle_no'"));
                    })
                ->where('a.sysid',$route_id)
                ->where('a.enum_route','GO')
                ->orderBy('sort_number','asc')
                ->get();
            $data['return_data']=Busroutepoint::from('m_bus_route_checkpoint as a')
                ->select('a.sysid','a.checkpoint_sysid','b.checkpoint_name','a.sort_number','a.factor_point',DB::raw('IFNULL(c.point,0) as point'))
                ->leftJoin('m_checkpoint as b', 'a.checkpoint_sysid', '=', 'b.sysid')
                ->leftJoin('m_vehicle_routepoint_detail as c', function($join) use($vehicle_no,$route_id)
                    {
                        $join->on('a.checkpoint_sysid', '=', 'c.checkpoint');
                        $join->on('a.enum_route', '=', 'c.flag_route');
                        $join->on('c.route_id','=',DB::raw("'$route_id'"));
                        $join->on('c.vehicle_no','=',DB::raw("'$vehicle_no'"));
                    })
                ->where('a.sysid',$route_id)
                ->where('a.enum_route','BACK')
                ->orderBy('a.sort_number','asc')
                ->get();
        }
        return response()->success('Success',$data);
    }

    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['pool_code']= PagesHelp::PoolCode($request);
        $go_data=$data['go_data'];
        $return_data=$data['return_data'];
        $validator=Validator::make($rec,[
            'route_id'=>'required',
            'route_name'=>'required',
            'start_station'=>'required',
            'dest_station'=>'required'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->all());
        }

        DB::beginTransaction();
        try{
            $sysid=$rec['sysid'];
            if ($opr=='updated'){
                Busroute::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                $sysid=Busroute::insertGetId($rec);
            }
            DB::table('m_bus_route_checkpoint')
                ->where($where)
                ->delete();
            // Go Bus Route
            $detail = array();
            foreach ($go_data as $value) {
                $record = (array) $value;
                $record['sysid']=$sysid;
                $record['enum_route']='GO';
                unset($record['checkpoint_name']);
                $detail[] = $record;
            }
            DB::table('m_bus_route_checkpoint')
                ->insert($detail);

            // Return Bus Route
            $detail = array();
            foreach ($return_data as $value) {
                $record = (array) $value;
                $record['sysid']=$sysid;
                $record['enum_route']='BACK';
                unset($record['checkpoint_name']);
                $detail[] = $record;
            }
            DB::table('m_bus_route_checkpoint')
                ->insert($detail);
            PagesHelp::write_log($request,$where['sysid'],$rec['route_id'],'Add/Update record ['.$rec['route_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function getRoute(Request $request){
        $pool_code= PagesHelp::PoolCode($request);
        $route=Busroute::select('sysid',DB::raw('CONCAT(route_id, " - ", route_name) AS descriptions'))
             ->where('is_active','1')
             ->where('pool_code',$pool_code)
             ->get();
        $na=array();
        $na['sysid']=-1;
        $na['descriptions']='N/A';
        $route[]=$na ;
        return response()->success('Success',$route);
    }

    public function getRoute2(Request $request){
        $na=isset($request->isna) ? $request->isna:1;
        $pool=Busroute::select('route_id',DB::raw('CONCAT(route_id, " - ", route_name) AS descriptions'))
             ->where('is_active','1')
             ->where('pool_code',$pool_code)
             ->get();
        $bank=array();
        if ($na==1){
            $bank['route_id']='';
            $bank['descriptions']='N/A';
        } else {
            $bank['route_id']='';
            $bank['descriptions']='Semua Rute';
        }
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }
    public function route_info(Request $request){
        $route_id=isset($request->id) ? $request->id : -1;
        $data['header']=Busroute::from('m_bus_route as a')
        ->selectRaw("a.sysid,a.route_id,a.route_name,a.start_station,a.dest_station,a.est_distance,a.pool_code,
        b.station_name as start_station_name,
        c.station_name as end_station_name")
        ->leftjoin("m_station as b","a.start_station","=","b.sysid")
        ->leftjoin("m_station as c","a.dest_station","=","c.sysid")
        ->where('a.sysid',$route_id)->first();
        $data['go_data']=Busroutepoint::from('m_bus_route_checkpoint as a')
            ->select('a.sysid','a.checkpoint_sysid','b.checkpoint_name','a.sort_number','a.factor_point','a.point')
            ->leftJoin('m_checkpoint as b', 'a.checkpoint_sysid', '=', 'b.sysid')
            ->where('a.sysid',$route_id)
            ->where('a.enum_route','GO')
            ->orderBy('a.sort_number','asc')
            ->get();
        $data['return_data']=Busroutepoint::from('m_bus_route_checkpoint as a')
            ->select('a.sysid','a.checkpoint_sysid','b.checkpoint_name','a.sort_number','a.factor_point','a.point')
            ->leftJoin('m_checkpoint as b', 'a.checkpoint_sysid', '=', 'b.sysid')
            ->where('a.sysid',$route_id)
            ->where('a.enum_route','BACK')
            ->orderBy('a.sort_number','asc')
            ->get();
        return response()->success('Success',$data);
    }


}
