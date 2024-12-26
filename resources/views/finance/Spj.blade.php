<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPJ {{$header->doc_number}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 0.2cm 1cm 0.5cm 1cm;
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
       .header-title {
            font-weight:bold;
            background-color: black;
            color: white;
            padding:2px 5px;
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
<div class="header">BUKTI SETORAN OPERASI</div>
<div class="information">
    <table class="tb-header">
        <tr>
			<td width="100px">No.Penerimaan</td>
			<td width="350px">: <strong>{{$header->doc_number}}</strong></strong></td>
            <td width="100px">Pengemudi</td>
			<td width="250px">: {{$header->driver}}</td>
        </tr>
        <tr>
			<td>Tanggal</td>
			<td>: {{$header->ref_date}}</td>
            <td>Tagihan Laka</td>
			<td>: {{number_format($header->debt_accident,2,',','.')}}</td>
        </tr>
        <tr>
			<td>No.SPJ</td>
			<td>:  {{$header->doc_operation}}</td>
            <td>Kernet</td>
			<td>: {{$header->helper}}</td>
        </tr>
        <tr>
			<td>Tanggal SPJ</td>
			<td>: {{$header->spj_date}}</td>
            <td>Kondektur</td>
			<td>: {{$header->conductor}}</td>
        </tr>
        <tr>
			<td>Unit</td>
			<td>: {{$header->vehicle_no}} - {{$header->police_no}}</td>
            <td>Tagihan KS</td>
			<td>: {{number_format($header->debt_deposit,2,',','.')}}</td>
        </tr>
        <tr>
			<td>Rute</td>
			<td>: {{$header->route_name}}</td>
            <td>No.Jurnal</td>
			<td>: {{$header->trans_code}} -  {{$header->trans_series}}</td>
        </tr>
	</table>
</div>
<div class="invoice" style="margin-left:0px">
	<table class="tb-header">
		<tr>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<table class="tb-row">
					<thead>
						<tr>
							<th width="150px">Pos Kontrol</th>
							<th width="50px">Point</th>
							<th width="50px">Penumpang</th>
							<th width="50px">Total</th>
						</tr>
					</thead>
					<tbody>
						@php $total=0 @endphp
						@foreach($go as $p)
						<tr>
							<td>{{$p->checkpoint_name}}</td>
							<td align="right">{{number_format($p->factor_point*$p->point,0,',','.')}}</td>
							<td align="center">{{number_format($p->passenger,0,',','.')}}</td>
							<td align="right">{{number_format($p->total,0,',','.')}}</td>
						</tr>
						@php
						$total = $total + $p->total;
						@endphp
						@endforeach
						<tr style="font-size:12px;font-weight:bold">
							<td>TOTAL</td>
							<td></td>
							<td></td>
							<td align="right">{{number_format($total,0,',','.')}}</td>
						</tr>
					</tbody>
				</table>
			</td>
			<td style="border:none;text-align:left;vertical-align:top;padding:0px">
				<table class="tb-row">
					<thead>
						<tr>
							<th width="150px">Pos Kontrol</th>
							<th width="50px">Point</th>
							<th width="50px">Penumpang</th>
							<th width="50px">Total</th>
						</tr>
					</thead>
					<tbody>
						@php $total=0 @endphp
						@foreach($back as $p)
						<tr>
							<td>{{$p->checkpoint_name}}</td>
							<td align="right">{{number_format($p->factor_point*$p->point,0,',','.')}}</td>
							<td align="center">{{number_format($p->passenger,0,',','.')}}</td>
							<td align="right">{{number_format($p->total,0,',','.')}}</td>
						</tr>
						@php
						$total = $total + $p->total;
						@endphp

						@endforeach

						<tr  style="font-size:12px;font-weight:bold">
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

<div>
	@if ($header->paid_type === 'POINT')
	<table class="tb-header">
		<tr>
			<td width="100px">Target Setoran</td>
			<td>:</td>
			<td width="150px" style="text-align:right;padding:0px">{{number_format($header->target,0,',','.')}}</td>
			<td width="150px" style="padding-left:20px">Yang harus disetor</td>
			<td>:</td>
			<td width="100px" style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Total Point</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->revenue,0,',','.')}}</td>
			<td style="padding-left:20px">Dispensasi</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Yang Harus disetor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others),0,',','.')}}</td>
			<td style="padding-left:20px">Total setor setelah dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->dispensation),0,',','.')}}</td>
		</tr>
		<tr>
			<td></td>
			<td></td>
			<td></td>
			<td style="padding-left:20px">Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid-($header->target2+$header->others),0,',','.')}}</td>
		</tr>
		<tr>
			<td></td>
			<td></td>
			<td></td>
			<td  style="padding-left:20px;font-weight:bold" >Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
	</table>
	@elseif ($header->paid_type === 'POINT-BIAYA 1')
	<table class="tb-header">
		<tr>
			<td width="150px">Target Setoran</td>
			<td>:</td>
			<td width="100px" style="text-align:right;padding:0px">{{number_format($header->target,0,',','.')}}</td>
			<td style="padding-left:20px">Yang harus disetor</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->external_cost),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Total Point</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->revenue,0,',','.')}}</td>
			<td style="padding-left:20px">Dispensasi</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Total</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others),0,',','.')}}</td>
			<td style="padding-left:20px">Total setor setelah dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->dispensation+$header->external_cost),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Biaya Operasional (Crew)</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->external_cost,0,',','.')}}</td>
			<td style="padding-left:20px">Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid-($header->target2+$header->others),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Yang Harus disetor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->external_cost),0,',','.')}}</td>
			<td  style="padding-left:20px;font-weight:bold" >Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
	</table>
	@elseif ($header->paid_type === 'POINT-TASIK')
	<table class="tb-header">
		<tr>
			<td style="width:200px">Total Point</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;width:100px">{{number_format($header->revenue,0,',','.')}}</td>
			<td style="padding-left:50px;width:200px">Yang harus disetor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->external_cost+$header->internal_cost+$header->station_fee+$header->operation_fee),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Total</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others),0,',','.')}}</td>
			<td style="padding-left:50px">Dispensasi</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Komisi Terminal</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->station_fee,0,',','.')}}</td>
			<td style="padding-left:50px">Total setor setelah dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->dispensation+$header->external_cost+$header->internal_cost+$header->station_fee+$header->operation_fee),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Komisi Crew (10%)</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->external_cost,0,',','.')}}</td>
			<td style="padding-left:50px">Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid-($header->target2+$header->others),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Biaya Operasional</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->internal_cost,0,',','.')}}</td>
			<td  style="padding-left:50px;font-weight:bold" >Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Bonus Operasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->operation_fee,0,',','.')}}</td>
		</tr>
	</table>
	@elseif ($header->paid_type === 'SETORAN')
	<table class="tb-header">
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
	@elseif ($header->paid_type === 'POINT-TASIK-2')
	<table class="tb-header">
		<tr>
			<td style="width:200px">Total Point</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;width:100px">{{number_format($header->revenue,0,',','.')}}</td>
			<td style="padding-left:50px;width:200px">Yang harus disetor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->external_cost+$header->internal_cost+$header->station_fee+$header->operation_fee),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Total</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others),0,',','.')}}</td>
			<td style="padding-left:50px">Dispensasi</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Komisi Terminal</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->station_fee,0,',','.')}}</td>
			<td style="padding-left:50px">Total setor setelah dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->dispensation+$header->external_cost+$header->internal_cost+$header->station_fee+$header->operation_fee),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Komisi Crew (10%)</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->external_cost,0,',','.')}}</td>
			<td style="padding-left:50px">Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid-($header->target2+$header->others),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Biaya Operasional</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->internal_cost,0,',','.')}}</td>
			<td  style="padding-left:50px;font-weight:bold" >Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Bonus Operasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->operation_fee,0,',','.')}}</td>
		</tr>
	</table>
	@elseif ($header->paid_type === 'POINT-SUKABUMI')
	<table class="tb-header">
		<tr>
			<td style="width:200px">Total Point</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;width:100px">{{number_format($header->revenue,0,',','.')}}</td>
			<td style="padding-left:50px;width:200px">Yang harus disetor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->external_cost+$header->station_fee+$header->operation_fee),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Total</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others),0,',','.')}}</td>
			<td style="padding-left:50px">Dispensasi</td>
			<td>:</td>
			<td width="135px" style="text-align:right;padding:0px">({{number_format($header->dispensation,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Komisi Terminal</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->station_fee,0,',','.')}}</td>
			<td style="padding-left:50px">Total setor setelah dispensasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->total-($header->target2+$header->others+$header->dispensation+$header->external_cost+$header->station_fee+$header->operation_fee),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Komisi Crew (15%)</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->external_cost,0,',','.')}}</td>
			<td style="padding-left:50px">Penerimaan Kasir</td>
			<td>:</td>
			<td style="text-align:right;padding:0px;font-weight:bold;font-size:12px">{{number_format($header->paid-($header->target2+$header->others),0,',','.')}}</td>
		</tr>
		<tr>
			<td>Biaya Operasional</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->internal_cost,0,',','.')}}</td>
			<td  style="padding-left:50px;font-weight:bold" >Kurang setor</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">({{number_format($header->unpaid,0,',','.')}})</td>
		</tr>
		<tr>
			<td>Bonus Operasi</td>
			<td>:</td>
			<td style="text-align:right;padding:0px">{{number_format($header->operation_fee,0,',','.')}}</td>
		</tr>
	</table>
	@endif
