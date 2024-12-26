<?php

namespace App\Models\General;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Documents extends Model
{
    protected $table = 't_attachment';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
}
