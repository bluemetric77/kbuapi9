<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CustomerOrder extends Model
{
    protected $table = 't_customer_order';
    protected $primaryKey = 'transid';
    public $timestamps = true;
    protected $hidden=['cost_id','contract_unit','db_version','app_version','update_userid','update_timestamp','update_location'];
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'price'=>'float',
        'qty'=>'float',
        'standart_price'=>'float',
        'fleet_cost'=>'float'
    ];

    public static function GenerateNumber($ref_date){
        $year=substr($ref_date,0,4);
        $month=substr($ref_date,5,2);
        $data=DB::table('t_customer_order')
             ->select(DB::raw('MAX(order_no) as doc_number'))
             ->whereRaw('YEAR(entry_date)=? AND MONTH(entry_date)=?',[$year,$month])
             ->first();
        $number=substr($data->doc_number,9,4);
        $num = ($number=='') ? 0 : intval($number);
        $counter = strval($num + 1);
        $counter = str_pad($counter, 4 ,"0", STR_PAD_LEFT);
        $prefix=substr($ref_date,2,2).substr($ref_date,5,2);
        return $number='ORD-'.$prefix.'-'.$counter;
    }
}

