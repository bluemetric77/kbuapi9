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
<div style="font-size:14px;"><strong>{{strtoupper($profile->name)}}</strong></div>
<div style="font-size:16px;text-align:right;margin-bottom:10px"><strong>WORK ORDER SERVICE REPORT</strong></div>
@php
$mekanik = $sign['mekanik'];
@endphp
<table class="table-detail">
	<tbody>
		<tr>
			<td width="100px" class="table-title">SERVICE NUM.</td>
			<td width="240px" style="font-size:16px" colspan="2"><strong>{{$header->doc_number}}</strong></td>
			<td width="100px" class="table-title">VIN</td>
			<td width="240px" colspan="2">.</td>
		<tr>
			<td class="table-title">DATE</td>
			<td colspan="2">{{$header->ref_date}} - {{$header->ref_time}}</td>
			<td class="table-title">CHASIS NUM.</td>
			<td colspan="2">{{$header->chasis_no}}</td>
		</tr>
		<tr>
			<td class="table-title">BODY NUM.</td>
			<td colspan="2">{{$header->vehicle_no}} - {{$header->police_no}}</td>
			<td class="table-title">ENGINE NUM.</td>
			<td colspan="2">{{$header->vin}}</td>
		</tr>
		<tr>
			<td class="table-title">KM</td>
			<td colspan="2">{{$header->odo_service}}</td>
			<td class="table-title">DRIVER</td>
			<td colspan="2">{{$header->requester}}</td>
		</tr>
		<tr>
			<td class="table-title">ORDER DATE</td>
			<td>{{$header->planning_date}} </td>
			<td class="table-title">FINISH ORDER</td>
			<td>{{$header->estimate_date}} </td>
			<td class="table-title">FINISH REPAIR</td>
			<td>{{$header->close_date}} </td>
		</tr>
		<tr>
			<td class="table-title">MECHANIC SPV</td>
			<td colspan="2">{{$header->service_advisor}}</td>
			<td class="table-title">MECHANIC</td>
			<td colspan="2">{{$header->mechanic_name}}</td>
		</tr>
		<tr>
			<td colspan="3">
				<div>
					@if ($header->service_type=='Checklist') 
						<input type='checkbox' id="checklist" name="checklist"  style="vertical-align:middle" checked>
					@else
						<input type='checkbox' id="checklist" name="checklist"  style="vertical-align:middle" unchecked>
					@endif
					<label for="checklist" style="vertical-align:middle" >CHECKLIST</label>
				</div>	
				<div>
					@if ($header->service_type=='Periodik') 
						<input type='checkbox' id="periodik" name="periodik"  style="vertical-align:middle" checked>
					@else
						<input type='checkbox' id="periodik" name="periodik"  style="vertical-align:middle" unchecked>
					@endif
					<label for="periodik"  style="vertical-align:middle">PERIODIK</label>
				</div>	
				<div>
					@if ($header->service_type=='Storing') 
						<input type='checkbox' id="storing" name="storing"  style="vertical-align:middle" checked>
					@else
						<input type='checkbox' id="storing" name="storing"  style="vertical-align:middle" unchecked>
					@endif
					<label for="storing"  style="vertical-align:middle" >STORING</label>
				</div>	
			</td>
			<td colspan="3" style="vertical-align:top">
				<div>PROBLEMS</div>
				{!! nl2br(e($header->problem)) !!}
			</td>
		</tr>
		<tr>
			<td colspan="6"></td>
		</tr>
		<tr>
			<td colspan="6" class="table-title" align="center">WORK DESCRIPTIONS</td>
		</td>
		<tr>
			<td colspan="6" height="80px"></td>
		</td>
		<tr>
			<td colspan="6"></td>
		</tr>
		<tr>
			<td colspan="6" class="table-title" align="center">PART TYPE</td>
		</td>
		<tr align="center">
			<td class="table-title">CODE</td>
			<td class="table-title">PART NUMBER</td>
			<td class="table-title" colspan="2">PART NAME</td>
			<td class="table-title">QUANTITY</td>
			<td class="table-title">AMOUNT</td>
		</td>
		@php $total=0;@endphp;
		@foreach($material as $line)
			<tr  style="text-align:left;vertical-align:top">
				<td align="center">{{$line->item_code}}</td>
				<td align="center">{{$line->part_number}}</td>
				<td colspan="2">{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->used,2,',','.')}} {{$line->mou_inventory}}</td>
				<td align="right">{{number_format($line->line_cost,2,',','.')}}</td>
			</tr>
			@php $total=$total+$line->line_cost;@endphp;
		@endforeach
		<tr align="center">
			<td  height="16px"></td>
			<td></td>
			<td colspan="2"></td>
			<td class="table-title">PARTS TOTAL</td>
			<td class="table-title" align="right">{{number_format($total,2,',','.')}}</td>
		</td>
		<tr>
			<td colspan="6"></td>
		</tr>
		<tr>
			<td colspan="6" class="table-title" align="center">OUTSIDE REPAIR</td>
		</td>
		<tr align="center">
			<td class="table-title">CODE</td>
			<td class="table-title">PART NUMBER</td>
			<td class="table-title" colspan="2">PART NAME</td>
			<td class="table-title">QUANTITY</td>
			<td class="table-title">AMOUNT</td>
		</td>
		@php $total=0;@endphp;
		@foreach($outsite as $line)
			<tr  style="text-align:left;vertical-align:top">
				<td align="center">{{$line->item_code}}</td>
				<td align="center">{{$line->part_number}}</td>
				<td colspan="2">{{$line->descriptions}}</td>
				<td align="right">{{number_format($line->qty_invoice,2,',','.')}} {{$line->mou_inventory}}</td>
				<td align="right">{{number_format($line->total,2,',','.')}}</td>
			</tr>
			@php $total=$total+$line->total;@endphp;
		@endforeach
		<tr align="center">
			<td  height="16px"></td>
			<td></td>
			<td colspan="2"></td>
			<td class="table-title">PARTS TOTAL</td>
			<td class="table-title" align="right">{{number_format($total,2,',','.')}}</td>
		</td>
		<tr>
			<td colspan="6" align="center"></td>
		</td>
		<tr>
			<td class="table-title">WORK ORDER COMPILED BY</td>
			<td colspan="2">{{$header->user_name}}</td>
			<td colspan="3" rowspan="2">
				<img src="{{$mekanik}}" style="width:auto;height:50px">				
			</td>
		</td>
		<tr>
			<td class="table-title">AUTHORIZATION DATE</td>
			<td colspan="2">{{$header->update_timestamp}}</td>
		</td>
		<tr align="center" style="vertical-align:top">
			<td colspan="2" height="60px">Mengetahui</td>
			<td colspan="2">
				<div>Mekanik Supervisor</div>
				<div style="margin-top:50px">{{$header->service_advisor}}</div>
			</td>
			<td colspan="2">
				<div>Awak Bus</div>
				<div style="margin-top:50px">{{$header->requester}}</div>
			</td>
		</td>
	</tbody>
</table>
</body>
</html>