<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class ServicePeriodicsDetail extends Model
{
    protected $table = 't_workorder_periodic_detail';
    public $timestamps = false;
    protected $casts = [
        'is_service' => 'string'
    ];
}
