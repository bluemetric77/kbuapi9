<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Models\General\Documents;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    /**
     * Update the avatar for the user.
     *
     * @param  Request  $request
     * @return Response
     */
    public function upload(Request $request)
    {
       // tampung berkas yang sudah diunggah ke variabel aru
        // 'file' merupakan nama input yang ada pada form
        $doctype   = isset($request->doc_type) ? $request->doc_type : '';
        $docnumber = isset($request->doc_number) ? $request->doc_number :'';
        $title     = isset($request->title) ? $request->title :'-';
        $notes     = isset($request->notes) ? $request->notes :'-';
        $folder    = isset($request->folder) ? $request->folder :'general';
        /*$validator=Validator::make($request,[
            'doc_type'=>'bail|required',
            'doc_number'=>'bail|required',
            'title'=>'bail|required',
        ],[
            'doc_type.required'=>'Type dokumen diisi',
            'doc_number.required'=>'Link dokumen diisi',
            'title.required'=>'Judul harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }*/

        $uploadedFile = $request->file('file');
        $originalFile = $uploadedFile->getClientOriginalName();
        $originalFile = Date('Ymd-His')."-".$originalFile;
        $extension    = $uploadedFile->getClientOriginalExtension();
        // simpan berkas yang diunggah ke sub-direktori 'public/files'
        // direktori 'files' otomatis akan dibuat jika belum ada
        $directory="public/".$folder;
        $path = $uploadedFile->storeAs($directory,$originalFile);
        Documents::insert([
            'doc_type'=>$doctype,
            'doc_number'=>$docnumber,
            'path_file'=>$path,
            'title'=>$title,
            'notes'=>$notes,
            'file_name'=>$uploadedFile->getClientOriginalName(),
            'file_type'=>$extension,
            'upload_date'=>date('Y-m-d H:i:s'),
            'update_timestamp'=>date('Y-m-d H:i:s'),
        ]);
        return response()->success('success','Upload berhasil');
    }

    public function download(Request $request)
    {
       // tampung berkas yang sudah diunggah ke variabel aru
        // 'file' merupakan nama input yang ada pada form
        $sysid = isset($request->sysid) ? $request->sysid : -1;
        $doc_type = isset($request->doc_type) ? $request->doc_type :'';
        $doc_number = isset($request->doc_number) ? $request->doc_number :'';
        $data=Documents::selectRaw("path_file,file_name,file_type")
            ->where('sysid',$sysid)
            ->orWhere(function($query) use ($doc_type,$doc_number) {
                $query->where('doc_type', $doc_type)
                    ->where('doc_number', $doc_number);
            })->first();
        if ($data) {
            $file=$data->path_file;
            $publicPath = \Storage::url($file);
            $backfile =$data->file_name;
            $headers = array('Content-Type: application/'.$data->file_type);
            return Storage::download($file, $backfile,$headers);
        } else {
              return response()->error('',301,'Dokumen tidak ditemukan');
        }
    }
    public function delete(Request $request)
    {
       // tampung berkas yang sudah diunggah ke variabel aru
        // 'file' merupakan nama input yang ada pada form
        $sysid = isset($request->sysid) ? $request->sysid : -1;
        Documents::where('sysid',$sysid)
            ->update(['is_deleted'=>'1']);
        return response()->success('Success','Hapus data berhasil');
    }
}
