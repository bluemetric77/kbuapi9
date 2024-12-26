<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workorder {{$header->doc_number}}</title>
	<link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 50px 30px 50px 40px;
		}

	</style>
</head>
<body>
<div style="font-size:14px;"><strong>{{$profile->name}}</strong></div>
<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>LAPORAN PERBAIKAN/SERVICE</strong></div>
<div class="information">
    <table style="padding:0px;font-size:12px"> 
		<tbody>
        <tr>
		  <td width="100px">No.Service</td>
		  <td>:</td>
		  <td width="300px" style="font-size:14px"><strong>{{$header->doc_number}}</strong></td>
		  <td>Supervisor</td>
		  <td>:</td>
		  <td>{{$header->service_advisor}}</td>
		</tr>
        <tr>
		  <td>Tanggal</td>
		  <td>:</td>
		  <td>{{$header->ref_date}} - {{$header->ref_time}}</td>
		  <td>Mekanik</td>
		  <td>:</td>
		  <td>{{$header->mechanic_name}}</td>
		</tr>
        <tr>
		  <td>Unit</td>
		  <td>:</td>
		  <td>{{$header->vehicle_no}} - {{$header->police_no}}</td>
		  <td>Tipe Perbaikan</td>
		  <td>:</td>
		  <td>{{$header->service_type}}</td>
		</tr>
        <tr>
		  <td>Pengemudi</td>
		  <td>:</td>
		  <td>{{$header->requester}}</td>
		  <td>KM Service</td>
		  <td>:</td>
		  <td>{{$header->odo_service}} KM, Berikutnya : {{$header->next_service}} KM</td>
		</tr>
		</tbody>
	</table>
</div>
<br/>
<div style="font-size:12px;padding-bottom:5px"><strong>KELUHAN/MASALAH KENDARAAN</strong></div>
<div style="font-size:12px">
{!! nl2br(e($header->problem)) !!}
</div>
@if (count($detail)>0) 
<div style="font-size:12px;padding-bottom:5px;padding-top:5px"><strong>PARAMETER PERBAIKAN PERIODIK</strong></div>
	<table class="table-detail" style="font-size:12px">
		<thead>
			<tr>
				<th width="50px" style="text-align:left">Kode</th>
				<th width="290px" style="text-align:left">Keterangan</th>
			</tr>
		</thead>		
		<tbody>
			@foreach($periodic as $line)
		    <tr  style="font-size:12px;text-align:left;vertical-align:top">
				<td>{{$line->service_code}}</td>
				<td>{{$line->descriptions}}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</div>
<div style="font-size:12px;padding-bottom:5px;padding-top:5px"><strong>CHECKLIST PEMERIKSAAN PERIODIK</strong></div>
    <table class="table-detail" width="100%">
		<thead>
        <tr style="font-size:11px">
				<th width="300px" style="text-align:left">Parameter Checklist</th>
				<th width="300px" style="text-align:left">Catatan</th>
			</tr>
		</thead>		
		<tbody>
		@php
		$group_name='';
		@endphp
		@foreach($detail as $row)
		@if (!($group_name==$row->group_name))
        <tr style="font-size:12px">
				<td colspan="2" style="background-color: #f0f0f0;"><strong>{{$row->group_line}}. {{$row->group_name}}</strong></td>
        </tr>
		@endif
		@php
		$group_name=$row->group_name;
		@endphp
        <tr style="font-size:10px">
				<td> 
					@if ($row->is_service=='1') 
						<div>
							<input type="checkbox" id="service1" name="service1" checked>
							<label for="service1">{{$row->item_line}}. {{$row->descriptions}}</label>
						</div>
					@else	
						<div>
							<input type="checkbox" id="service2" name="service2" unchecked>
							<label for="service2">{{$row->item_line}}. {{$row->descriptions}}</label>
						</div>
					@endif	
				</td>
				<td>{{$row->notes}}</td>
        </tr>
		@endforeach
		</tbody>
	</table>	
<br/>
@endif
<div style="font-size:12px;padding-bottom:5px"><strong>PEKERJAAN</strong></div>
	<table class="table-detail" style="font-size:10px">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="150px">Grup</th>
				<th width="200px">Pekerjaan</th>
				<th width="270px">Catatan</th>
			</tr>
		</thead>		
		<tbody>
			@foreach($jobs as $line)
		    <tr  style="font-size:10px;text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->descriptions}}</td>
				<td>{{$line->line_notes}}</td>
				<td>{{$line->line_notes}}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</div>
<br/>
<div class="invoice" style="margin-left:0px">
	<div style="font-size:12px;padding-bottom:5px"><strong>PEMAKAIAN SPAREPART</strong></div>
	<table class="table-detail" style="font-size:11px">
		<thead>
			<tr>
				<th width="80px">Kode</th>
				<th width="200px">Nama Barang/Sparepart</th>
				<th width="60px">Permintaan</th>
				<th width="60px">Diberikan</th>
				<th width="60px">Satuan</th>
				<th width="60px">Terpakai</th>
				<th width="60px">Sisa</th>
			</tr>
		</thead>		
		<tbody>
			@foreach($material as $line)
		    <tr  style="font-size:11px;text-align:left;vertical-align:top">
				<td>{{$line->item_code}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->request,2,',','.')}}</td>
				<td align="right">{{number_format($line->approved,2,',','.')}}</td>
				<td>{{$line->mou_inventory}}</td>
				<td align="right">{{number_format($line->used,2,',','.')}}</td>
				<td align="right">{{number_format($line->approved-$line->used,2,',','.')}}</td>
			</tr>
			@endforeach
		</tbody>
	</table>
</div>
<br/>
<div style="font-size:12px;padding-bottom:5px"><strong>CATATAN AKHIR SERVICE/PERBAIKAN</strong></div>
    <pre style="font-size: 10px;line-height: 1.6;">
{{$header->report}}
	</pre>
<br/>
Tanggal Selesai : <strong style="font-size:12px">{{$header->close_date}} {{$header->close_time}}</strong>
</div>
<br/>
<br/>
<br/>
<br/>
@php
$mekanik = $sign['mekanik'];
@endphp

<table class="table-detail" style="font-size:12px;line-height:0.8">
	<tr style="border:none;text-align:center;vertical-align:middle;padding:0px"> 
		<td width="150px">Mengetahui</td>
		<td width="150px">Mekanik</td>
		<td width="150px">Bengkel</td>
		<td width="120px">Tgl&Jam Selesai</td>
		<td width="110px">KM Service Selanjutnya</td>
	</tr>
	<tr style="border:none;text-align:center;vertical-align:bottom;padding-top:20px"> 
		<td height="60px">{{$header->requester}}</td>
		<td height="60px">
			<img src="{{$mekanik}}" width="100px" height="100px"><br/> 
			{{$header->mechanic_name}}</td>
		<td height="60px">{{$header->service_advisor}}</td>
		<td height="60px"></td>
		<td height="60px"></td>
	</tr>
</table>

<div class="information" style="position: absolute; bottom: -30;">
    <table width="100%">
        <tr style="font-size:10px">
            <td align="right" style="width:50%;">
                {{$profile->name}}
            </td>
        </tr>
    </table>
</div>
</body>
</html>