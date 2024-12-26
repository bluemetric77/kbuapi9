<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPJ {{$header->doc_number}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.5cm 1cm 1cm 1cm;
		}

	</style>
</head>
<body>

<div style="font-size:14px;text-align:center;margin-bottom:10px"><strong>BUKTI SETORAN OPERASI</strong></div>
<div class="information">
    <table width="100%" style="padding:0px"> 
        <tr style="padding:0px;margin-bottom:0px">
            <td style="width:65%;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:11px">
No.Penerimaan : <strong>{{$header->doc_number}}</strong>
Tanggal       : {{$header->ref_date}}
No.SPJ        : {{$header->doc_operation}}
Tanggal SPJ   : {{$header->spj_date}}
Unit          : {{$header->vehicle_no}} - {{$header->police_no}}
Rute          : {{$header->route_name}}
				</pre>
            </td>
            <td style="width:65%;text-align:left;vertical-align:top;padding:0px">
                <pre style="font-size:11px">
Pengemudi   : {{$header->driver}}
Tagihan Laka: {{number_format($header->debt_accident,2,',','.')}}
Kernet 	    : {{$header->helper}}
Kondektur   : {{$header->conductor}}
Tagihan KS  : {{number_format($header->debt_deposit,2,',','.')}}
No.Jurnal   : {{$header->trans_code}} -  {{$header->trans_series}}
                </pre>
            </td>
        </tr>
	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table width="100%">
		<tr>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<table class="table-detail" style="font-size:10px">
					<thead>
						<tr>
							<th width="100px">Pos Kontrol</th>
							<th width="50px">Point</th>
							<th width="50px">Penumpang</th>
							<th width="50px">Total</th>
						</tr>
					</thead>		
					<tbody>
						@php $total=0 @endphp
						@foreach($go as $p)
						<tr  style="font-size:10px">
							<td>{{$p->checkpoint_name}}</td>
							<td align="center">{{number_format($p->factor_point*$p->point,2,',','.')}}</td>
							<td align="center">{{number_format($p->passenger,0,',','.')}}</td>
							<td align="right">{{number_format($p->total,0,',','.')}}</td>
						</tr>
						@php
						$total = $total + $p->total;
						@endphp
						@endforeach
						<tr  style="font-size:10px;font-weight:bold">
							<td>TOTAL</td>
							<td></td>
							<td></td>
							<td align="right">{{number_format($total,0,',','.')}}</td>
						</tr>
					</tbody>
				</table>
			</td>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<table class="table-detail" style="font-size:10px">
					<thead>
						<tr>
							<th width="100px">Pos Kontrol</th>
							<th width="50px">Point</th>
							<th width="50px">Penumpang</th>
							<th width="50px">Total</th>
						</tr>
					</thead>		
					<tbody>
						@php $total=0 @endphp
						@foreach($back as $p)
						<tr  style="font-size:10px">
							<td>{{$p->checkpoint_name}}</td>
							<td align="center">{{number_format($p->factor_point*$p->point,2,',','.')}}</td>
							<td align="center">{{number_format($p->passenger,0,',','.')}}</td>
							<td align="right">{{number_format($p->total,0,',','.')}}</td>
						</tr>
						@php
						$total = $total + $p->total;
						@endphp

						@endforeach

						<tr  style="font-size:10px;font-weight:bold">
							<td>TOTAL</td>
							<td></td>
							<td></td>
							<td align="right">{{number_format($total,0,',','.')}}</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</table>
</div>	
	<b/>
	@if ($header->paid_type === 'POINT')
	<table style="font-size:12px;line-height:1.2">
		<tr> 
			<td width="150px">Target Setoran 1</td>
			<td>:</td>
			<td width="100px" style="text-align:right;padding:0px">{{number_format($header->target,0,',','.')}}</td>
			<td style="padding-left:20px">Yang harus disetor</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">{{number_format($header->total,0,',','.')}}</td>
		</tr>
		<tr> 
			<td width="150px">Target Setoran 2</td>
			<td>:</td>
			<td width="100px" style="text-align:right;padding:0px">{{number_format($header->target2,0,',','.')}}</td>
			<td style="padding-left:20px">Dispensasi</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr> 
			<td>Total Point</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->revenue,0,',','.')}}</td>
			<td style="padding-left:20px">Total setor setelah dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-$header->dispensation,0,',','.')}}</td>
		</tr>
		<tr> 
			<td>Lain-Lain</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->others,0,',','.')}}</td>
			<td style="padding-left:20px">Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid,0,',','.')}}</td>
		</tr>
		<tr> 
			<td>Yang Harus disetor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total,0,',','.')}}</td>
			<td  style="padding-left:20px;font-weight:bold" >Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
	</table>
	@elseif ($header->paid_type === 'SETORAN')
	<table style="font-size:11px;line-height:1.2">
		<tr> 
			<td>Setoran</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->deposit,0,',','.')}}</td>
		</tr>
		<tr> 
			<td>Biaya lain-lain</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->others,0,',','.')}}</td>
		</tr>
		<tr>
			<td>Dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr style="font-weight:bold">
			<td>Jumlah setoran yang harus dibayar</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total - $header->dispensation,0,',','.')}}</td>
		</tr>
		<tr>
			<td>Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid,0,',','.')}}</td>
		</tr>
		<tr>
			<td>Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
	</table>
	@endif
	<!--<div style="padding-left:5px">Terbilang : {{$terbilang}}</div> -->
	<br/>
	<table style="font-size:10px;line-height:0.5">
		<tr style="border:none;text-align:center;vertical-align:bottom;padding:0px"> 
			<td width="150px">Yang menerima setoran</td>
			<td width="150px">Kondektur</td>
		</tr>
		<tr style="border:none;text-align:center;vertical-align:bottom;padding-top:0px"> 
			<td height="35px">({{$header->user}})</td>
			<td>({{$header->conductor}})</td>
		</tr>
	</table>	
	<div style="position: absolute; bottom: -30;padding-bottom:10px">
		<div style="font-size:10px">dokumen dicetak : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</body>
</html>
