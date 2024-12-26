<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workorder {{$header->doc_number}}</title>
	<link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 30px 100px 10px 30px;
		}

	</style>
</head>
<body>
<div style="font-size:14px;"><strong>{{$profile->name}}</strong></div>
<div style="font-size:20px;text-align:center;margin-bottom:10px"><strong>WORK ORDER</strong></div>
	<table width="100%">
		<tr>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<table style="padding:0px;font-size:12px"> 
					<tbody>
					<tr>
						<td width="100px">No.Service</td>
						<td>:</td>
						<td width="160px" style="font-size:14px"><strong>{{$header->doc_number}}</strong></td>
					<tr>
						<td>Tanggal</td>
						<td>:</td>
						<td>{{$header->ref_date}} - {{$header->ref_time}}</td>
					</tr>
					<tr>
						<td>Seri Kendaraan</td>
						<td>:</td>
						<td>{{$header->vehicle_no}} - {{$header->police_no}}</td>
					</tr>
					<tr>
						<td>No Mesin</td>
						<td>:</td>
						<td>{{$header->vin}}</td>
					</tr>
					<tr>
						<td>No Chasis</td>
						<td>:</td>
						<td>{{$header->chasis_no}}</td>
					</tr>
					<tr>
						<td>KM Service</td>
						<td>:</td>
						<td>{{$header->odo_service}}</td>
					</tr>
					<tr>
						<td>Supervisor</td>
						<td>:</td>
						<td>{{$header->service_advisor}}</td>
					</tr>
					<tr>
						<td>Mekanik</td>
						<td>:</td>
						<td>{{$header->mechanic_name}}</td>
					</tr>
					<tr>
						<td>Perbaikan</td>
						<td>:</td>
						<td></td>
					</tr>
					<tr>
						<td>Pengemudi</td>
						<td>:</td>
						<td>{{$header->requester}}</td>
					</tr>
					</tbody>
				</table>
			</td>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<img src="/var/www/kbu.com/public_html/backend/public/wo.jpeg" width="450px" style="float:right"> 
			</td>
		</tr>
	</table>
<br/>
<div style="font-size:12px;padding-bottom:5px"><strong>KELUHAN/MASALAH KENDARAAN</strong></div>
<div style="font-size:12px">
{!! nl2br(e($header->problem)) !!}
</div>
<br/>
<div style="font-size:12px;padding-bottom:5px"><strong>PEKERJAAN</strong></div>
	<table class="table-detail" style="font-size:10px">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="150px">Grup</th>
				<th width="200px">Pekerjaan</th>
				<th width="320px">Catatan</th>
			</tr>
		</thead>		
	</table>
</div>
<div style="font-size:12px;padding-bottom:5px;margin-top:3cm"><strong>PEMAKAIAN SPAREPART</strong></div>
	<table class="table-detail" style="font-size:11px;">
		<thead>
			<tr>
				<th width="80px">Kode Item</th>
				<th width="150px">No.Part</th>
				<th width="200px">Nama Barang/Sparepart</th>
				<th width="60px">Qty Order</th>
				<th width="60px">Qty Keluar</th>
				<th width="120px">Catatan</th>
			</tr>
		</thead>		
	</table>
</div>
<div style="font-size:12px;padding-bottom:5px;margin-top:4.5cm"><strong>PEKERJAAN EKSTERNAL</strong></div>
	<table class="table-detail" style="font-size:10px">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="150px">Grup</th>
				<th width="200px">Pekerjaan</th>
				<th width="320px">Catatan</th>
			</tr>
		</thead>		
	</table>
	<table class="table-detail" style="font-size:11px;margin-top:1.5cm;margin-bottom:3cm">
		<thead>
			<tr>
				<th width="80px">Kode Item</th>
				<th width="150px">No.Part</th>
				<th width="200px">Nama Barang/Sparepart</th>
				<th width="60px">Qty</th>
				<th width="190px">Catatan</th>
			</tr>
		</thead>		
	</table>
</div>
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
</body>
</html>