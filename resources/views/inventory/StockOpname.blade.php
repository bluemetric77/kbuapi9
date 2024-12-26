<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Opname {{$header[0]->doc_number ?? ''}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 50px 30px 50px 30px;
		}

	</style>
</head>
<body>
<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>KOREKSI STOCK</strong></div>
<div class="information">
    <table width="100%" style="padding:0px"> 
        <tr style="padding:0px;margin-bottom:0px">
            <td style="width:450px;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:10px">
No.Dokumen  : <strong>{{$header[0]->doc_number}}</strong>
Tanggal     : {{$header[0]->ref_date}}
				</pre>
            </td>
            <td style="width:50%;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:10px">
Gudang  : {{$header[0]->warehouse_name}}
Pool    : {{$header[0]->pool_code}} {{$header[0]->pool_name}}
                </pre>
            </td>
        </tr>

	</table>
</div>
<br/>
<div class="invoice" style="margin-left:0px">
	<table class="table-detail" style="font-size:10px">
		<thead>
			<tr>
				<th width="20px">No</th>
				<th width="80px">Kode</th>
				<th width="200px">Nama Barang/Sparepart</th>
				<th width="70px">Komputer</th>
				<th width="70px">Fisik</th>
				<th width="70px">Selisih</th>
				<th width="70px">Satuan</th>
				<th width="70px">Harga</th>
			</tr>
		</thead>		
		<tbody>
    	    @php $amount=0; @endphp
			@foreach($header as $line)
		    <tr  style="font-size:10px;text-align:left;vertical-align:top">
				<td>{{$line->line_no}}</td>
				<td>{{$line->item_code}}</td>
				<td>{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->current_stock,2,',','.')}}</td>
				<td align="right">{{number_format($line->opname_stock,2,',','.')}}</td>
				<td align="right">{{number_format($line->end_stock,2,',','.')}}</td>
				<td>{{$line->mou_inventory}}</td>
				<td align="right">{{number_format($line->cost_adjustment,2,',','.')}}</td>
			</tr>
			@php
			$amount = $amount + $line->amount;
			@endphp
			@endforeach
		</tbody>
	</table>
    <br/>
	<table class="table-detail" style="font-size:10px">
		<tr style="text-align:center;vertical-align:center"> 
			<td width="150px">Dibuat oleh</td>
			<td width="150px">Divalidasi oleh</td>
		</tr>
		<tr style="text-align:center;vertical-align:bottom;"> 
			<td height="40px">({{$header[0]->user_name}})</td>
			<td>(..................................................)</td>
		</tr>
	</table>
</div>	
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
		<table width="100%">
			<tr style="font-size:10px">
				<td align="left" style="width: 50%;">
					&copy; {{ date('Y') }} {{ config('app.url') }}
				</td>
				<td align="right" style="width:50%;">
					{{$profile->name}}
				</td>
			</tr>
		</table>
	</div>
</body>
</html>
