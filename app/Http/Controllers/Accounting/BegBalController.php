<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Accounting;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use App\Models\Accounting\AccountMutation;
use App\Models\Master\Account;
use Illuminate\Support\Facades\DB;

class BegBalController extends Controller
{
    public function show(Request $request)
    {
        $year=isset($request->year) ? $request->year : '1899';

        DB::beginTransaction();
        try{
            Account::whereIn('account_group',[1,2,3])
            ->update(
                ['reversed1'=>0,
                'reversed2'=>0,
                'reversed3'=>0,
                'reversed4'=>0,
                'reversed5'=>0,
                'reversed6'=>0
                ]
            );

            DB::update(
                "UPDATE m_account a
                 INNER JOIN t_account_mutation b ON a.account_no=b.no_account
                 SET a.reversed2=b.begining_balance
                 WHERE a.account_group IN (1,2,3) AND b.fiscal_year=?",
                 [$year]
            );

            $data=Account::selectRaw("account_no,account_name,account_header,is_header,enum_drcr,reversed1,reversed2")
            ->whereIn('account_group',[1,2,3])
            ->orderBy('account_no','asc')
            ->get();
            DB::commit();
            return response()->success('Success', $data);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function store(Request $request)
    {
        $data=$request->json()->all();
        $rows =$data['data'];
        $year =$data['year'];
        DB::beginTransaction();
        try{
            DB::table('t_account_mutation')
            ->where('fiscal_year',$year)
            ->update([
                'begining_balance'=>0
            ]);
            foreach($rows as $line) {
               if ($line['is_header']=='0') {
                    $am= AccountMutation::where('fiscal_year',$year)
                    ->where('no_account',$line['account_no'])
                    ->where('project','00')
                    ->first();
                    if (!$am) {
                        $am = new AccountMutation();
                        $am->fiscal_year= $year;
                        $am->no_account = $line['account_no'];
                        $am->project    = '00';
                    }
                    $am->begining_balance = floatval($line['reversed2']);
                    $am->save();

               }
            }
            DB::commit();
            return response()->success('Success', 'Setup saldo awal berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
}
