<?php

namespace App\Http\Controllers\Ops;

use App\Models\Ops\Drivergroup;
use App\Models\Master\Driver;
use App\Models\Master\Vehicle;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class DrivergroupController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ? 'desc' : 'asc';
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);
        $vehicle_no = $request->vehicle_no ?? '';

        $data = Drivergroup::from('m_driver_group as a')
            ->selectRaw('a.sysid, a.vehicle_no, b.police_no, a.group_name, a.driver_id, c.personal_name AS driver,
                a.helper_id, d.personal_name AS helper, a.conductor_id, e.personal_name AS conductor, a.uuid_rec')
            ->leftJoin('m_vehicle as b', 'a.vehicle_no', '=', 'b.vehicle_no')
            ->leftJoin('m_personal as c', 'a.driver_id', '=', 'c.employee_id')
            ->leftJoin('m_personal as d', 'a.helper_id', '=', 'd.employee_id')
            ->leftJoin('m_personal as e', 'a.conductor_id', '=', 'e.employee_id')
            ->where('a.pool_code', $pool_code)
            ->where('a.vehicle_no', $vehicle_no);

        if ($filter) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function($q) use ($filter) {
                $q->where('c.personal_name', 'like', $filter)
                    ->orWhere('d.personal_name', 'like', $filter)
                    ->orWhere('e.personal_name', 'like', $filter);
            });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }

    public function destroy(Request $request){
        $uuid=$request->uuid ?? '';
        DB::beginTransaction();
        try {
            $data=Drivergroup::where('uuid_rec',$uuid)->first();
            if (!$data) {
                DB::rollback();
                return response()->error('',501,'Data tidak ditemukan');
            }
            $data->delete();
            $count=Drivergroup::where('vehicle_no',$data->vehicle_no)->get()->count();
            Vehicle::where('vehicle_no',$data->vehicle_no)
            ->update(['group_count'=>$count]);
            DB::commit();
        } catch(Exception $e){
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function get(Request $request){
        $uuid=$request->uuid ?? '';
        $data=Drivergroup::
        selectRaw("sysid,vehicle_no,pool_code,group_name,driver_id, helper_id, conductor_id, uuid_rec")
        ->where('uuid_rec',$uuid)
        ->first();
        return response()->success('Success',$data);
    }

    public function post(Request $request)
    {
        $data = $request->json()->all();
        $rec = $data['data'];
        $session = PagesHelp::Session();
        $rec['pool_code'] = $session->pool_code;

        $validator = Validator::make($rec, [
            'vehicle_no' => 'bail|required',
            'pool_code' => 'bail|required',
            'group_name' => 'bail|required',
            'driver_id' => 'bail|required'
        ], [
            'vehicle_no.required' => 'No. unit kendaraan harus diisi',
            'pool_code.required' => 'Pool kendaraan harus diisi',
            'group_name.required' => 'Nama group batangan harus diisi',
            'driver_id.required' => 'Pengemudi harus diisi'
        ]);

        if ($validator->fails()) {
            return response()->error('', 501, $validator->errors()->first());
        }

        DB::beginTransaction();
        try {
            $sysid = $rec['sysid'];
            unset($rec['sysid']);
            $driver = Drivergroup::where('uuid_rec', $rec['uuid_rec'] ?? '')->first();

            if (!$driver) {
                $driver = new Drivergroup();
                $driver->uuid_rec = Str::uuid();
            }

            $driver->pool_code    = $rec['pool_code'];
            $driver->vehicle_no   = $rec['vehicle_no'];
            $driver->group_name   = $rec['group_name'];
            $driver->driver_id    = $rec['driver_id'];
            $driver->helper_id    = $rec['helper_id'];
            $driver->conductor_id = $rec['conductor_id'];
            $driver->update_userid = $session->user_id;
            $driver->update_timestamp = Date('Y-m-d H:i:s');
            $driver->save();

            $count = Drivergroup::where('vehicle_no', $rec['vehicle_no'])->get()->count();

            DB::table('m_vehicle')
                ->where('vehicle_no', $rec['vehicle_no'])
                ->update(['group_count' => $count]);

            DB::commit();
            return response()->success('Success', 'Simpan data Berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }

    public function getgroup(Request $request)
    {
        $vehicle_no = $request->vehicle_no;

        $data = Drivergroup::from('m_driver_group as a')
            ->selectRaw('a.sysid, a.driver_id, a.helper_id, a.conductor_id, b.personal_name AS driver, c.personal_name AS helper, d.personal_name AS conductor')
            ->leftJoin('m_personal as b', 'a.driver_id', '=', 'b.employee_id')
            ->leftJoin('m_personal as c', 'a.helper_id', '=', 'c.employee_id')
            ->leftJoin('m_personal as d', 'a.conductor_id', '=', 'd.employee_id')
            ->where('a.vehicle_no', $vehicle_no)
            ->get();

        return response()->success('Success', $data);
    }

}
