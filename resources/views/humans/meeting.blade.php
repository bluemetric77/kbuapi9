<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notulen rapat {{$header->title}}</title>
    <link rel="stylesheet" href="{{public_path('/css/document.css')}}" type="text/css" media="all">
	<style>
		@page  {
			margin: 50px 30px 50px 50px;
		}

	</style>
</head>
<body>

<div style="font-size:14px;"><strong>{{$profile->name}}</strong></div>
<div style="font-size:18px;text-align:center;margin-bottom:10px"><strong>NOTULEN RAPAT</strong></div>
<div class="information">
                <pre style="line-height: 2.0;">
Agenda  : <strong>{{$header->title}}</strong>
Tanggal : {{$header->ref_date}} {{$header->ref_time}}
Peserta : 
{{$header->audiance}}
<br/>
<strong>Hasil Rapat</strong>

				</pre>
{!!$header->notes !!}
</div>

<div class="information" style="position: absolute; bottom: -30;">
    <table width="100%">
        <tr style="font-size:10px">
            <td align="left" style="width: 50%;">
                &copy; {{ date('Y') }} {{ config('app.url') }} - All rights reserved.
            </td>
            <td align="right" style="width:50%;">
                {{$profile->name}}
            </td>
        </tr>

    </table>
</div>
</body>
</html>