</div>

<div style="margin-top:10px">
	<table class="table-detail" style="font-size:12px">
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
		<div style="font-size:10px">Dicetak tanggal-jam : {{Date('d-m-Y H:i:s')}}</div>
	</div>
</div>


<div class="page-break">
    <img src="{{storage_path('app/public/logo-print.jpeg')}}" width="150px">
	<div class="header"><strong>BUKTI SETORAN LAIN-LAIN</strong></div>
    <div class="information">
        <table class="tb-header">
            <tr>
                <td width="100px">No.Penerimaan</td>
                <td width="350px">: <strong>{{$header->doc_number}}</strong></strong></td>
                <td width="100px">Pengemudi</td>
                <td width="250px">: {{$header->driver}}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: {{$header->ref_date}}</td>
                <td>Kernet</td>
                <td>: {{$header->helper}}</td>
            </tr>
            <tr>
                <td>No.SPJ</td>
                <td>:  {{$header->doc_operation}}</td>
                <td>Kondektur</td>
                <td>: {{$header->conductor}}</td>
            </tr>
            <tr>
                <td>Unit</td>
                <td>: {{$header->vehicle_no}} - {{$header->police_no}}</td>
                <td></td>
                <td></td>
            </tr>

        </table>
    </div>
    <div class="invoice">
        <table class="tb-row">
            <thead>
                <tr>
                    <th width="250px" align="left">Komponen Biaya Lain-Lain</th>
                    <th width="50px">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @php $total=0 @endphp
                @foreach($others as $p)
                <tr>
                <td>{{$p->item_name}}</td>
                <td align="right">{{number_format($p->amount,2,',','.')}}</td>
                </tr>
                @php
                $total = $total + $p->amount;
                @endphp
                @endforeach
                <tr  style="font-weight:bold">
                <td>TOTAL</td>
                <td align="right">{{number_format($total,0,',','.')}}</td>
                </tr>
                <tr  style="font-weight:bold">
                <td>Biaya Operasional</td>
                <td align="right">{{number_format($header->target2,0,',','.')}}</td>
                </tr>
                <tr  style="font-weight:bold">
                <td>Biaya Operasional</td>
                <td align="right">{{number_format($total+$header->target2,0,',','.')}}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <br/>
	<table class="table-detail" style="font-size:12px">
        <tr>
            <td width="150px">Yang menerima setoran</td>
            <td width="150px">Kondektur</td>
        </tr>
        <tr style="text-align:center;vertical-align:bottom;">
            <td height="80px">({{$header->user}})</td>
            <td>({{$header->conductor}})</td>
        </tr>
    </table>
    <div style="position: absolute; bottom: -30;padding-bottom:10px">
        <div style="font-size:10px">Dicetak tanggal-jam : {{Date('d-m-Y H:i:s')}}</div>
    </div>
</body>
</html>
