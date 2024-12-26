<?php

namespace App\Http\Controllers\Finance;

use App\Models\Ops\Operation;
use App\Models\Finance\OpsChashierOthers;
use App\Models\Finance\OpsCashier;
use App\Models\Ops\OperationUnpaid;
use App\Models\Finance\OtherItems;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SPJOthersController extends Controller
{
    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= OpsChashierOthers::from('t_operation_cashier_others as a')
        ->selectRaw("a.sysid as _index,b.doc_number,a.ref_date,b.doc_operation,c.ref_date AS ops_date,c.vehicle_no,c.route_id,d.route_name,b.target2,
                    a.*,c.conductor_id,e.personal_name AS conductor_name,b.pool_code,b.update_userid,b.update_timestamp")
        ->join('t_operation_cashier as b','a.sysid','=','b.sysid')
        ->leftJoin('t_operation as c','b.sysid_operation','=','c.sysid')
        ->leftJoin('m_bus_route as d','c.route_id','=','d.sysid')
        ->leftJoin('m_personal as e','c.conductor_id','=','e.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('b.is_canceled','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('b.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('b.doc_number', 'like', $filter)
                    ->orwhere('b.doc_operation', 'like', $filter)
                    ->orwhere('c.vehicle_no', 'like', $filter)
                    ->orwhere('c.police_no', 'like', $filter)
                    ->orwhere('c.pool_code', 'like', $filter);
            });
        }
        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
    public function report(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= OpsChashierOthers::from('t_operation_cashier_others as a')
        ->selectRaw("a.sysid as _index,b.doc_number,a.ref_date,b.doc_operation,c.ref_date AS ops_date,c.vehicle_no,c.route_id,d.route_name,
                    a.*,c.conductor_id,e.personal_name AS conductor_name,b.pool_code,b.update_userid,b.update_timestamp,b.target2")
        ->join('t_operation_cashier as b','a.sysid','=','b.sysid')
        ->leftJoin('t_operation as c','b.sysid_operation','=','c.sysid')
        ->leftJoin('m_bus_route as d','c.route_id','=','d.sysid')
        ->leftJoin('m_personal as e','c.conductor_id','=','e.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('b.is_canceled','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('b.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN PENERIMAAN LAIN2');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'No.Bukti');
        $sheet->setCellValue('B5', 'Tgl.Setor');
        $sheet->setCellValue('C5', 'No.SPJ');
        $sheet->setCellValue('D5', 'Tgl.SPJ');
        $sheet->setCellValue('E5', 'No. Seri');
        $sheet->setCellValue('F5', 'Trayek');
        $sheet->setCellValue('G5', 'NIK Kondektur');
        $sheet->setCellValue('H5', 'Kondektur');
        $sheet->setCellValue('I5', 'Target');
        $sheet->setCellValue('J5', 'ATK');
        $sheet->setCellValue('K5', 'Sumbangan');
        $sheet->setCellValue('L5', 'Perawatan');
        $sheet->setCellValue('M5', 'Dana Kecelakaan');
        $sheet->setCellValue('N5', 'Biaya Kesehatan');
        $sheet->setCellValue('O5', 'Koperasi');
        $sheet->setCellValue('P5', 'Kebersihan');
        $sheet->setCellValue('Q5', 'NIk DB/TR');
        $sheet->setCellValue('R5', 'FA');
        $sheet->setCellValue('S5', 'Lain2');
        $sheet->setCellValue('T5', 'Target Point 3');
        $sheet->setCellValue('U5', 'AQUA');
        $sheet->setCellValue('V5', 'KWA');
        $sheet->setCellValue('W5', 'Pool');
        $sheet->setCellValue('X5', 'User Input');
        $sheet->setCellValue('Y5', 'Tgl.Input');
        $sheet->getStyle('A5:Y5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->doc_operation);
            $sheet->setCellValue('D'.$idx, $row->ops_date);
            $sheet->setCellValue('E'.$idx, $row->vehicle_no);
            $sheet->setCellValue('F'.$idx, $row->route_name);
            $sheet->setCellValue('G'.$idx, $row->conductor_id);
            $sheet->setCellValue('H'.$idx, $row->conductor_name);
            $sheet->setCellValue('I'.$idx, $row->target2);
            $sheet->setCellValue('J'.$idx, 0);
            $sheet->setCellValue('K'.$idx, $row->o_002);
            $sheet->setCellValue('L'.$idx, $row->o_003);
            $sheet->setCellValue('M'.$idx, $row->o_004);
            $sheet->setCellValue('N'.$idx, $row->o_005);
            $sheet->setCellValue('O'.$idx, $row->o_006);
            $sheet->setCellValue('P'.$idx, 0);
            $sheet->setCellValue('Q'.$idx, 0);
            $sheet->setCellValue('R'.$idx, $row->o_009);
            $sheet->setCellValue('S'.$idx, $row->o_010);
            $sheet->setCellValue('T'.$idx, $row->o_011);
            $sheet->setCellValue('U'.$idx, $row->o_012);
            $sheet->setCellValue('V'.$idx, $row->o_013);
            $sheet->setCellValue('W'.$idx, $row->pool_code);
            $sheet->setCellValue('X'.$idx, $row->update_userid);
            $sheet->setCellValue('Y'.$idx, $row->update_timestamp);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D6:D'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('Y6:Y'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->setCellValue('J'.$idx, "=SUM(J6:J$last)");
        $sheet->setCellValue('K'.$idx, "=SUM(K6:K$last)");
        $sheet->setCellValue('L'.$idx, "=SUM(L6:L$last)");
        $sheet->setCellValue('M'.$idx, "=SUM(M6:M$last)");
        $sheet->setCellValue('N'.$idx, "=SUM(N6:N$last)");
        $sheet->setCellValue('O'.$idx, "=SUM(O6:O$last)");
        $sheet->setCellValue('P'.$idx, "=SUM(P6:P$last)");
        $sheet->setCellValue('Q'.$idx, "=SUM(Q6:Q$last)");
        $sheet->setCellValue('R'.$idx, "=SUM(R6:R$last)");
        $sheet->setCellValue('S'.$idx, "=SUM(S6:S$last)");
        $sheet->setCellValue('T'.$idx, "=SUM(T6:T$last)");
        $sheet->setCellValue('U'.$idx, "=SUM(T6:T$last)");
        $sheet->setCellValue('V'.$idx, "=SUM(T6:T$last)");
        $sheet->getStyle('I6:V'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:Y5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'Y'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:Y'.$idx)->applyFromArray($styleArray);
        foreach(range('C','Y') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_kasir_lain2_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
