<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class ServiceExternalDetail extends Model
{
    protected $table = 't_workorder_external_detail';
    public $timestamps = false;
    protected $casts = [
        'qty' => 'float',
        'price' => 'float',
        'total' => 'float'
    ];
}

