<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Permintaan Stock {{$header[0]->doc_number ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 0.5cm 1cm 1cm;
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
<div class="header">PERMINTAAN PEMBELIAN BARANG</div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No.Permintaan</td>
			<td width="300px">: <strong>{{$header->doc_number}}</strong></td>
            <td width="100px">Permintaan ke</td>
			<td width="250px">: {{$header->warehouse_name}}</td>
        </tr>
        <tr>
			<td>Tanggal</td>
			<td>: {{$header->ref_date}}</td>
            <td>Dari Pool</td>
			<td>:  {{$header->pool_code}} {{$header->pool_name}}</td>
        </tr>
        <tr>
			<td>Keterangan</td>
			<td colspan="3">: {{$header->descriptions}}</td>
        </tr>
	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="80px">Kode</th>
				<th width="120px">No.Part</th>
				<th width="250px">Nama Barang/Sparepart</th>
				<th width="50px">Jumlah</th>
				<th width="80px">Satuan</th>
				<th width="50px">Stock</th>
			</tr>
		</thead>
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($detail as $line)
		    <tr  style="text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->item_code}}</td>
				<td>{{$line->part_number}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->qty_request,0,',','.')}}</td>
				<td>{{$line->mou_inventory}}</td>
				<td align="right">{{number_format($line->current_stock,0,',','.')}}</td>
			</tr>
			@php
			$amount = $amount + $line->amount;
			@endphp
			@endforeach
		</tbody>
	</table>
    <br/>
	<table class="tb-row">
		<tr style="text-align:center;vertical-align:center;font-weight:bold">
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Disetujui oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="80px">{{$header->user_name}}</td>
			<td></td>
		</tr>
		<tr style="font-size:10px;text-align:left;vertical-align:bottom;">
			<td>Tgl/Jam {{$header->update_timestamp}}</td>
			<td>Tgl/Jam</td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
