<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ObjectItem extends Model
{
    protected $table = 'o_object_items';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
}
