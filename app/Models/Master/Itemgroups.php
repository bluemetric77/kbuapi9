<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Itemgroups extends Model
{
    protected $table = 'm_item_group';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts =[
        'record_stock'=>'string'
    ];
}
