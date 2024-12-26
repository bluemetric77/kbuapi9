<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Vehicle;
use App\Models\Master\Vehicleroutepoint;
use App\Models\Master\VariableCost;
use App\Models\Master\Devices;
use App\Models\Ops\Storing;
use App\Models\Service\Service;
use App\Models\General\Documents;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use PagesHelp;

class VehicleController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = $request->descending == "true" ? 'desc' : 'asc';
        $sortBy = $request->sortBy;
        $active = $request->active ?? false;
        $pool_code = PagesHelp::Session()->pool_code;

        $data = Vehicle::from('m_vehicle as a')
            ->select(
                'a.vehicle_no',
                'a.descriptions', 'a.model', 'a.manufactur', 'a.year_production', 'a.police_no', 'a.vin',
                'a.vehicle_status', 'a.odometer', 'a.stnk_validate', 'a.kir_validate', 'a.tax_validate', 'sipa_validate', 'kps_validate',
                'a.tire_type', 'a.pool_code', 'a.is_active',
                'b.route_name', 'a.group_count', 'a.device_id'
            )
            ->leftJoin('m_bus_route as b', 'a.default_route_id', '=', 'b.sysid')
            ->where('a.pool_code', $pool_code);

        if ($filter) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.vehicle_no', 'like', $filter)
                    ->orWhere('a.descriptions', 'like', $filter)
                    ->orWhere('a.police_no', 'like', $filter)
                    ->orWhere('a.chasis_no', 'like', $filter)
                    ->orWhere('a.vin', 'like', $filter)
                    ->orWhere('b.route_name', 'like', $filter);
            });
        }

        if ($active) {
            $data = $data->where('a.is_active', 1);
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);
        return response()->success('Success', $data);
    }


    public function destroy(Request $request)
    {
        $id = $request->vehicle_no;
        $data = Vehicle::where('vehicle_no', $id)->first();

        if ($data) {
            DB::beginTransaction();
            try {
                Vehicle::where('vehicle_no', $id)->delete();
                DB::commit();
                return response()->success('Success', 'Data berhasil dihapus');
            } catch (Exception $e) {
                DB::rollback();
                return response()->error('', 501, $e->getMessage());
            }
        } else {
            return response()->error('', 501, 'Data tidak ditemukan');
        }
    }


    public function get(Request $request)
    {
        $id = $request->vehicle_no;

        $header = Vehicle::select(
            'vehicle_no', 'descriptions', 'model', 'manufactur', 'year_production', 'police_no',
            'vin', 'chasis_no', 'vehicle_type', 'stnk_validate', 'kir_validate', 'tax_validate',
            'tire_type', 'pool_code', 'default_route_id', 'group_count', 'bank_id1', 'bank_id2',
            'device_id', 'is_active'
        )
        ->where('vehicle_no', $id)
        ->first();

        if ($header) {
            $document = Documents::where('doc_number', $id)
                ->where('doc_type', 'VCH')
                ->where('is_deleted', 0)
                ->get();
        }
        $data=[
            'header' => $header,
            'document'=>$document
        ];
        return response()->success('Success', $data);
    }

    public function document(Request $request)
    {
        $id = $request->vehicle_no;

        $data = Documents::where('doc_number', $id)
            ->where('doc_type', 'VCH')
            ->where('is_deleted', 0)  // '0' is used instead of the string '0' for consistency
            ->get();

        return response()->success('Success', $data);
    }


    public function post(Request $request)
    {
        $data = $request->json()->all();
        $opr = $data['operation'];
        $where = $data['where'];
        $rec = $data['data'];
        $rec['pool_code'] = PagesHelp::PoolCode($request);

        $validator = Validator::make($rec, [
            'vehicle_no' => 'required',
            'police_no' => 'required',
            'vin' => 'required',
            'default_route_id' => 'required'
        ], [
            'vehicle_no.required' => 'No. unit kendaraan harus diisi',
            'police_no.required' => 'No. Polisi harus diisi',
            'vin.required' => 'Nomor mesin harus diisi',
            'default_route_id.required' => 'Rute default harus diisi'
        ]);

        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->all());
        }

        DB::beginTransaction();
        try {
            if ($opr === 'updated') {
                Vehicle::where($where)->update($rec);
            } else if ($opr === 'inserted') {
                $rec['vehicle_status'] = 'Siap';
                Vehicle::insert($rec);
            }

            DB::commit();

            return response()->success('Success', 'Simpan data Berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }

    public function getVehicle(Request $request)
    {
        $pool_code  = PagesHelp::Session()->pool_code;
        $ready      = $request->state ?? '';
        $vehicle_no = $request->vehicle_no ?? '';

        $query = Vehicle::selectRaw("vehicle_no, CONCAT(police_no, ' - ', descriptions, ' - ', vehicle_no) AS descriptions, pool_code")
                        ->where('is_active', '1')
                        ->where('pool_code', $pool_code);

        if ($ready === 'siap') {
            $query->where(function($q) use ($pool_code) {
                $q->where('vehicle_status', 'Siap')
                ->whereRaw("IFNULL(ops_permit, '') <> ''")
                ->whereRaw("ops_permit_valid >= CURRENT_DATE()");
            });
        }

        if ($vehicle_no) {
            $query->orWhere('vehicle_no', $vehicle_no);
        }

        $data = $query->orderBy('police_no')->get();

        return response()->success('Success', $data);
    }


    public function vehicle_info(Request $request)
    {
        $vehicle = $request->vehicle_no ?? '';

        $data = Vehicle::selectRaw("vehicle_no, descriptions, police_no, default_route_id, odometer, IFNULL(ops_permit, '-') AS ops_permit, ops_permit_valid")
                    ->where("vehicle_no", $vehicle)
                    ->first();

        return response()->success('Success', $data);
    }


    public function vehicle_status(Request $request)
    {
        $filter  = $request->filter ?? '';
        $limit   = $request->limit;
        $sorting = ($request->descending == "true") ?'desc':'asc';
        $sortBy  = $request->sortBy;
        $status  = $request->status ?? '';
        $pool_code = PagesHelp::Session()->pool_code;

        // Update vehicle status
        DB::update("UPDATE m_vehicle SET vehicle_status='Pengecekan', ops_permit='', ops_permit_valid=NULL
            WHERE pool_code=? AND vehicle_status='Siap' AND ops_permit_valid < CURRENT_DATE() AND ops_permit_valid IS NOT NULL", [$pool_code]);

        $query = Vehicle::from('m_vehicle as a')
            ->selectRaw("
                a.sysid, a.vehicle_no, a.police_no, a.vehicle_status, b.route_name,
                d.personal_name AS driver, e.personal_name AS helper, f.personal_name AS conductor,
                c.doc_number, c.time_boarding, g.planning_date, g.planning_time, g.estimate_date, g.estimate_time,
                g.problem as service_problem, a.odometer, a.next_km_service, a.ops_permit, a.ops_permit_valid,
                a.stnk_validate, a.kir_validate, a.tax_validate, c.sysid as sysid_operation, c.is_storing,
                IFNULL(a.device_id, '') as device_id, IFNULL(h.devicename, '') as devicename,
                IFNULL(h.latitude, '') as latitude, IFNULL(h.longitude, '') as longitude, IFNULL(h.address, '') as road_name,
                h.update_date, h.ignition_status, h.speed, c.ref_date
            ")
            ->leftJoin('m_bus_route as b', 'a.default_route_id', '=', 'b.sysid')
            ->leftJoin('t_operation as c', 'a.last_operation', '=', 'c.sysid')
            ->leftJoin('m_personal as d', 'c.driver_id', '=', 'd.employee_id')
            ->leftJoin('m_personal as e', 'c.helper_id', '=', 'e.employee_id')
            ->leftJoin('m_personal as f', 'c.conductor_id', '=', 'f.employee_id')
            ->leftJoin('m_vehicle_service as g', 'a.vehicle_no', '=', 'g.vehicle_no')
            ->leftJoin('m_gps_device as h', 'a.device_id', '=', 'h.deviceid')
            ->where('a.pool_code', $pool_code)
            ->where('a.is_active', 1);

        // Apply filters
        if ($status) {
            $query->where('vehicle_status', $status);
        }

        if ($filter) {
            $filter = '%' . trim($filter) . '%';
            $query->where(function($q) use ($filter) {
                $q->where('a.vehicle_no', 'like', $filter)
                    ->orWhere('a.police_no', 'like', $filter)
                    ->orWhere('b.route_name', 'like', $filter)
                    ->orWhere('a.vehicle_status', 'like', $filter);
            });
        }

        $query->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }


    public function point(Request $request) {
        $filter = $request->filter;
        $limit = $request->limit ?? 10; // Default limit if not provided
        $descending = $request->descending === "true";
        $sortBy = $request->sortBy ?? 'route_name';
        $vehicle_no = $request->vehicle_no ?? '';

        $data = Vehicleroutepoint::from('m_vehicle_routepoint as a')
            ->select(
                'a.sysid', 'a.route_id', 'b.route_name', 'a.point', 'a.breakpoint',
                'a.target', 'a.target_min', 'a.target_min2', 'b.start_factor_go',
                'b.start_factor_end', 'b.dest_factor_go', 'b.dest_factor_end',
                'a.start_point_go', 'a.start_point_end', 'a.dest_point_go',
                'a.dest_point_end', 'a.is_active','a.uuid_rec'
            )
            ->leftJoin('m_bus_route as b', 'a.route_id', '=', 'b.sysid')
            ->where('a.vehicle_no', $vehicle_no);

        // Apply ordering
        $data = $data->orderBy($sortBy, $descending ? 'desc' : 'asc')
        ->paginate($limit);

        return response()->success('Success', $data);
    }


    public function destroy_point(Request $request)
    {
        $uuid = $request->uuid ?? '';

        $data = Vehicleroutepoint::where('uuid_rec', $uuid)->first();

        if (!$data) {
            return response()->error('', 501, 'Data tidak ditemukan');
        }
        DB::transaction(function () use ($data) {
            DB::delete("DELETE FROM m_vehicle_routepoint_cost WHERE sysid = ?", [$data->sysid]);
            DB::delete("DELETE FROM m_vehicle_routepoint_detail WHERE vehicle_no = ? AND route_id=?",
            [$data->vehicle_no,$data->route_id]);
            $data->delete();
        });

        return response()->success('Success', 'Data berhasil dihapus');

    }


    public function get_point(Request $request) {
        $uuid = $request->uuid ?? '';

        // Fetch route point data
        $routePoint = Vehicleroutepoint::from('m_vehicle_routepoint as a')
            ->select(
                'a.sysid', 'a.vehicle_no', 'a.route_id', 'b.route_name', 'a.point', 'a.breakpoint',
                'a.target', 'a.target_min', 'a.target_min2', 'b.start_factor_go',
                'b.start_factor_end', 'b.dest_factor_go', 'b.dest_factor_end',
                'a.start_point_go', 'a.start_point_end', 'a.dest_point_go',
                'a.dest_point_end', 'a.default_model', 'a.cost', 'a.is_active',
                'a.uuid_rec'
            )
            ->leftJoin('m_bus_route as b', 'a.route_id', '=', 'b.sysid')
            ->where('a.uuid_rec', $uuid)
            ->first();


        if (!$routePoint) {
            return response()->error('Route point not found', 404);
        }

        // Fetch associated costs
        $costs = VariableCost::from('m_vehicle_routepoint_cost as a')
            ->select('a.sysid', 'a.line_no', 'a.cost_id', 'a.cost_name', 'a.cost')
            ->where('a.sysid', $routePoint->sysid)
            ->get();

        $data = [
            'route_point' => $routePoint,
            'cost' => $costs,
        ];

        return response()->success('Success', $data);
    }


    public function state(Request $request) {
        // Get pool code from request or fallback to default
        $pool_code = $request->pool_code ?? PagesHelp::Session()->pool_code;

        // Update vehicle statuses
        DB::update(
            "UPDATE m_vehicle
            SET vehicle_status = 'Pengecekan',
                ops_permit = '',
                ops_permit_valid = NULL
            WHERE pool_code = ?
            AND vehicle_status = 'Siap'
            AND ops_permit_valid < CURRENT_DATE()
            AND ops_permit_valid IS NOT NULL",
            [$pool_code]
        );

        // Retrieve vehicle counts grouped by status
        $data = DB::table('m_vehicle')
            ->select('vehicle_status', DB::raw('COUNT(*) AS jumlah'))
            ->where('pool_code', $pool_code)
            ->where('is_active', 1)
            ->groupBy('vehicle_status')
            ->get();

        return response()->success('Success', $data);
    }


    public function more_detail(Request $request) {
        $state     = $request->state ?? 'Standby';  // Default state is "Standby"
        $pool_code = PagesHelp::Session()->pool_code;  // Get pool code

        // Base query for vehicle data
        $query = DB::table('m_vehicle as a')
            ->selectRaw('a.vehicle_no, a.descriptions, a.police_no')
            ->where('a.pool_code', $pool_code);

        // Determine the query details based on the vehicle state
        switch ($state) {
            case 'Standby':
                $query->leftJoin('m_bus_route as b', 'a.default_route_id', '=', 'b.sysid')
                    ->where('a.vehicle_status', 'Siap')
                    ->addSelect('b.route_name');
                break;
            case 'Operasi':
                $query->leftJoin('t_operation as b', function ($join) {
                        $join->on('a.vehicle_no', '=', 'b.vehicle_no')
                            ->on('b.is_closed', '=', DB::raw('0'));
                    })
                    ->leftJoin('m_bus_route as c', 'b.route_id', '=', 'c.sysid')
                    ->leftJoin('m_personal as d', 'b.driver_id', '=', 'd.employee_id')
                    ->leftJoin('m_personal as e', 'b.conductor_id', '=', 'e.employee_id')
                    ->where('a.vehicle_status', 'Beroperasi')
                    ->addSelect('c.route_name', 'd.personal_name as driver', 'e.personal_name as conductor', 'b.doc_number', 'b.ref_date');
                break;
            case 'Perbaikan':
                $query->leftJoin('t_workorder_service as b', function ($join) {
                        $join->on('a.vehicle_no', '=', 'b.vehicle_no')
                            ->on('b.is_closed', '=', DB::raw('0'))
                            ->on('b.is_cancel', '=', DB::raw('0'));
                    })
                    ->where('a.vehicle_status', 'Service')
                    ->addSelect('b.doc_number', 'b.ref_date', 'b.problem', 'b.user_service', 'b.service_planning');
                break;
            case 'Pengecekan':
                $query->leftJoin('m_bus_route as b', 'a.default_route_id', '=', 'b.sysid')
                    ->where('a.vehicle_status', 'Pengecekan')
                    ->addSelect('b.route_name');
                break;
            default:
                return response()->error('Invalid state', 400);
        }

        // Execute the query and return the data
        $data = $query->get();
        return response()->success('Success', $data);
    }


    public function post_point(Request $request) {
        $data = $request->json()->all();
        $record = $data['data'];
        $go   = $data['go'];
        $back = $data['back'];
        $cost = $data['cost'];

        // Validation
        $validator = Validator::make($record, [
            'vehicle_no' => 'bail|required',
            'route_id' => 'bail|required',
        ], [
            'vehicle_no.required' => 'No. unit kendaraan harus diisi',
            'route_id.required' => 'Rute unit harus diisi',
        ]);

        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->first());
        }

        $session= PagesHelp::Session();

        DB::beginTransaction();
        try {
            // Insert or update route point record
            $vrp=Vehicleroutepoint::where('uuid_rec',$record['uuid_rec']??'')->first();
            if(!$vrp) {
                $vrp  = new Vehicleroutepoint();
                $vrp->uuid_rec  = Str::uuid();
            }
            $vrp->fill([
                'vehicle_no'       => $record['vehicle_no'],
                'route_id'         => $record['route_id'],
                'point'            => $record['point'],
                'breakpoint'       => $record['breakpoint'],
                'target'           => $record['target'] ?? 0,
                'target_min'       => $record['target_min'] ?? 0,
                'target_min2'      => $record['target_min2'],
                'start_point_go'   => $record['start_point_go'],
                'start_point_end'  => $record['start_point_go'],
                'dest_point_go'    => $record['dest_point_go'],
                'dest_point_end'   => $record['dest_point_end'],
                'cost'             => $record['cost'],
                'default_model'    => $record['default_model'],
                'is_active'        => $record['is_active'],
                'update_userid'    => $session->user_id,
                'update_timestamp' => Date('Y-m-d H:i:s')
            ]);
            $vrp->save();
            $sysid = $vrp->sysid;

            // Delete previous related details
            DB::delete("DELETE FROM m_vehicle_routepoint_cost WHERE sysid=?", [$sysid]);
            DB::delete("DELETE FROM m_vehicle_routepoint_detail WHERE vehicle_no=? AND route_id=?",
                        [$record['vehicle_no'], $record['route_id']]);

            // Insert 'GO' route details
            foreach ($go as $row) {
                $this->insertRoutePointDetail($sysid,$record, $row, 'GO');
            }

            // Insert 'BACK' route details
            foreach ($back as $row) {
                $this->insertRoutePointDetail($sysid,$record, $row, 'BACK');
            }

            // Insert costs
            foreach ($cost as $row) {
                $this->insertCost($sysid, $row);
            }

            DB::commit();
            return response()->success('Success', 'Simpan data Berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e->getMessage());
        }
    }

    // Helper function to insert route point details
    private function insertRoutePointDetail($sysid,$record, $row, $flagRoute) {
        $detail = [
            'vehicle_no' => $record['vehicle_no'],
            'route_id' => $record['route_id'],
            'flag_route' => $flagRoute,
            'checkpoint' => $row['checkpoint_sysid'],
            'point' => $row['point'],
            'sysid'=>$sysid
        ];
        DB::table('m_vehicle_routepoint_detail')
        ->insert($detail);
    }

    // Helper function to insert costs
    private function insertCost($sysid, $row) {
        $costDetail = (array) $row;
        $costDetail['sysid'] = $sysid;
        DB::table('m_vehicle_routepoint_cost')
        ->insert($costDetail);
    }


    public function change_pool(Request $request){
        $data= $request->json()->all();
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'vehicle_no'=>'bail|required',
            'pool_code'=>'bail|required'
        ],[
            'vehicle_no.required'=>'No unit harus diisi',
            'pool_code.required'=>'Pool tujuan harus diisi harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if ($rec['pool_code']=='-'){
            return response()->error('',501,'Pool tujuan belum dipilih');
        }
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        try{
            vehicle::where($where)
                ->update(['pool_code'=>$rec['pool_code'],
                          'default_route_id'=>'-1']);
            return response()->success('Success','Perpindahan ke pool tujuan berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }

    public function getstoring(Request $request){
        $sysid=$request->opsid;
        $data = Storing::
        where('sysid_operation',$sysid)
        ->where('is_closed','0')
        ->first();
        return response()->success('Success',$data);
    }

    public function gps(Request $request){
        $data=Devices::selectRaw("deviceid,CONCAT(devicename,' DEVICE :',deviceid,' Telp :',gsm_phone) as list")
        ->orderBy("devicename")->get();
        return response()->success('Success',$data);
    }

    public function poststoring(Request $request){
        $req= $request->json()->all();
        $data=$req['data'];
        $data['pool_code']=PagesHelp::PoolCode($request);
        DB::beginTransaction();
        try{
            if ($data['sysid']=='-1'){
                unset($data['sysid']);
                $data['update_timestamp']=Date('Y-m-d');
                Storing::insert($data);
            } else {
                $data['update_timestamp']=Date('Y-m-d');
                Storing::where('sysid',$data['sysid'])
                ->update($data);
            }
            DB::table('t_operation')
            ->where('sysid',$data['sysid_operation'])
            ->update(['is_storing'=>'1']);
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function monitoring(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ?'desc' :'asc';
        $sortBy = $request->sortBy;
        $all =isset($request->all) ? $request->all :'1';
        $pool_code=isset($request->pool_code) ? $request->pool_code :'';

        DB::update("UPDATE m_vehicle SET vehicle_status='Pengecekan',ops_permit='',ops_permit_valid=NULL
        WHERE pool_code=? AND vehicle_status='Siap' AND ops_permit_valid< CURRENT_DATE() AND ops_permit_valid IS NOT NULL",[$pool_code]);

        $data = Vehicle::from('m_vehicle as a')
            ->selectRaw("a.sysid,a.vehicle_no,a.police_no,a.vehicle_status,b.ignition_status,b.speed,b.update_date,a.device_id,
                IFNULL(b.devicename,'') as devicename,IFNULL(b.latitude,'') as latitude,IFNULL(b.longitude,'') as longitude,
                IFNULL(b.address,'') as road_name,c.doc_number,c.ref_date,d.personal_name as driver_name")
            ->leftJoin('m_gps_device as b', 'a.device_id', '=', 'b.deviceid')
            ->leftJoin('t_operation as c', 'a.last_operation', '=', 'c.sysid')
            ->leftJoin('m_personal as d', 'c.driver_id', '=', 'd.employee_id')
            ->where('a.is_active',1)
            ->whereRaw("IFNULL(a.device_id,'')<>''");
            if (!($pool_code=='ALL')) {
                $data=$data->where('a.pool_code',$pool_code);
            }
            if ($all=='0'){
                $data=$data->where('a.vehicle_status','Beroperasi');
            }

            if (!($filter=='')){
                $filter='%'.trim($filter).'%';
                $data=$data->where(function($q) use ($filter) {
                        $q->where('a.vehicle_no','like',$filter)
                        ->orwhere('a.police_no','like',$filter)
                        ->orwhere('a.vehicle_status','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function gps_update(Request $request) {
        $data = Vehicle::from('m_vehicle as a')
        ->selectRaw("a.sysid,a.vehicle_no,a.police_no,a.vehicle_status,b.ignition_status,b.speed,b.update_date,a.device_id,
            IFNULL(b.devicename,'') as devicename,IFNULL(b.latitude,'') as latitude,IFNULL(b.longitude,'') as longitude,
            IFNULL(b.address,'') as road_name,c.doc_number,c.ref_date,d.personal_name as driver_name")
        ->leftJoin('m_gps_device as b', 'a.device_id', '=', 'b.deviceid')
        ->leftJoin('t_operation as c', 'a.last_operation', '=', 'c.sysid')
        ->leftJoin('m_personal as d', 'c.driver_id', '=', 'd.employee_id')
        ->where("a.vehicle_no",isset($request->vehicle_no)? $request->vehicle_no:'')
        ->first();
        if ($data) {
            $config=DB::table('o_system')->selectRaw("key_word,key_value_nvarchar")
            ->whereRaw("key_word='GPS_URL'")->first();
            $url=$config->key_value_nvarchar;
            $token=PagesHelp::GetToken();
            if ($token==''){
                PagesHelp::GenerateToken();
                $token=PagesHelp::GetToken();
            }
            $data->update= false;
            if (!($token=='')) {
                $url_realtime=$url."/api_positions/realtime/".$token.'?devices%5B0%5D%5Bid%5D='.$data->device_id;
                $json=null;
                $log=PagesHelp::curl_data($url_realtime,null,false);
                if ($log['status']===true){
                    $json=$log['json'];
                }
                //$data->logs=$log;
                if ($json){
                    if ($json['status_code']==200) {
                        $log=$json['data']['data'];
                        if ($log){
                            $data->update= true;
                            $gps=$log[$data->device_id][0];
                            //return response()->success('Success', $gps);
                            $data->latitude=$gps['alat'];
                            $data->longitude=$gps['along'];
                            $data->road_name=$gps['roadname'];
                            $data->ignition_status=$gps['ignition_status'];
                            $data->speed=$gps['aspeed'];
                            $data->update_date=$gps['ldatetime'];

                            Devices::where('deviceid',$data->device_id)
                            ->update([
                                'longitude'=>$gps['along'],
                                'latitude'=>$gps['alat'],
                                'address'=>$gps['roadname'],
                                'speed'=>$gps['aspeed'],
                                'ignition_status'=>$gps['ignition_status'],
                                'update_date'=>$gps['ldatetime']
                            ]);
                        }
                    } else if ($json['status_code']==403) {
                        PagesHelp::GenerateToken();
                        $token=PagesHelp::GetToken();
                    }
                }
            }
        }
        return response()->success('Success', $data);
    }
}
