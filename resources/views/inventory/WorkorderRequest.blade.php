<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Permintaan Barang Service {{$header[0]->doc_number ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 0.5cm 0.5cm 0.5cm;
		}
		.page-break {
			page-break-before: always;
		}
        .header {
           font-size:14px;
           text-align:center;
           margin-bottom:14px;
           font-weight:bold;
        }
        .tb-header {
            width:100%;
            font-size:12px;
        }
        .tb-header tr {
            text-align:left;
            vertical-align:top;
            padding:0px;
        }
        .tb-header tr td {
        }
        .tb-row {
            width:100%;
            font-size:12px;
            border: 0.5px solid black;
            border-collapse: collapse;
        }
        .tb-row td,
        tr,
        th {
            padding: 5px;
            border: .5px solid black;
        }
        .tb-row th {
            background-color: #f0f0f0;
        }
        .header-title {
            font-weight:bold;
            background-color: black;
            color: white;
            padding:5px 2px;
        }
	</style>
</head>
<body>
<img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
<div class="header"><strong>PERMINTAAN BARANG SERVICE</strong></div>
<div class="information">
    <table class="tb-header">
        <tr>
            <td width="80px">No.Dokumen</td>
            <td width="320px">: <strong>{{$header[0]->doc_number}}</strong></td>
            <td width="100px">Kendaraan</td>
            <td width="200px">: {{$header[0]->vehicle_no}} {{$header[0]->police_no}}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{$header[0]->ref_date}}</td>
            <td>Pool</td>
            <td>: {{$header[0]->pool_code}} {{$header[0]->pool_name}}</td>
        </tr>
        <tr>
            <td>No.Service</td>
            <td>: {{$header[0]->service_no}}</td>
            <td></td>
            <td></td>
        </tr>
	</table>
</div>
<br/>
<div class="invoice">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="75px">Kode</th>
				<th width="90px">No.Part</th>
				<th width="250px">Nama Barang/Sparepart</th>
				<th width="60px">Permintaan</th>
				<th width="80px">Satuan</th>
				<th width="60px">Diberikan</th>
			</tr>
		</thead>
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($header as $line)
		    <tr  style="text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->item_code}}</td>
				<td>{{$line->part_number}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->qty_item,0,',','.')}}</td>
				<td>{{$line->mou_inventory}}</td>
				<td align="right">{{number_format($line->qty_supply,0,',','.')}}</td>
			</tr>
			@php
			$amount = $amount + $line->amount;
			@endphp
			@endforeach
		</tbody>
	</table>
    <br/>
	<table class="tb-row">
		<tr style="text-align:center;vertical-align:center">
			<td width="200px">Dibuat oleh</td>
			<td width="200px">Diterima oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="80px">{{$header[0]->user_name}}</td>
			<td></td>
		</tr>
		<tr style="text-align:left;vertical-align:bottom;">
			<td>Tgl/Jam:</td>
			<td>Tgl/Jam:</td>
		</tr>
	</table>
</div>
<div style="position: absolute; bottom: -30;padding-bottom:10px">
    <div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>

</div>
</body>
</html>
