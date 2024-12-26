<?php

namespace App\Helpers;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Ops\InTransit;
use App\Models\Finance\OpsCashier;
use App\Models\Master\Fiscalyears;
use App\Models\Master\Account;
use PagesHelp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Accounting {
    public static function Rollback($sysid){
      if ($sysid==-1){
        return true;
        exit(0);
      }
      $setup=DB::table('o_account_general')
      ->selectRaw("IFNULL(earning_account,'')as earning_account,IFNULL(retainearning_account,'') as retainearning_account")
      ->where('id',1)
      ->first();
      $jurnal=Journal2::from('t_jurnal2 as a')
        ->selectRaw("a.no_account,IFNULL(a.debit,0) as debit,IFNULL(b.reference1,'') as reference1,
        IFNULL(a.credit,0) as credit,a.project,a.fiscal_month,a.fiscal_year,b.trans_code")
        ->leftjoin('t_jurnal1 as b','a.sysid','=','b.sysid')
        ->where('a.sysid',$sysid)->get();
      if ($jurnal){
        $earning=$setup->earning_account;
        $retain=$setup->retainearning_account;
        foreach($jurnal as $row){
            if (!($row['reference1']=='')) {
              InTransit::where('doc_type',$row['trans_code'])
                        ->where('doc_number',$row['reference1'])
                  ->update(['is_deleted'=>'1']);
            }
            $month=$row->fiscal_month;
            $year=$row->fiscal_year;
            $field_debit='debit_month'.$month;
            $field_credit='credit_month'.$month;
            DB::update("UPDATE t_account_mutation SET
                $field_debit=IFNULL($field_debit,0)-?,
                $field_credit=IFNULL($field_credit,0)-?
                WHERE fiscal_year=? AND no_account=? AND project=?",[$row->debit,$row->credit,$year,$row->no_account,$row->project]);
            if (substr($row->no_account,0,1)>'3') {
                DB::update("UPDATE t_account_mutation SET
                    $field_debit=IFNULL($field_debit,0)-?,
                    $field_credit=IFNULL($field_credit,0)-?
                    WHERE fiscal_year=? AND no_account=? AND project=?",[$row->debit,$row->credit,$year,$earning,$row->project]);
            }
        }
        $timestamp=Date('Y-m-d H:m:s');
        DB::insert("INSERT INTO h_jurnal1
                   SELECT ?,a.* FROM t_jurnal1 a WHERE a.sysid=?",[$timestamp,$sysid]);
        DB::insert("INSERT INTO h_jurnal2
                   SELECT ?,a.* FROM t_jurnal2 a WHERE a.sysid=?",[$timestamp,$sysid]);
        Journal2::where('sysid',$sysid)->delete();
      }
      return true;
    }

    public static function Posting($sysid,$request=null){
      $info['state']=false;
      $info['message']='';
      $jurnal1=Journal1::selectRaw("sysid,ref_date,fiscal_month,fiscal_year,is_verified,is_void,trans_code,trans_series,notes")
        ->where('sysid',$sysid)->first();
      $message="";
      if ($jurnal1) {
        if ($jurnal1->is_verified=='1') {
            $message="Jurnal ".$jurnal1->trans_code." - ".$jurnal1->trans_series." sudah terverifikasi";
        } else if ($jurnal1->is_void=='1') {
          $message="Jurnal ".$jurnal1->trans_code." - ".$jurnal1->trans_series." sudah divoid";
        } else {
          $valid =Fiscalyears::IsValid($jurnal1->ref_date);
          if ($valid['state']==false){
            $message=$valid['message'];
          }
        }
      } else {
        $message='Data tidak ditemukan - ('.$sysid.')';
      }

      if (!($message=='')){
        $info['message']=$message;
        return $info;
        exit();
      }
      DB::update("UPDATE t_jurnal2 a INNER JOIN t_jurnal1 b ON a.sysid=b.sysid
        SET a.transtype=b.transtype,a.ref_date=b.ref_date,a.fiscal_year=b.fiscal_year,
        a.fiscal_month=b.fiscal_month,a.enum_drcr=CASE (IFNULL(a.debit,0)<>0)
        WHEN 1 THEN 'D'
        ELSE 'C' END
        WHERE a.sysid=?",[$sysid]);

      DB::update("UPDATE t_jurnal2 a INNER JOIN m_account b ON a.no_account=b.account_no
        SET a.description=b.account_name
        WHERE a.sysid=?",[$sysid]);

      $setup=Accounting::Config();

      if (($setup->earning_account=='') || (!($setup->earning_account))) {
        $info['message']='Akun Laba/rugi berjalan belum di setup';
        return $info;
        exit();
      } else if (($setup->retainearning_account=='') || (!($setup->retainearning_account))) {
        $info['message']='Akun Laba/rugi ditahan belum di setup';
        return $info;
        exit();
      }
      $isExists=DB::table('m_account')->select('account_no')
      ->where('account_no',$setup->earning_account)
      ->where('is_posted','1')
      ->where('is_active','1')
      ->first();
      if (!($isExists)){
        $info['message']='Akun Laba/rugi berjalan salah/tidak terdaftar dimaster akun';
        return $info;
        exit();
      }
      $isExists=DB::table('m_account')->select('account_no')
      ->where('account_no',$setup->retainearning_account)
      ->where('is_posted','1')
      ->where('is_active','1')
      ->first();
      if (!($isExists)){
        $info['message']='Akun Laba/rugi ditahan salah/tidak terdaftar dimaster akun';
        return $info;
        exit();
      }
      $jurnal=Journal2::from('t_jurnal2 as a')
      ->selectRaw("a.sysid,a.no_account,a.project,a.debit,a.credit,a.description,a.line_memo,a.reference1,c.ref_date,c.trans_code,
       IFNULL(b.project_mandatory,0) as project_mandatory,a.fiscal_month,a.fiscal_year,IFNULL(b.is_active,0) as is_active,IFNULL(b.is_posted,0) as is_posted,
       IFNULL(b.account_no,'') as account_no,IFNULL(b.intransit,0) as intransit,c.pool_code,c.update_timestamp,c.update_userid,
       a.transtype,c.is_void")
      ->leftjoin('m_account as b','a.no_account','=','b.account_no')
      ->leftjoin('t_jurnal1 as c','a.sysid','=','c.sysid')
      ->where('a.sysid',$sysid)->get();
      $debit =0;
      $credit=0;
      foreach($jurnal as $row){
        if ((($row->project=='00') || ($row->project=='')) && ($row->project_mandatory=='1')){
            $info['message']='Akun '.$row->no_account.'-'.$row->line_memo.' belum diisi proyek-nya';
            return $info;
            exit();
        } else if ($row->account_no==''){
            $info['message']='Akun '.$row->no_account.'-'.$row->description.' tidak ditemukan ('.$row->line_memo.')';
            return $info;
            exit();
        } else if ($row->is_active==0){
            $info['message']='Akun '.$row->no_account.'-'.$row->description.' sudah tidak aktif ('.$row->line_memo.')';
            return $info;
            exit();
        } else if ($row->is_posted==0){
            $info['message']='Akun '.$row->no_account.'-'.$row->description.' adalah akun header (tidak bisa di posting)';
            return $info;
            exit();
        }
        $debit=$debit+floatval($row->debit);
        $credit=$credit+floatval($row->credit);
      }
      $debit=round($debit,2);
      $credit=round($credit,2);
      if (!($debit==$credit)) {
            $info['message']='Jurnal Debit & Kredit tidak balance/sama  DEBIT :'.$debit.' KREDIT :'.$credit;
            return $info;
            exit();
      }
      DB::update("UPDATE t_jurnal2 a INNER JOIN m_account b ON a.no_account=b.account_no
        SET a.description=b.account_name
        WHERE a.sysid=?",[$sysid]);

      if ($jurnal){
        InTransit::where('doc_type',$row['trans_code'])
        ->where('doc_number',$row['reference1'])->delete();
        $earning=$setup->earning_account;
        $retain=$setup->retainearning_account;
        foreach($jurnal as $row){
            if (($row['intransit']==1) && ($row['transtype']==1) && ($row['is_void']=='0')) {
                $ops=OpsCashier::from("t_operation_cashier as a")
                ->selectRaw("IFNULL(a.target2,0) as target2,
                            IFNULL(a.others,0) as others,
                            IFNULL(b.vehicle_no,'') as vehicle_no,
                            IFNULL(c.bank_id1,-1) as bank_id1,
                            IFNULL(c.bank_id2,-1) as bank_id2")
                ->join("t_operation as b","a.sysid_operation","=","b.sysid")
                ->leftjoin("m_vehicle as c","b.vehicle_no","=","c.vehicle_no")
                ->where('a.doc_number',$row['reference1'])
                ->where('a.sysid_jurnal',$row['sysid'])->first();
                if ($ops) {
                  $amount=floatval($row['debit']);
                  if ($row['trans_code']=='SL') {
                    $is_deposit=($amount==0) ? 1 : 0;
                    InTransit::insert(
                    [
                      'pool_code'=>$row['pool_code'],
                      'doc_type'=>$row['trans_code'],
                      'doc_number'=>$row['reference1'],
                      'ref_date'=>$row['ref_date'],
                      'descriptions'=>$row['line_memo'],
                      'amount'=>$amount,
                      'no_account'=>$row['no_account'],
                      'vehicle_no'=>$ops->vehicle_no,
                      'cash_id'=>$ops->bank_id1,
                      'update_timestamp'=>Date('Y-m-d H:i:s'),
                      'update_userid'=>$row['update_userid'],
                      'is_deposit'=>$is_deposit
                    ]);
                    if (floatval($ops->target2)>0) {
                      InTransit::insert(
                      [
                        'pool_code'=>$row['pool_code'],
                        'doc_type'=>$row['trans_code'],
                        'doc_number'=>$row['reference1'],
                        'ref_date'=>$row['ref_date'],
                        'descriptions'=>$row['line_memo'],
                        'amount'=>floatval($ops->target2),
                        'no_account'=>$row['no_account'],
                        'vehicle_no'=>$ops->vehicle_no,
                        'cash_id'=>$ops->bank_id2,
                        'update_timestamp'=>Date('Y-m-d H:i:s'),
                        'update_userid'=>$row['update_userid']
                      ]);
                    }
                    if (floatval($ops->others)>0) {
                      $others=DB::table('t_operation_cashier as a')->selectRaw("c.bank_id,SUM(b.amount) as amount")
                        ->join('t_operation_others as b','a.sysid','=','b.sysid')
                        ->join('m_others_item as c','b.item_code','=','c.item_code')
                        ->where('a.doc_number',$row['reference1'])
                        ->groupby('c.bank_id')
                        ->get();
                      foreach($others as $rowothers) {
                        InTransit::insert(
                        [
                          'pool_code'=>$row['pool_code'],
                          'doc_type'=>$row['trans_code'],
                          'doc_number'=>$row['reference1'],
                          'ref_date'=>$row['ref_date'],
                          'descriptions'=>'Biaya Lain2',
                          'amount'=>floatval($rowothers->amount),
                          'no_account'=>'-',
                          'vehicle_no'=>$ops->vehicle_no,
                          'cash_id'=>$rowothers->bank_id,
                          'update_timestamp'=>Date('Y-m-d H:i:s'),
                          'update_userid'=>$row['update_userid']
                        ]);
                      }
                    }
                    DB::update("UPDATE t_intransit a
                      INNER JOIN `t_operation_cashier` b ON a.doc_number=b.doc_number
                      SET a.update_userid=b.update_userid WHERE a.doc_number=?",[$row['reference1']]);
                  } else {
                    InTransit::insert(
                    [
                      'pool_code'=>$row['pool_code'],
                      'doc_type'=>$row['trans_code'],
                      'doc_number'=>$row['reference1'],
                      'ref_date'=>$row['ref_date'],
                      'descriptions'=>$row['line_memo'],
                      'amount'=>$amount,
                      'no_account'=>$row['no_account'],
                      'vehicle_no'=>'',
                      'cash_id'=>-1,
                      'update_timestamp'=>Date('Y-m-d H:i:s'),
                      'update_userid'=>$row['update_userid']
                    ]);
                  }
                }
            }

            $month=$row->fiscal_month;
            $year=$row->fiscal_year;
            $field_debit='debit_month'.$month;
            $field_credit='credit_month'.$month;

            //Check if exists
            $mutasi=DB::table('t_account_mutation')
                ->where('fiscal_year',$year)
                ->where('no_account',$row->no_account)
                ->where('project',$row->project)
                ->first();
            if (!($mutasi)) {
                DB::table('t_account_mutation')
                 ->insert([
                    'fiscal_year'=>$year,
                    'no_account'=>$row->no_account,
                    'project'=>$row->project,
                    'begining_balance'=>0,
                    'debit_month1'=>0,
                    'credit_month1'=>0,
                    'debit_month2'=>0,
                    'credit_month2'=>0,
                    'debit_month3'=>0,
                    'credit_month3'=>0,
                    'debit_month4'=>0,
                    'credit_month4'=>0,
                    'debit_month5'=>0,
                    'credit_month5'=>0,
                    'debit_month6'=>0,
                    'credit_month6'=>0,
                    'debit_month7'=>0,
                    'credit_month7'=>0,
                    'debit_month8'=>0,
                    'credit_month8'=>0,
                    'debit_month9'=>0,
                    'credit_month9'=>0,
                    'debit_month10'=>0,
                    'credit_month10'=>0,
                    'debit_month11'=>0,
                    'credit_month11'=>0,
                    'debit_month12'=>0,
                    'credit_month12'=>0
                 ]);
            }
            DB::update("UPDATE t_account_mutation SET
                $field_debit=IFNULL($field_debit,0)+?,
                $field_credit=IFNULL($field_credit,0)+?
                WHERE fiscal_year=? AND no_account=? AND project=?",[$row->debit,$row->credit,$year,$row->no_account,$row->project]);

            /* Earning Account */
            if (substr($row->no_account,0,1)>'3') {
                //Check if Exists
                $mutasi=DB::table('t_account_mutation')
                    ->where('fiscal_year',$year)
                    ->where('no_account',$earning)
                    ->where('project',$row->project)
                    ->first();
                if (!($mutasi)) {
                    DB::table('t_account_mutation')
                    ->insert([
                        'fiscal_year'=>$year,
                        'no_account'=>$earning,
                        'project'=>$row->project,
                        'begining_balance'=>0,
                        'debit_month1'=>0,
                        'credit_month1'=>0,
                        'debit_month2'=>0,
                        'credit_month2'=>0,
                        'debit_month3'=>0,
                        'credit_month3'=>0,
                        'debit_month4'=>0,
                        'credit_month4'=>0,
                        'debit_month5'=>0,
                        'credit_month5'=>0,
                        'debit_month6'=>0,
                        'credit_month6'=>0,
                        'debit_month7'=>0,
                        'credit_month7'=>0,
                        'debit_month8'=>0,
                        'credit_month8'=>0,
                        'debit_month9'=>0,
                        'credit_month9'=>0,
                        'debit_month10'=>0,
                        'credit_month10'=>0,
                        'debit_month11'=>0,
                        'credit_month11'=>0,
                        'debit_month12'=>0,
                        'credit_month12'=>0
                    ]);
                }
                DB::update("UPDATE t_account_mutation SET
                    $field_debit=IFNULL($field_debit,0)+?,
                    $field_credit=IFNULL($field_credit,0)+?
                    WHERE fiscal_year=? AND no_account=? AND project=?",[$row->debit,$row->credit,$year,$earning,$row->project]);
            }
        }
        if ($request==null) {
          $userid = '-';
        } else {
          $userid = PagesHelp::UserID($request);
        }
        $ip  = request()->ip();
        Journal1::where('sysid',$sysid)
              ->update(['debit'=>$debit,'credit'=>$credit,'verified_by'=>'-',
              'update_timestamp'=>now(),'update_userid'=>$userid,
              'update_location'=>$ip]);
      }
      $info['state']=true;
      return $info;
    }
    public static function Config(){
        $config=DB::table('o_account_general')->where('id',1)->first();
        return $config;
    }
    public static function Verified($sysid,$request){
        $userid = PagesHelp::UserID($request);
        Journal1::where('sysid',$sysid)
              ->update(['is_verified'=>1,'verified_by'=>$userid,'verified_date'=>now()]);
    }
    public static function UnVerified($sysid){
        Journal1::where('sysid',$sysid)
              ->update(['is_verified'=>0]);
    }

    private static function begining_balance($project,$no_account,$date) {
        $realdate = date_create($date);
        $month=intval(date_format($realdate,'m'));
        $year=date_format($realdate,'Y');
        $data=DB::table('t_account_mutation')
        ->where('fiscal_year',$year)
        ->where('no_account',$no_account);
        if (!($project=='00')){
          $data= $data->where('project',$project);
        }
        $data=$data->first();
        $value=0;
        if ($data) {
          if (substr($no_account,0,1)=='1'){
            $value= floatval($data->begining_balance);
            for ($i=1;$i<$month;$i++){
              $debit='debit_month'.strval($i);
              $credit='credit_month'.strval($i);
              $value = $value+ (floatval($data->$debit)-floatval($data->$credit));
            }
          } else {
            $value= 0 - floatval($data->begining_balance);
            for ($i=1;$i<$month;$i++){
              $debit='debit_month'.strval($i);
              $credit='credit_month'.strval($i);
              $value = $value+ (floatval($data->$credit)-floatval($data->$debit));
            }
          }
        }
        $sdate=date_create($year."-".$month."-01");
        $start_date=date_format($sdate,'Y-m-d');
        $mutasi= DB::table('t_jurnal2')->selectRaw('SUM(debit) as debit,SUM(credit) as credit')
          ->where('no_account',$no_account)
          ->where('ref_date','>=',$start_date)
          ->where('ref_date','<',$date);
        if (!($project=='00')){
          $mutasi= $mutasi->where('project',$project);
        }
        $mutasi=$mutasi->first();
        if (substr($no_account,0,1)=='1'){
          $value = $value + (floatval($mutasi->debit)-floatval($mutasi->credit));
        } else {
          $value = $value + (floatval($mutasi->credit)-floatval($mutasi->debit));
        }
        return $value;
    }

    public static function GeneralLedger($no_account,$project,$date1,$date2,$limit,$all=false){
       $data=Journal2::from('t_jurnal2 as a')
       ->selectRaw('a.sysid,b.pool_code,a.ref_date,b.trans_code,b.trans_series,a.line_memo,a.debit,a.credit,a.project,
                    a.reference1,a.reference2,b.is_void,b.is_verified,b.verified_date')
       ->join('t_jurnal1 as b','a.sysid','=','b.sysid')
       ->where('a.ref_date','>=',$date1)
       ->where('a.ref_date','<=',$date2)
       ->where('a.no_account','=',$no_account);
       if (!($project=='00')) {
         $data=$data->where('project',$project);
       }
       if ($all==false) {
          $data=$data->orderBy('a.ref_date','asc')
            ->orderBy('a.sysid','asc')
            ->paginate($limit);
          $realdate = date_create($date1);
          $realdate->modify('-1 day');

          $data=$data->toArray();
          $rows=array();

          $lines=array();
          $begining=Accounting::begining_balance($project,$no_account,$date1);
          $line['sysid']=-1;
          $line['line_memo']='Saldo Awal s.d '.date_format($realdate, 'd-m-Y');
          $line['ref_date']=date_format($realdate, 'Y-m-d');
          $line['begining']=0;
          $line['debit']=0;
          $line['credit']=0;
          $line['last'] =$begining;
          $line['is_void'] ='0';
          $line['is_verified'] ='1';
          $line['verified_date'] =null;
          $lines[]=$line;
          $last=floatval($line['last']);
          foreach($data['data'] as $row){
            //$line=(array)$row;
            $row['begining']=$last;
            if (substr($no_account,0,1)=='1'){
                $row['last'] =floatval($row['begining'])+(floatval($row['debit'])-floatval($row['credit']));
            } else {
                $row['last'] =floatval($row['begining'])+(floatval($row['credit'])-floatval($row['debit']));
            }
            $row['last']=strval($row['last']);
            $last=$row['last'];
            $row['is_void']=strval($row['is_void']);
            $row['is_verified']=strval($row['is_verified']);
            $lines[]=$row;
          }
          $data['data']=$lines;
          return $data;
       } else {
          $data=$data->orderBy('a.ref_date','asc')
            ->orderBy('a.sysid','asc')
            ->get();
          $realdate = date_create($date1);
          $realdate->modify('-1 day');

          $data=$data->toArray();
          $rows=array();

          $lines=array();
          $begining=Accounting::begining_balance($project,$no_account,$date1);
          $line['sysid']=-1;
          $line['pool_code']='';
          $line['trans_code']='';
          $line['trans_series']='';
          $line['project']='';
          $line['reference1']='';
          $line['reference2']='';
          $line['line_memo']='Saldo Awal s.d '.date_format($realdate, 'd-m-Y');
          $line['ref_date']=date_format($realdate, 'Y-m-d');
          $line['begining']=0;
          $line['debit']=0;
          $line['credit']=0;
          $line['last'] =$begining;
          $line['is_void'] ='0';
          $line['is_verified'] ='1';
          $line['verified_date'] =null;
          $lines[]=$line;
          $last=floatval($line['last']);
          foreach($data as $line){
            $row=(array)$line;
            $row['begining']=$last;
            if (substr($no_account,0,1)=='1'){
                $row['last'] =floatval($row['begining'])+(floatval($row['debit'])-floatval($row['credit']));
            } else {
                $row['last'] =floatval($row['begining'])+(floatval($row['credit'])-floatval($row['debit']));
            }
            $row['last']=strval($row['last']);
            $last=$row['last'];
            $row['is_void']=strval($row['is_void']);
            $row['is_verified']=strval($row['is_verified']);
            $lines[]=$row;
          }
          return $lines;
       }
    }
    public static function Mutation($month,$year,$model,$project='00'){
        $debit='debit_month'.strval($month);
        $credit='credit_month'.strval($month);
        DB::beginTransaction();
        DB::update("UPDATE m_account set reversed1=0,reversed2=0,reversed3=0,reversed4=0");
        if ($project=='00'){
          DB::update("UPDATE m_account a INNER JOIN
              (SELECT no_account,SUM($debit) AS debit,SUM($credit) AS credit FROM t_account_mutation
              WHERE fiscal_year=$year GROUP BY no_account) b ON a.account_no=b.no_account
              SET a.reversed2=b.debit,a.reversed3=b.credit",[$year]);
        } else {
          DB::update("UPDATE m_account a INNER JOIN
              (SELECT no_account,SUM($debit) AS debit,SUM($credit) AS credit FROM t_account_mutation
              WHERE fiscal_year=? AND project=? GROUP BY no_account) b ON a.account_no=b.no_account
              SET a.reversed2=b.debit,a.reversed3=b.credit",[$year,$project]);
        }
        DB::update("UPDATE m_account SET reversed4=reversed1+(reversed2-reversed3) WHERE enum_drcr='D'");
        DB::update("UPDATE m_account SET reversed4=reversed1+(reversed3-reversed2) WHERE enum_drcr='C'");
        DB::commit();
        if ($model=='neraca'){
          $lines=Account::selectRaw('account_no,account_name,reversed1,reversed2,reversed3,reversed4,level_account,account_header,is_header')->where('account_group','<=','3')->get();
        } else {
          $lines=Account::selectRaw('account_no,account_name,reversed1,reversed2,reversed3,reversed4,level_account,account_header,is_header')->where('account_group','>','3')->get();
        }
        $lines->makeVisible(['reversed1','reversed2','reversed3','reversed4']);
        return $lines;
    }

    public static function create_customer_account($sysid,$source,$noaccount='-'){
      $acc=DB::table('t_customer_account')
        ->where('ref_sysid',$sysid)
        ->where('doc_source',$source)
        ->first();
      DB::delete("DELETE FROM t_customer_card WHERE ref_sysid=? AND doc_source=?",[$sysid,$source]);
      $isExists=($acc);
      $negatif=false;
      $uuid=Str::uuid()->toString();
      if ($source=="LPB"){
        if (!($acc)) {
          DB::insert("INSERT INTO t_customer_account(ref_sysid,doc_source,doc_number,reference,uuid_invoice,ref_date,partner_id,amount,doc_payment,no_account,source,uuid_code)
             SELECT sysid,?,doc_number,ref_document,uuid_code,ref_date,partner_code,total,'-',?,'AP',? FROM t_item_invoice1 WHERE sysid=?",[$source,$noaccount,$uuid,$sysid]);
        } else {
          DB::update("UPDATE t_customer_account a INNER JOIN t_item_invoice1 b ON a.ref_sysid=b.sysid AND a.doc_source=?
              SET a.amount=b.total,a.ref_date=b.ref_date,a.partner_id=b.partner_code,doc_payment='-',a.reference=b.ref_document,
              a.uuid_invoice=b.uuid_code,no_account=? WHERE a.ref_sysid=?",[$source,$noaccount,$sysid]);
        }
      }

      if ($source=="SPK"){
        if (!($acc)) {
          DB::insert("INSERT INTO t_customer_account(ref_sysid,doc_source,doc_number,reference,uuid_invoice,ref_date,partner_id,amount,doc_payment,no_account,source,uuid_code)
             SELECT sysid,?,doc_number,ref_document,uuid_code,ref_date,partner_code,total,'-',?,'AP',? FROM t_job_invoice1 WHERE sysid=?",[$source,$noaccount,$uuid,$sysid]);
        } else {
          DB::update("UPDATE t_customer_account a INNER JOIN t_job_invoice1 b ON a.ref_sysid=b.sysid AND a.doc_source=?
              SET a.amount=b.total,a.ref_date=b.ref_date,a.partner_id=b.partner_code,doc_payment='-',a.reference=b.ref_document,
              a.uuid_invoice=b.uuid_code,no_account=? WHERE a.ref_sysid=?",[$source,$noaccount,$sysid]);
        }
      }

      if ($negatif){
        DB::insert("INSERT INTO t_customer_card(ref_sysid,doc_source,doc_number,reference,ref_date,debit,credit)
                   SELECT ref_sysid,doc_source,doc_number,reference,ref_date,0,amount FROM t_customer_account WHERE
                   ref_sysid=? AND doc_source=?",[$sysid,$source]);
      } else {
        DB::insert("INSERT INTO t_customer_card(ref_sysid,doc_source,doc_number,reference,ref_date,debit,credit)
                   SELECT ref_sysid,doc_source,doc_number,reference,ref_date,amount,0 FROM t_customer_account WHERE
                   ref_sysid=? AND doc_source=?",[$sysid,$source]);
      }
    }

    public static function void_customer_account($sysid,$source,$noaccount='-'){
      DB::delete("DELETE FROM t_customer_card WHERE ref_sysid=? AND doc_source=?",[$sysid,$source]);
      DB::update("UPDATE t_customer_account SET is_void=1 WHERE ref_sysid=? AND doc_source=?",[$sysid,$source]);
    }

    public static function void_jurnal($sysid,$request=null){
        $ret['state']=true;
        $ret['message']='';
        $jurnal1=Journal1::where('sysid',$sysid)->first();
        $jurnal2=Journal2::where('sysid',$sysid)->orderBy('line_no','asc')->get();
        if ($jurnal1->is_void=='1') {
          $ret['state']=false;
          $ret['message']='Transaksi tersebut sudah divoid/dibatalkan';
        } else {
            $voucher=PagesHelp::GetVoucherseries($jurnal1->trans_code,$jurnal1->ref_date);
            $sysid_jurnal=Journal1::insertGetId([
              'ref_date'=>$jurnal1->ref_date,
              'pool_code'=>$jurnal1->pool_code,
              'reference1'=>$jurnal1->reference1,
              'reference2'=>$jurnal1->reference2,
              'posting_date'=>$jurnal1->posting_date,
              'is_posted'=>'1',
              'trans_code'=>$jurnal1->trans_code,
              'trans_series'=>$voucher,
              'fiscal_year'=>$jurnal1->fiscal_year,
              'fiscal_month'=>$jurnal1->fiscal_month,
              'transtype'=>$jurnal1->transtype,
              'notes'=>$jurnal1->notes
            ]);
            $line_no=0;
            foreach($jurnal2 as $line) {
              $line_no=$line_no+1;
              Journal2::insert([
                'sysid'=>$sysid_jurnal,
                'line_no'=>$line_no,
                'no_account'=>$line->no_account,
                'line_memo'=>$line->line_memo.'(VOID)',
                'reference1'=>$line->reference1,
                'reference2'=>$line->reference2,
                'debit'=>0 - $line->debit,
                'credit'=>0 -$line->credit,
                'project'=>$line->project
              ]);
            }
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                Journal1::where('sysid',$sysid)
                ->update([
                  'is_void'=>'1',
                  'sysid_void'=>$sysid_jurnal,
                  'ref_date_void'=>Date('Y-m-d')
                ]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        }
        return $ret;
    }
}
