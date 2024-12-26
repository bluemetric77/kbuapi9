<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class InTransit extends Model
{
    protected $table = 't_intransit';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'deposit'=>'float',
        'undeposit'=>'float',
        'update_timestap'=>'datetime'
    ];
}
