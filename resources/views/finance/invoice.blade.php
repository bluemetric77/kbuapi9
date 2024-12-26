<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Pembelian {{$header->doc_number ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 1cm 1cm 1cm;
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
<div class="header">INVOICE PEMBELIAN</div>
<div class="information">
    <table class="tb-header">
        <tr>
            <td width="80px">No.Dokumen</td>
            <td width="320px">: <strong>{{$header->doc_number}}</strong></td>
            <td width="100px">Invoice Supplier</td>
            <td width="200px">: {{$header->ref_document}}</td>
        </tr>
        <tr>
            <td>No.PO</td>
            <td>: {{$header->order_document}}</td>
            <td>Jatuh Tempo</td>
            <td>: {{$header->due_date}}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>: {{$header->ref_date}}</td>
            <td>Diterima</td>
            <td>: {{$header->warehouse_name}}</td>
        </tr>
        <tr>
            <td>Supplier</td>
            <td>:{{$header->partner_name}}</td>
            <td></td>
            <td></td>
        </tr>
	</table>
</div>

<div class="invoice" style="margin-left:0px">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="250px">Nama Barang</th>
				<th width="80px">No.Part</th>
				<th width="40px">Jumlah</th>
				<th width="70px">Harga</th>
				<th width="40px">Diskon</th>
				<th width="40px">PPN</th>
				<th width="90px">Total</th>
			</tr>
		</thead>
		<tbody>
    	    @php $total=0; @endphp
			@foreach($detail as $line)
		    <tr  style="text-align:left;vertical-align:top">
				<td align="center">{{$line->line_no}}</td>
				<td>{{$line->descriptions}}</td>
				<td>{{$line->part_number}}</td>
				<td align="right">{{number_format($line->qty_invoice,0,',','.')}}</td>
				<td align="right">{{number_format($line->purchase_price,0,',','.')}}</td>
				<td align="right">{{number_format($line->prc_discount1,2,',','.')}}</td>
				<td align="right">{{number_format($line->prc_tax,2,',','.')}}</td>
				<td align="right">{{number_format($line->total,2,',','.')}}</td>
			</tr>
			@php
			$total = $total + $line->total;
			@endphp
			@endforeach
			<tr  style="font-weight:bold">
				<td colspan="7">Jumlah</td>
				<td align="right">{{number_format($header->amount,2,',','.')}}</td>
			</tr>
			<tr  style="font-weight:bold">
				<td colspan="7">Diskon</td>
				<td align="right">{{number_format($header->discount1,2,',','.')}}</td>
			</tr>
			<tr  style="font-weight:bold">
				<td colspan="7">Ppn</td>
				<td align="right">{{number_format($header->tax,2,',','.')}}</td>
			</tr>
			<tr  style="font-weight:bold">
				<td colspan="7">Total</td>
				<td align="right">{{number_format($header->net_total,2,',','.')}}</td>
			</tr>
		</tbody>
	</table>
	<br/>
	<br/>
	@php
	$user_sign = $sign['user_sign'];
	@endphp
	<table class="tb-row">
		<tr style="text-align:center;vertical-align:center;font-weight:bold">
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Diperiksa oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;">
			<td height="60px">
				<img src="{{$user_sign}}" width="100px" height="100px"><br/>
				{{$header->user_name}}</td>
			<td></td>
		</tr>
		<tr style="text-align:left;vertical-align:bottom;font-size:10px">
			<td>Tgl/Jam</td>
			<td>Tgl/Jam</td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
