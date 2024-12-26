<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Pembelian {{$header[0]->doc_number ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 10px 30px 20px 20px;
            font-size:12px;
		}
        .header {
           font-size:20px;
           text-align:center;
           margin-bottom:10px;
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
            text-align:left;
            vertical-align:top;
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
            padding:2px 5px;
        }
	</style>
</head>
<body>
<img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
@if ($header[0]->is_posted === '1')
	<div class="header"><strong>PURCHASE ORDER</strong></div>
@else
	<div class="header"><strong>PURCHASE ORDER (DRAFT)</strong></div>
@endif
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No.PO</td>
			<td width="550px">: <strong>{{$header[0]->doc_number}}</strong></td>
            <td>{{$header[0]->ref_date}}</td>
        </tr>

	</table>
</div>

<div class="information">
    <table class="tb-header">
        <tr>
			<td width="500px" colspan="2" class="header-title">REKANAN/SUPPLIER</td>
            <td width="300px" colspan="2" class="header-title">UNTUK DIKIRIM</td>
        </tr>
        <tr>
			<td width="100px"><Strong>Kepada</strong></td>
			<td width="280px">: {{$header[0]->partner_name}}</td>
            <td width="100px"><Strong>Kepada</strong></td>
			<td width="250px">: {{$profile->name}}</td>
        </tr>
        <tr>
			<td><Strong>Alamat</strong></td>
			<td>: {{$header[0]->partner_address}}</td>
            <td><Strong>Alamat</strong></td>
			<td>: {{$profile->address}}</td>
        </tr>
        <tr>
			<td><Strong>Kota</strong></td>
			<td>:</td>
            <td><Strong>Kota</strong></td>
			<td>: {{$profile->city}}</td>
        </tr>
        <tr>
			<td><Strong>Telepon</strong></td>
			<td>: {{$header[0]->phone_number}}</td>
            <td><Strong>Telepon</strong></td>
			<td>: {{$profile->phone}}</td>
        </tr>
        <tr>
			<td><Strong>Attn</strong></td>
			<td>: {{$header[0]->contact_person}}</td>
            <td><Strong>Attn</strong></td>
			<td>:</td>
        </tr>
	</table>
</div>
<br/>
<div class="invoice" style="margin-left:0px">
	<table class="tb-row">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="120px">No.Part</th>
				<th width="250px">Nama Barang</th>
				<th width="40px">Stock</th>
				<th width="40px">Qty</th>
				<th width="40px">Satuan</th>
				<th width="70px">Harga Sat.</th>
				<th width="90px">Jml.Harga</th>
			</tr>
		</thead>
		<tbody>
    	    @php $total=0; @endphp
			@foreach($header as $line)
		    <tr>
				<td align="center">{{$line->line_no}}</td>
				<td>{{$line->part_number}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->current_stock,0,',','.')}}</td>
				<td align="right">{{number_format($line->qty_order,0,',','.')}}</td>
				<td align="left">{{$line->mou_purchase}}</td>
				<td align="right">{{number_format($line->price,0,',','.')}}</td>
				<td align="right">{{number_format($line->total,0,',','.')}}</td>
			</tr>
			@php
			$total = $total + $line->total;
			@endphp
			@endforeach
			<tr  style="font-size:12px;font-weight:bold">
				<td colspan="7">TOTAL</td>
				<td align="right">{{number_format($total,0,',','.')}}</td>
			</tr>
		</tbody>
	</table>
    <br/>
    <div style="font-size:12px;">
        <span><strong>Keterangan</strong></span><br/>
        <span>{{$header[0]->purchase_instruction}}</span><br/>
    </div>
    <br/>
	@php
	$manager = $sign['manager'];
	$general_manager = $sign['general_manager'];
	$director = $sign['director'];
	@endphp
	<table class="tb-row">
		<tr>
			<th width="250px" colspan="2">Dibuat oleh</th>
			<th width="150px">Diketahui oleh</th>
			<th width="150px">Disetujui oleh</th>
			<th width="150px">Supplier</th>
		</tr>
		<tr style="text-align:center;vertical-align:bottom">
			<td height="100px">
				<img src="{{$manager}}" width="100px" height="100px"><br/>
				{{$header[0]->user_name}}
			</td>
			<td width="120px">Epen Ependi</td>
			<td>
				<img src="{{$general_manager}}" width="100px" height="100px"><br/>
				Aep Hardianto
			</td>
			<td>
				<img src="{{$director}}" width="100px" height="100px"><br/>
				M. Andriana Ruhyana, SE
			</td>
			<td>
			</td>
		</tr>
		<tr style="text-align:center;font-weight:bold">
			<td>Pembelian</td>
			<td>Ka.Tehnik</td>
			<td>Keuangan</td>
			<td>Direktur</td>
			<td>Supplier</td>
		</tr>
		<tr style="text-align:center;font-size:10px">
			<td>Tgl {{isset($header[0]->posted_date) ?date_format($header[0]->posted_date,'d-m-Y H:i'):''}}</td>
			<td>Tgl</td>
			<td>Tgl {{isset($header[0]->approved_date1) ?date_format($header[0]->approved_date1,'d-m-Y H:i'):''}}</td>
			<td>Tgl {{isset($header[0]->approved_date2) ?date_format($header[0]->approved_date2,'d-m-Y H:i'):''}}</td>
			<td></td>
		</tr>
	</table>
</div>
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
