<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Fleetcost extends Model
{
    protected $table = 'm_fleet_cost';
    protected $primaryKey = 'id';
    protected $hidden=['db_version','app_version','update_userid','update_timestamp','update_location'];
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';

}
