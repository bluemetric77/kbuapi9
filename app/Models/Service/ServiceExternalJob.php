<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class ServiceExternalJob extends Model
{
    protected $table = 't_workorder_external_job';
    public $timestamps = false;
    protected $casts = [
        'qty' => 'float',
        'price' => 'float',
        'total' => 'float'
    ];
}

