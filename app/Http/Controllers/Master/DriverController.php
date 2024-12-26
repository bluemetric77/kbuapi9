<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Driver;
use App\Models\General\Documents;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DriverController extends Controller
{
    public function show(Request $request){
        $filter = isset($request->filter) ? $request->filter :'';
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy = $request->sortBy;
        $pool_code=PagesHelp::PoolCode($request);
        $type=isset($request->type) ? $request->type : 'ALL';

        $data= Driver::select('personal_id','employee_id','personal_name','dob','personal_address',
                'phone1','phone2','citizen_no','marital_state','driving_license_type','driving_license_no',
                'driving_license_valid','is_active','personal_type','emergency_contact','emergency_address',
                'emergency_phone','bank_name','account_number','account_name','pool_code','crew_group','photo');
        //->where('pool_code',$pool_code);
        if ($type=='DRIVER'){
            $data=$data->where('crew_group','Pengemudi');
        } else if ($type=='HELPER'){
            $data=$data->where('crew_group','Kernet');
        } else if ($type=='CONDUCTOR'){
            $data=$data->where('crew_group','Kondektur');
        } else if ($type=='MECHANIC'){
            $data=$data->where('crew_group','Mekanik');
        }
        //$data=$data->where('is_crew','1');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('employee_id','like',$filter)
               ->orwhere('personal_name','like',$filter)
               ->orwhere('personal_address','like',$filter)
               ->orwhere('phone1','like',$filter)
               ->orwhere('citizen_no','like',$filter)
               ->orwhere('driving_license_no','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        $data=$data->toArray();
        $rows=array();
        $server=PagesHelp::my_server_url();
        foreach($data['data'] as $row){
            $row['photo']=$server.'/'.$row['photo'];
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $personal_id=$request->id;
        $data=Driver::find($personal_id);
        if (!($data==null)) {
            DB::beginTransaction();
            try{
                $data->delete();
                PagesHelp::write_log($request,$data->personal_id,$data->employee_id,'Delete recods ['.$data->employee_id.'-'.$data->personal_name.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch(Exception $e) {
              DB::rollback();
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $personal_id=$request->id;
        $data['document']=[];
        $data['header']=Driver::where('personal_id',$personal_id)->first();
        if ($data['header']) {
            $data['document']=Documents::where('doc_number',$data['header']->employee_id)
            ->where('doc_type','DRV')
            ->where('is_deleted','0')
            ->get();
        }
        return response()->success('Success',$data);
    }
    public function document(Request $request){
        $id=$request->employee_id;
        $data=Documents::where('doc_number',$id)
        ->where('doc_type','DRV')
        ->where('is_deleted','0')
        ->get();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        unset($rec['photo']);
        unset($rec['sign']);
        $validator=Validator::make($rec,[
            'employee_id'=>'bail|required',
            'personal_name'=>'bail|required',
            'crew_group'=>'bail|required',
            'personal_address'=>'bail|required',
            'phone1'=>'bail|required',
            'citizen_no'=>'bail|required',
            'personal_type'=>'bail|required',
            'pool_code'=>'bail|required'
        ],[
            'employee_id.required'=>'ID personal harus diisi',
            'personal_name.required'=>'Nama harus diisi',
            'crew_group.required'=>'Jenis personal harus diisi',
            'personal_address.required'=>'Alamat harus diisi',
            'phone1.required'=>'No. Telepon 1 harus diisi',
            'citizen_no.required'=>'No. KTP harus diisi',
            'personal_type.required'=>'Grup personal/krew harus diisi',
            'pool_code.required'=>'Kode pool harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $rec['crew_group']=$rec['personal_type'];
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Driver::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['personal_id']);
                Driver::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['employee_id'],'Add/Update record ['.$rec['employee_id'].'-'.$rec['personal_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function change_pool(Request $request){
        $data= $request->json()->all();
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'personal_id'=>'bail|required',
            'pool_code'=>'bail|required'
        ],[
            'personal_id.required'=>'ID personal harus diisi',
            'personal_name.required'=>'Nama harus diisi',
            'pool_code.required'=>'Pool tujuan harus diisi harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if ($rec['pool_code']=='-'){
            return response()->error('',501,'Pool tujuan belum dipilih');
        }
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        try{
            Driver::where($where)
                ->update(['pool_code'=>$rec['pool_code']]);
            return response()->success('Success','Perpindahan ke pool tujuan berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }

    public function getDriver(Request $request){
        $pool_code = PagesHelp::PoolCode($request);
        $personal_type = isset($request->type) ? $request->type :'';
        $onduty = isset($request->onduty) ? $request->onduty : 0;
        $employeeid = isset($request->employeeid) ? $request->employeeid :-1;
        $all = isset($request->all) ? $request->all : 0;
        $filter = isset($request->filter) ? $request->filter : '';
        if ($all==1){
            $data=Driver::selectRaw("employee_id,CONCAT(personal_name, ' - ', employee_id) AS personal_name,IFNULL(photo,'') as photo")
                ->where('is_active','1')
                ->where('pool_code',$pool_code)
                ->orderBy('personal_name')
                ->get();
        } else if (!($filter=='')) {
            $filter='%'.trim($filter).'%';
            $data=Driver::selectRaw("employee_id,CONCAT(personal_name, ' - ', employee_id) AS personal_name,IFNULL(photo,'') as photo")
                ->where('is_active','1')
                ->where('pool_code',$pool_code)
                ->where('personal_name','like',$filter)
                ->orderBy('personal_name')
                ->get();
        } else if ($onduty==0){
            $data=Driver::selectRaw("employee_id,CONCAT(personal_name, ' - ', employee_id) AS personal_name,IFNULL(photo,'') as photo")
                ->where('is_active','1')
                ->where('pool_code',$pool_code)
                ->where('personal_type','=',$personal_type)
                ->orderBy('personal_name')
                ->get();
        } else {
            $data=Driver::selectRaw("employee_id,CONCAT(personal_name, ' - ', employee_id) AS personal_name,IFNULL(photo,'') as photo")
                ->where('is_active','1')
                ->where('pool_code',$pool_code)
                ->where(function($q) use ($employeeid) {
                    $q->where('on_duty','=',0)
                      ->orwhere('employee_id','=',$employeeid);
                });
                if ($personal_type=='Pengemudi') {
                    $data=$data->where('personal_type','=',$personal_type)
                    ->orderBy('personal_name')
                    ->get();
                } else if ($personal_type=='Mekanik') {
                    $data=$data->where('personal_type','=',$personal_type)
                    ->orderBy('personal_name')
                    ->get();
                } else {
                    $data=$data->where(function($q) use ($employeeid) {
                        $q->where('personal_type','=','Kernet')
                            ->orwhere('personal_type','Kondektur');
                    })
                    ->orderBy('personal_name')
                    ->get();
                }
        }
        $rows=array();
        $server=PagesHelp::my_server_url();
        foreach($data as $row){
            if (!($row['photo']=='')) {
                $row['photo']=$server.'/'.$row['photo'];
            }
            $rows[]=$row;
        }
        return response()->success('Success',$rows);
    }

    public function uploadfoto(Request $request)
    {
        $personal_id  = isset($request->personal_id) ? $request->personal_id : '';
        $personal_id  = strval($personal_id);
        $uploadedFile = $request->file('file');
        $originalFile = $uploadedFile->getClientOriginalName();
        $originalFile = Date('Ymd-His')."-".$originalFile;
        $directory="public/photo";
        $path = $uploadedFile->storeAs($directory,$originalFile);
        Driver::where('personal_id',$personal_id)
        ->update(['photo'=>$path]);
        $respon['path_file']=$path;
        $respon['message']='Upload foto berhasil';
        return response()->success('success',$respon);
    }

    public function downloadfoto(Request $request)
    {
        $personal_id   = isset($request->personal_id) ? $request->personal_id : '-1';
        $data=Driver::selectRaw("IFNULL(photo,'') as photo")
            ->where('personal_id',$personal_id)
            ->first();
        if ($data) {
            $file=$data->photo;
            $publicPath = Storage::url($file);
            $backfile =$data->file_name;
            $headers = array('Content-Type: application/jpeg');
            return Storage::download($file, $backfile,$headers);
        } else {
            return null;
        }
    }
    public function deletefoto(Request $request)
    {
        $personalid   = isset($request->personalid) ? $request->personalid : '';
        Driver::where('personal_id',$personal_id)
            ->update(['photo'=>'-']);
        return response()->success('Success','Hapus data berhasil');
    }

    public function uploadsign(Request $request)
    {
        $personal_id  = isset($request->personal_id) ? $request->personal_id : '';
        $personal_id  = strval($personal_id);
        $uploadedFile = $request->file('file');
        $originalFile = $uploadedFile->getClientOriginalName();
        $originalFile = Date('Ymd-His')."-".$originalFile;
        $directory="public/sign";
        $path = $uploadedFile->storeAs($directory,$originalFile);
        Driver::where('personal_id',$personal_id)
        ->update(['sign'=>$path]);
        $respon['path_file']=$path;
        $respon['message']='Upload foto berhasil';
        return response()->success('success',$respon);
    }

    public function downloadsign(Request $request)
    {
        $personal_id   = isset($request->personal_id) ? $request->personal_id : '-1';
        $data=Driver::selectRaw("IFNULL(sign,'') as sign")
            ->where('personal_id',$personal_id)
            ->first();
        if ($data) {
            $file=$data->sign;
            $publicPath = Storage::url($file);
            $backfile =$data->file_name;
            $headers = array('Content-Type: application/jpeg');
            return Storage::download($file, $backfile,$headers);
        } else {
            return null;
        }
    }
    public function deletesign(Request $request)
    {
        $personalid   = isset($request->personalid) ? $request->personalid : '';
        Driver::where('personal_id',$personal_id)
            ->update(['sign'=>'-']);
        return response()->success('Success','Hapus data berhasil');
    }
    public function download(Request $request)
    {
        $pool_code=PagesHelp::PoolCode($request);
        $type=isset($request->type) ? $request->type : 'ALL';
        $data= Driver::select('personal_id','employee_id','personal_name','dob','personal_address',
                'phone1','phone2','citizen_no','marital_state','driving_license_type','driving_license_no',
                'driving_license_valid','is_active','personal_type','emergency_contact','emergency_address',
                'emergency_phone','bank_name','account_number','account_name','pool_code','crew_group','photo','is_active');
        if ($type=='DRIVER'){
            $data=$data->where('crew_group','Pengemudi');
        } else if ($type=='HELPER'){
            $data=$data->where('crew_group','Kernet');
        } else if ($type=='CONDUCTOR'){
            $data=$data->where('crew_group','Kondektur');
        } else if ($type=='MECHANIC'){
            $data=$data->where('crew_group','Mekanik');
        }
        $data=$data->orderBy('employee_id','asc')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'MASTER PENGEMUDI/KERNET/KONDEKTUR/MEKANIK');
        $sheet->setCellValue('A3', 'ID Pengemudi');
        $sheet->setCellValue('B3', 'Nama Pengemudi');
        $sheet->setCellValue('C3', 'Tgl.Lahir');
        $sheet->setCellValue('D3', 'Alamat');
        $sheet->setCellValue('E3', 'Telepon 1');
        $sheet->setCellValue('F3', 'Telepon 2');
        $sheet->setCellValue('G3', 'No.KTP');
        $sheet->setCellValue('H3', 'Tipe SIM');
        $sheet->setCellValue('I3', 'No.SIM');
        $sheet->setCellValue('J3', 'Berlaku SIM');
        $sheet->setCellValue('K3', 'Kontak Darurat');
        $sheet->setCellValue('L3', 'Alamat Kontak Darurat');
        $sheet->setCellValue('M3', 'Telp Darurat');
        $sheet->setCellValue('N3', 'Pool');
        $sheet->setCellValue('O3', 'Aktif');
        $sheet->getStyle('A3:O3')->getAlignment()->setHorizontal('center');

        $idx=3;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->employee_id);
            $sheet->setCellValue('B'.$idx, $row->personal_name);
            $sheet->setCellValue('C'.$idx, $row->dob);
            $sheet->setCellValue('D'.$idx, $row->personal_address);
            $sheet->setCellValue('E'.$idx, $row->phone1);
            $sheet->setCellValue('F'.$idx, $row->phone2);
            $sheet->setCellValue('G'.$idx, $row->citizen_no);
            $sheet->setCellValue('H'.$idx, $row->driving_license_type);
            $sheet->setCellValue('I'.$idx, $row->driving_license_no);
            $sheet->setCellValue('J'.$idx, $row->driving_license_valid);
            $sheet->setCellValue('K'.$idx, $row->emergency_contact);
            $sheet->setCellValue('L'.$idx, $row->emergency_address);
            $sheet->setCellValue('M'.$idx, $row->emergency_phone);
            $sheet->setCellValue('N'.$idx, $row->pool_code);
            $sheet->setCellValue('O'.$idx, ($row->is_active=='1') ? 'Ya':'Tidak');
        }

        $sheet->getStyle('C3:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $idx=$idx+1;
        // Formater
        $sheet->getStyle('A1:O3')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'S'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A3:O'.$idx)->applyFromArray($styleArray);
        foreach(range('C','O') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="master_karyawan.xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
    public function open(Request $request){
        $personal_type = isset($request->type) ? $request->type :'';
        $filter = isset($request->filter) ? $request->filter :'';
        $limit = $request->limit;
        $sortBy = $request->sortBy;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';

        $pool_code = PagesHelp::PoolCode($request);
        $data=Driver::selectRaw("personal_id,employee_id,personal_name,IFNULL(photo,'') as photo,phone1,is_active")
        ->where('pool_code',$pool_code)
        ->where('is_active','1')
        ->where('on_duty','=','0');
        if ($personal_type=='Pengemudi') {
            $data=$data->where('personal_type','=',$personal_type);
        } else if ($personal_type=='Mekanik') {
            $data=$data->where('personal_type','=',$personal_type);
        } else {
            $data=$data->where(function($q) {
                $q->where('personal_type','=','Kernet')
                    ->orwhere('personal_type','Kondektur');
            });
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function ($q) use ($filter) {
               $q->where('employee_id','like',$filter)
               ->orwhere('personal_name','like',$filter)
               ->orwhere('personal_address','like',$filter)
               ->orwhere('phone1','like',$filter)
               ->orwhere('citizen_no','like',$filter)
               ->orwhere('driving_license_no','like',$filter);
            });
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        $data=$data->toArray();
        $rows=array();
        $server=PagesHelp::my_server_url();
        foreach($data['data'] as $row){
            $row['photo']=$server.'/'.$row['photo'];
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success',$data);    }

}
