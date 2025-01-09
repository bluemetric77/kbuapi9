<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(\Config\CompanyController::class)->group(function () {
    Route::get('profile', 'CompanyProfile');
    Route::get('pool', 'getpool');
});

Route::controller(\Config\UserController::class)->group(function () {
    Route::post('login', 'UserAuth');
});

Route::controller(\Config\SecureController::class)->group(function () {
    Route::get('/access/securitypage', 'Verified');
});

Route::post('attendance', function () {
    Log::info("Attendance entered");
});
Route::get('attendance', function () {
    Log::info("Attendance entered get");
});
Route::post('upload', function () {
    Log::info("Upload entered");
});
Route::get('upload', function () {
    Log::info("Upload entered get");
});

Route::middleware('appauth')->group(function () {
    Route::controller(\Config\UserController::class)->group(function () {
        Route::get('userprofile', 'profile');

        Route::post('lock', 'lock');
        Route::post('relogin', 'relogin');

        Route::post('logout', 'logout');
    });
    Route::controller(\Config\HomeController::class)->group(function () {
        Route::get('/home/columndef', 'Datadef');
        Route::get('/home/item', 'getItem');
        Route::get('/home/reports', 'getReport');
    });
    Route::controller(\Config\SecureController::class)->group(function () {
        Route::get('/access/pageaccess', 'getSecurityForm');
    });

});

Route::group(['prefix' => 'user', 'as' => 'master','middleware'=>'appauth'], function () {
    Route::controller(\Config\UserController::class)->group(function () {
        Route::get('/users', 'index');
        Route::get('/users/get', 'get');
        Route::get('/users/profile', 'profile');
        Route::post('/users/profile', 'post_profile');
        Route::post('/users/changepassword', 'changepassword');
        Route::get('/usersaccess', 'getObjAccess');

        Route::get('/item', 'getItem');
        Route::get('/itemaccess', 'getItemAccess');

        Route::post('/users', 'post');
        Route::post('/users/photo', 'uploadfoto');
        Route::post('/users/sign', 'uploadsign');
        Route::delete('/users', 'delete');
        Route::get('/users/level', 'user_level');
        Route::post('/userspwd', 'changepwd');
        Route::post('/usersaccess', 'save_security');
        Route::post('/savepoolaccess', 'SavePoolAccess');
        Route::delete('/deletepoolaccess', 'DeletePoolAccess');
        Route::get('/poolaccess', 'poolaccess');
        Route::post('/updatepool', 'updatepool');
    });

    Route::controller(\Config\HomeController::class)->group(function () {
        Route::get('/reports', 'setReport');
    });

    Route::controller(\Config\UserCredentialContoller::class)->group(function () {
        Route::get('/registration', 'registration');
        Route::post('/registration-finish', 'registration_finish');
        Route::get('/login', 'login');
        Route::post('/login-finish', 'login_finish');
    });
});

Route::group(['prefix' => 'dashboard', 'as' => 'dashboard'], function () {
    Route::controller(\Dashboard\Dashboard1Controller::class)->group(function () {
        Route::get('/ritase', 'ritase');
        Route::get('/tableritase', 'tableritase');
        Route::get('/tablepoint', 'tablepoint');
        Route::get('/point', 'point');
        Route::get('/service', 'service');
    });
});

Route::group(['prefix' => 'master', 'as' => 'master','middleware'=>'appauth'], function () {
    Route::controller(\Master\DriverController::class)->group(function () {
        Route::get('/driver', 'show');
        Route::get('/driver/get', 'get');
        Route::delete('/driver', 'destroy');
        Route::post('/driver', 'post');
        Route::get('/driver/getdriver', 'getDriver');
        Route::post('/driver/changepool', 'change_pool');
        Route::get('/driver/document', 'document');
        Route::post('/driver/photo', 'uploadfoto');
        Route::get('/driver/photo', 'downloadfoto');
        Route::delete('/driver/photo', 'deletefoto');
        Route::post('/driver/sign', 'uploadsign');
        Route::get('/driver/sign', 'downloadsign');
        Route::delete('/driver/sign', 'deletesign');
        Route::get('/driver/download', 'download');
        Route::get('/driver/open', 'open');
    });

    Route::controller(\Master\PoolController::class)->group(function () {
        Route::get('/pool', 'show');
        Route::get('/pool/get', 'get');
        Route::delete('/pool', 'destroy');
        Route::post('/pool', 'post');
        Route::get('/pool/getpool', 'getPool');
        Route::get('/pool/getpool2', 'getPool2');
        Route::get('/pool/project', 'getProject');
    });

    Route::controller(\Master\StationController::class)->group(function () {
        Route::get('/station', 'show');
        Route::get('/station/get', 'get');
        Route::delete('/station', 'destroy');
        Route::post('/station', 'post');
        Route::get('/station/getstation', 'getStation');
        Route::get('/station/getstation2', 'getStation2');
    });

    Route::controller(\Master\CheckpointController::class)->group(function () {
        Route::get('/checkpoint', 'show');
        Route::get('/checkpoint/get', 'get');
        Route::delete('/checkpoint', 'destroy');
        Route::post('/checkpoint', 'post');
        Route::get('/checkpoint/getcheckpoint', 'getCheckpoint');
        Route::get('/checkpoint/getcheckpoint2', 'getCheckpoint2');
    });

    Route::controller(\Master\BusrouteController::class)->group(function () {
        Route::get('/route', 'show');
        Route::get('/route/get', 'get');
        Route::delete('/route', 'destroy');
        Route::post('/route', 'post');
        Route::get('/route/getroute', 'getRoute');
        Route::get('/route/getroute2', 'getRoute2');
        Route::get('/route/info', 'route_info');
    });

    Route::controller(\Master\WarehouseController::class)->group(function () {
        Route::get('/warehouse', 'show');
        Route::get('/warehouse/get', 'get');
        Route::delete('/warehouse', 'destroy');
        Route::post('/warehouse', 'post');
        Route::get('/warehouse/getwarehouse', 'getWarehouse');
    });

    Route::controller(\Master\ItemController::class)->group(function () {
        Route::get('/item', 'show');
        Route::get('/item/get', 'get');
        Route::delete('/item', 'destroy');
        Route::post('/item', 'post');
        Route::get('/item/getitem', 'getItem');
        Route::get('/item/lookup', 'lookup');
        Route::get('/item/stock', 'stock');
        Route::get('/item/stockcard', 'stockcard');
        Route::get('/item/card_detail', 'card_detail');
        Route::get('/item/price', 'price');
        Route::get('/item/job/lookup', 'joblookup');
        Route::get('/item/query', 'query');
        Route::get('/item/report', 'report');
        Route::get('/item/stock/query', 'stock_query');
        Route::get('/item/stock/report', 'stock_report');
    });

    Route::controller(\Master\ItemjobController::class)->group(function () {
        Route::get('/itemjob', 'show');
        Route::get('/itemjob/get', 'get');
        Route::delete('/itemjob', 'destroy');
        Route::post('/itemjob', 'post');
        Route::get('/itemjob/getitem', 'getItem');
        Route::get('/itemjob/lookup', 'lookup');
    });

    Route::controller(\Master\ItemgroupController::class)->group(function () {
        Route::get('/itemgroup', 'show');
        Route::get('/itemgroup/get', 'get');
        Route::delete('/itemgroup', 'destroy');
        Route::post('/itemgroup', 'post');
        Route::get('/itemgroup/getitemgroup', 'getItemgroups');
    });

    Route::controller(\Master\SupplierController::class)->group(function () {
        Route::get('/supplier', 'show');
        Route::get('/supplier/get', 'get');
        Route::delete('/supplier', 'destroy');
        Route::post('/supplier', 'post');
        Route::get('/supplier/getsupplier', 'getSupplier');
        Route::get('/supplier/lookup', 'lookup');
        Route::get('/supplier/query', 'query');
        Route::get('/supplier/report', 'report');
        Route::get('/supplier/querysummary', 'query_summary');
        Route::get('/supplier/reportsummary', 'report_summary');
    });

    Route::controller(\Master\AccountController::class)->group(function () {
        Route::get('/account', 'show');
        Route::get('/account/get', 'get');
        Route::delete('/account', 'destroy');
        Route::post('/account', 'post');
        Route::get('/account/getaccount', 'getAccount');
        Route::get('/account/lookup', 'lookup');
        Route::get('/account/header', 'getAccountHeader');
        Route::get('/account/jurnaltype', 'Getjurnaltype');
        Route::post('/account/verified', 'Verified');
    });

    Route::controller(\Master\FiscalYearsController::class)->group(function () {
        Route::get('/fiscalyear', 'show');
        Route::post('/fiscalyear/analytic', 'GLAnalysis');
        Route::post('/fiscalyear/change-state', 'PeriodeSetup');
        Route::get('/fiscalyear/show-analytic', 'ShowAnalytic');
    });

    Route::controller(\Master\AccountSetupController::class)->group(function () {
        Route::get('/accountsetup', 'show');
        Route::get('/accountsetup/cashbank', 'cashlink');
        Route::delete('/accountsetup/deletecashbank', 'deletecashbank');
        Route::post('/accountsetup/postcashbank', 'postbank');
        Route::post('/accountsetup', 'post');
    });


    Route::controller(\Master\BankController::class)->group(function () {
        Route::get('/bank', 'show');
        Route::get('/bank/get', 'get');
        Route::delete('/bank', 'destroy');
        Route::post('/bank', 'post');
        Route::get('/bank/getbank', 'getBank');
    });

    Route::controller(\Master\VoucherController::class)->group(function () {
        Route::get('/voucher', 'show');
        Route::get('/voucher/get', 'get');
        Route::delete('/voucher', 'destroy');
        Route::post('/voucher', 'post');
        Route::get('/voucher/getvoucher', 'getVoucher');
        Route::get('/jurnal_type', 'getJurnaltype');
    });

    Route::controller(\Master\VehiclegroupController::class)->group(function () {
        Route::get('/vehiclegroup', 'show');
        Route::get('/vehiclegroup/get', 'get');
        Route::delete('/vehiclegroup', 'destroy');
        Route::post('/vehiclegroup', 'post');
        Route::get('/vehiclegroup/getvehiclegroup', 'getVehiclegroup');
    });

    Route::controller(\Master\VehicleController::class)->group(function () {
        Route::get('/vehicle', 'show');
        Route::get('/vehicle/get', 'get');
        Route::delete('/vehicle', 'destroy');
        Route::post('/vehicle', 'post');
        Route::get('/vehicle/getvehicle', 'getVehicle');
        Route::get('/vehicle/info', 'vehicle_info');
        Route::get('/vehicle/status', 'vehicle_status');
        Route::get('/vehicle/state', 'state');
        Route::get('/vehicle/more_detail', 'more_detail');
        Route::post('/vehicle/changepool', 'change_pool');
        Route::get('/vehicle/document', 'document');
        Route::get('/vehicle/storing', 'getstoring');
        Route::post('/vehicle/storing', 'poststoring');
        Route::get('/vehicle/gpsdevice','gps');
        Route::get('/vehicle/monitoring','monitoring');
        Route::get('/vehicle/gps_update','gps_update');

        Route::get('/point', 'point');
        Route::get('/point/get', 'get_point');
        Route::delete('/point', 'destroy_point');
        Route::post('/point', 'post_point');
    });

    Route::controller(\Master\JobsgroupController::class)->group(function () {
        Route::get('/jobsgroup', 'show');
        Route::get('/jobsgroup/get', 'get');
        Route::delete('/jobsgroup', 'destroy');
        Route::post('/jobsgroup', 'post');
        Route::get('/jobsgroup/list', 'getJobsgroup');
    });

    Route::controller(\Master\OthersController::class)->group(function () {
        Route::get('/others', 'show');
        Route::get('/others/get', 'get');
        Route::delete('/others', 'destroy');
        Route::post('/others', 'post');
        Route::get('/others/list', 'getOthers');
    });

    Route::controller(\Master\VariableCostController::class)->group(function () {
        Route::get('/variablecost', 'show');
        Route::get('/variablecost/get', 'get');
        Route::delete('/variablecost', 'destroy');
        Route::post('/variablecost', 'post');
        Route::get('/variablecost/list', 'getVariableCost');
    });
});


Route::group(['prefix' => 'inv', 'as' => 'inv','middleware'=>'appauth'], function () {
    Route::controller(\Inv\PurchaserequestController::class)->group(function () {
        Route::get('/purchaserequest', 'show');
        Route::get('/purchaserequest/get', 'get');
        Route::delete('/purchaserequest', 'destroy');
        Route::delete('/purchaserequest/unpost', 'unposting');
        Route::post('/purchaserequest', 'post');
        Route::get('/purchaserequest/print', 'print');
    });

    Route::controller(\Inv\IteminvoiceController::class)->group(function () {
        Route::get('/invoice', 'show');
        Route::get('/invoice/get', 'get');
        Route::delete('/invoice', 'destroy');
        Route::post('/invoice', 'post');
        Route::post('/invoice/upload', 'upload_document');
        Route::get('/invoice/download', 'download_document');
        Route::get('/invoice/print', 'print');
        Route::get('/invoice/order', 'order');
        Route::get('/invoice/detailorder', 'dtlorder');
        Route::get('/invoice/query', 'query');
        Route::get('/invoice/report', 'report');
    });

    Route::controller(\Inv\IteminvoiceReturController::class)->group(function () {
        Route::get('/invoice-retur', 'show');
        Route::get('/invoice-retur/get', 'get');
        Route::delete('/invoice-retur', 'destroy');
        Route::post('/invoice-retur', 'post');
        Route::post('/invoice-retur/upload', 'upload_document');
        Route::get('/invoice-retur/download', 'download_document');
        Route::get('/invoice-retur/print', 'print');
        Route::get('/invoice-retur/invoice', 'getInvoice');
        Route::get('/invoice-retur/detailinvoice', 'getdtlinvoice');
    });

    Route::controller(\Inv\GoodsoutController::class)->group(function () {
        Route::get('/goodsout', 'show');
        Route::get('/goodsout/get', 'get');
        Route::delete('/goodsout', 'destroy');
        Route::post('/goodsout', 'post');
        Route::get('/goodsout/print', 'print');
    });

    Route::controller(\Inv\StockRequestController::class)->group(function () {
        Route::get('/stockrequest', 'show');
        Route::get('/stockrequest/get', 'get');
        Route::delete('/stockrequest', 'destroy');
        Route::post('/stockrequest', 'post');
        Route::get('/stockrequest/print', 'print');
    });

    Route::controller(\Inv\StockTransferController::class)->group(function () {
        Route::get('/stocktransfer', 'show');
        Route::get('/stocktransfer/get', 'get');
        Route::delete('/stocktransfer', 'destroy');
        Route::post('/stocktransfer', 'post');
        Route::get('/stocktransfer/print', 'print');
        Route::get('/stocktransfer/request2', 'Request2');
        Route::get('/stocktransfer/dtlrequest', 'dtlRequest');
        Route::get('/stocktransfer/query', 'query');
        Route::get('/stocktransfer/report', 'report');
    });

    Route::controller(\Inv\StockReceiveController::class)->group(function () {
        Route::get('/stockreceive', 'show');
        Route::get('/stockreceive/get', 'get');
        Route::delete('/stockreceive', 'destroy');
        Route::post('/stockreceive', 'post');
        Route::get('/stockreceive/print', 'print');
        Route::get('/stockreceive/transfer', 'Transfer');
        Route::get('/stockreceive/transfer2', 'Transfer2');
        Route::get('/stockreceive/dtltransfer', 'dtlTransfer');
        Route::get('/stockreceive/query', 'query');
        Route::get('/stockreceive/report', 'report');
    });

    Route::controller(\Inv\ItemCorrectionController::class)->group(function () {
        Route::get('/stockcorrection', 'show');
        Route::get('/stockcorrection/get', 'get');
        Route::post('/stockcorrection', 'post');
        Route::get('/stockcorrection/print', 'print');
    });
});

Route::group(['prefix' => 'purchase', 'as' => 'purchase','middleware'=>'appauth'], function () {
    Route::controller(\Purchase\PurchaseOrderController::class)->group(function () {
        Route::get('/purchaseorder', 'show');
        Route::get('/purchaseorder/get', 'get');
        Route::delete('/purchaseorder', 'destroy');
        Route::delete('/purchaseorder/unpost', 'unposting');
        Route::post('/purchaseorder', 'post');
        Route::post('/purchaseorder/posting', 'posting');
        Route::get('/purchaseorder/print', 'print');
        Route::get('/purchaseorder/openrequest', 'request');
        Route::get('/purchaseorder/dtlrequest', 'dtlrequest');
        Route::post('/purchaseorder/upload', 'upload_document');
        Route::get('/purchaseorder/item_partner', 'itempartner');
        Route::get('/purchaseorder/history', 'history');
        Route::get('/purchaseorder/query', 'query');
        Route::get('/purchaseorder/report', 'report');
    });

    Route::controller(\Purchase\JobInvoiceController::class)->group(function () {
        Route::get('/jobinvoice', 'show');
        Route::get('/jobinvoice/get', 'get');
        Route::delete('/jobinvoice', 'destroy');
        Route::post('/jobinvoice', 'post');
        Route::get('/jobinvoice/print', 'print');
        Route::get('/jobinvoice/openrequest', 'request');
        Route::get('/jobinvoice/external', 'external');
        Route::get('/jobinvoice/material', 'material');
        Route::get('/jobinvoice/job', 'job');
        Route::get('/jobinvoice/query', 'query');
        Route::get('/jobinvoice/report', 'report');
        Route::get('/jobinvoice', 'show');
        Route::post('/jobinvoice/upload', 'upload_document');
        Route::get('/jobinvoice/download', 'download_document');
    });

    Route::controller(\Inv\IteminvoiceController::class)->group(function () {
        Route::get('/invoice/get', 'get');
    });
});

Route::group(['prefix' => 'ops', 'as' => 'ops','middleware'=>'appauth'], function () {
    Route::controller(\Ops\DrivergroupController::class)->group(function () {
        Route::get('/driver', 'show');
        Route::get('/driver/get', 'get');
        Route::delete('/driver', 'destroy');
        Route::post('/driver', 'post');
        Route::get('/driver/group', 'getgroup');
    });

    Route::controller(\Ops\SPJController::class)->group(function () {
        Route::get('/spj', 'show');
        Route::get('/spj/get', 'get');
        Route::delete('/spj', 'destroy');
        Route::post('/spj/cancel', 'cancel');
        Route::post('/spj', 'post');
        Route::get('/spj/open', 'open');
        Route::get('/spj/print', 'print');
        Route::get('/spj/passenger', 'passenger');
        Route::post('/spj/passenger', 'postpassenger');
        Route::get('/spj/expense', 'expenses');
        Route::get('/spj/query', 'query');
        Route::get('/spj/report', 'report');
        Route::get('/spj/query/route', 'query_route');
        Route::get('/spj/report/route', 'report_route');
        Route::get('/spj/query/vehicle', 'query_unit');
        Route::get('/spj/report/vehicle', 'report_unit');
        Route::get('/spj/unpaid', 'unpaid');
        Route::get('/spj/unpaidxls', 'unpaidxls');
        Route::get('/spj/intransit', 'intransit');
        Route::get('/spj/intransitxls', 'intransitxls');
        Route::get('/spj/passenger-query', 'passenger_query');
        Route::get('/spj/passenger-xls', 'passenger_xls');
    });

    Route::controller(\Ops\OperationCtrlController::class)->group(function () {
        Route::get('/checklist', 'show');
        Route::get('/checklist/get', 'get');
        Route::delete('/checklist', 'destroy');
        Route::post('/checklist', 'post');
        Route::get('/checklist/item', 'getitem');
        Route::get('/checklist/print', 'print');
    });

    Route::controller(\Ops\AccidentController::class)->group(function () {
        Route::get('/accident', 'show');
        Route::get('/accident/get', 'get');
        Route::delete('/accident', 'destroy');
        Route::post('/accident', 'post');
        Route::get('/accident/print', 'print');
    });

});

Route::group(['prefix' => 'service', 'as' => 'service','middleware'=>'appauth'], function () {
    Route::controller(\Service\ServiceController::class)->group(function () {
        Route::get('/service', 'show');
        Route::get('/service/get', 'get');
        Route::post('/service/cancel', 'destroy');
        Route::post('/service', 'post');
        Route::post('/service/reopen', 'reopen');
        Route::get('/service/active', 'getdocservice');
        Route::post('/servicereport', 'postreport');
        Route::get('/material', 'SummaryItem');
        Route::post('/material', 'PostItem');
        Route::get('/service/printreport', 'printreport');
        Route::get('/service/printworkorder', 'printworkorder');
        Route::get('/service/query', 'query');
        Route::get('/service/report', 'report');
        Route::get('/service/query2', 'query2');
        Route::get('/service/report2', 'report2');
    });

    Route::controller(\Service\GoodsRequestController::class)->group(function () {
        Route::get('/service/request', 'getrequest');
        Route::get('/request', 'show');
        Route::get('/request/get', 'get');
        Route::delete('/request', 'destroy');
        Route::post('/request', 'post');
        Route::post('/request/approved', 'approved');
        Route::get('/request/print', 'print');
        Route::get('/material/query', 'query');
        Route::get('/material/report', 'report');
    });

    Route::controller(\Service\GoodsRequestReturnController::class)->group(function () {
        Route::get('/request/return', 'show');
        Route::get('/request/return/get', 'get');
        Route::post('/request/return', 'post');
        Route::get('/request/return/print', 'print');
        Route::get('/request/return/wor', 'showWOR');
        Route::get('/request/return/wor-detail', 'showWORDetail');
    });

    Route::controller(\Service\ServiceExternalController::class)->group(function () {
        Route::get('/external', 'show');
        Route::get('/external/get', 'get');
        Route::delete('/external', 'destroy');
        Route::post('/external', 'post');
    });

    Route::controller(\Service\PeriodicController::class)->group(function () {
        Route::get('/periodic', 'show');
        Route::get('/periodic/get', 'get');
        Route::delete('/periodic', 'destroy');
        Route::post('/periodic', 'post');
    });

    Route::controller(\Service\DashboardController::class)->group(function () {
        Route::get('/dashboard/checking', 'checking');
        Route::get('/dashboard/service', 'service');
        Route::get('/dashboard/storing', 'storing');
        Route::delete('/dashboard/storing/delete', 'cancel_storing');
        Route::post('/dashboard/storing/approved', 'approved_storing');
        Route::get('/dashboard/state', 'state');
    });
});

Route::group(['prefix' => 'finance', 'as' => 'finance','middleware'=>'appauth'], function () {
    Route::controller(\Finance\SPJController::class)->group(function () {
        Route::get('/spj', 'show');
        Route::get('/spj/get', 'get');
        Route::get('/spj/getspjinfo', 'getspjinfo');
        Route::delete('/spj', 'destroy');
        Route::post('/spj', 'post');
        Route::get('/spj/print', 'print');
        Route::get('/spj/expense', 'expenses');
        Route::get('/spj/query', 'query');
        Route::get('/spj/report', 'report');
    });

    Route::controller(\Finance\SPJOthersController::class)->group(function () {
        Route::get('/spj/others/query', 'query');
        Route::get('/spj/others/report', 'report');
    });

    Route::controller(\Finance\CashOutController::class)->group(function () {
        Route::get('/expense', 'show');
        Route::get('/expense/get', 'get');
        Route::delete('/expense', 'destroy');
        Route::post('/expense', 'post');
        Route::get('/expense/print', 'print');
        Route::get('/expense/spjcost', 'Open');
        Route::get('/expense/variablecost', 'variablecost');
        Route::get('/expense/printspjcost', 'printspjcost');
        Route::get('/expense/query', 'query');
        Route::get('/expense/report', 'report');
    });

    Route::controller(\Finance\CashInController::class)->group(function () {
        Route::get('/receive', 'show');
        Route::get('/receive/get', 'get');
        Route::delete('/receive', 'destroy');
        Route::post('/receive', 'post');
        Route::get('/receive/print', 'print');
    });

    Route::controller(\Finance\OutPaymentController::class)->group(function () {
        Route::get('/outpayment', 'show');
        Route::get('/outpayment/get', 'get');
        Route::get('/outpayment/outstanding', 'getAP');
        Route::delete('/outpayment', 'destroy');
        Route::post('/outpayment', 'post');
        Route::get('/outpayment/print', 'print');
        Route::get('/outpayment/query', 'query');
        Route::get('/outpayment/report', 'report');
        Route::get('/outpayment/submission', 'submission');
        Route::get('/outpayment/submission_info', 'submission_info');
    });

    Route::controller(\Finance\PaymentSubmissionController::class)->group(function () {
        Route::get('/submission', 'show');
        Route::get('/submission/get', 'get');
        Route::get('/submission/outstanding', 'getAP');
        Route::delete('/submission', 'destroy');
        Route::post('/submission', 'post');
        Route::get('/submission/print', 'print');
        Route::post('/submission/posting', 'posting');
        Route::delete('/submission/unposting', 'unposting');
    });

    Route::controller(\Finance\InTransitController::class)->group(function () {
        Route::get('/intransit', 'show');
        Route::get('/intransit/get', 'get');
        Route::delete('/intransit', 'destroy');
        Route::post('/intransit', 'post');
        Route::get('/intransit/print', 'print');
        Route::get('/intransit/open', 'open');
        Route::get('/intransit/query', 'query');
        Route::get('/intransit/report', 'report');
    });

    Route::controller(\Finance\DepositController::class)->group(function () {
        Route::get('/deposit', 'show');
        Route::get('/deposit/get', 'get');
        Route::delete('/deposit', 'destroy');
        Route::post('/deposit', 'post');
        Route::get('/deposit/print', 'print');
        Route::get('/deposit/open', 'getOpen');
        Route::get('/deposit/query', 'query');
        Route::get('/deposit/report', 'report');
    });
    Route::controller(\Finance\CAController::class)->group(function () {
        Route::get('/customeraccount', 'show');
        Route::post('/customeraccount', 'post');
        Route::post('/customeraccount/all', 'post_all');
        Route::get('/customeraccount/print', 'print');
        Route::get('/customeraccount/download_document', 'download');
        Route::post('/customeraccount/upload_document', 'upload');
    });
});

Route::group(['prefix' => 'acc', 'as' => 'acc','middleware'=>'appauth'], function () {
    Route::controller(\Accounting\GLeadgerController::class)->group(function () {
        Route::get('/journal', 'show');
        Route::get('/journal/get', 'get');
        Route::delete('/journal', 'destroy');
        Route::get('/journal/print', 'print');
        Route::post('/journal', 'post');
        Route::get('/inqjournal', 'inquery');
        Route::get('/inqjournalxls', 'inqueryxls');

        Route::get('/printgl', 'Print');
    });

    Route::controller(\Master\AccountController::class)->group(function () {
        Route::get('/generalledger', 'GeneralLedger');
        Route::get('/generalledger/report', 'GeneralLedgerXLS');
        Route::get('/generalledger/report_gl', 'GeneralLedger_all');
        Route::get('/mutation', 'Mutation');
        Route::get('/mutation/report', 'MutationXLS');
        Route::get('/mutation/rl', 'MutationRL');
        Route::get('/mutation/rl/report', 'MutationRLXLS');
    });

    Route::controller(\Accounting\BegBalController::class)->group(function () {
        Route::get('/begining-balance', 'show');
        Route::post('/begining-balance', 'store');
    });

});

Route::group(['prefix' => 'mobile', 'as' => 'mobile'], function () {
    Route::controller(\User\UserController::class)->group(function () {
        Route::post('/login', 'login');
    });
    Route::controller(\Master\AccountController::class)->group(function () {
        Route::get('/checkpoint', 'show');
        Route::get('/routestart', 'go_route');
        Route::get('/routeback', 'back_route');
        Route::post('/checkpoint', 'savecheckpoint');
        Route::get('/lastunit', 'lastunit');
        Route::post('/confirmcheckpoint', 'confirmcheckpoint');
    });
});

Route::group(['prefix' => 'report', 'as' => 'report'], function () {
    Route::controller(\Accounting\ReportController::class)->group(function () {
        Route::get('/bc/standart', 'bs_standart');
        Route::get('/bc/period', 'bs_period');
        Route::get('/pl/standart', 'pl_standart');
        Route::get('/pl/period', 'pl_period');
    });
});

Route::group(['prefix' => 'document', 'as' => 'document','middleware'=>'appauth'], function () {
    Route::controller(\General\DocumentController::class)->group(function () {
        Route::post('/upload', 'upload');
        Route::get('/download', 'download');
        Route::delete('/remove', 'delete');
    });
});

Route::group(['prefix' => 'humans', 'as' => 'humans','middleware'=>'appauth'], function () {
    Route::controller(\Humans\MeetingController::class)->group(function () {
        Route::get('/meeting', 'show');
        Route::get('/meeting/get', 'get');
        Route::delete('/meeting', 'destroy');
        Route::post('/meeting', 'post');
        Route::get('/meeting/print', 'print');
    });

    Route::controller(\Humans\MachineController::class)->group(function () {
        Route::get('/machine', 'show');
        Route::get('/machine/get', 'get');
        Route::delete('/machine', 'destroy');
        Route::post('/machine', 'post');

        Route::get('/machine/user', 'show_user');
        Route::get('/machine/employee', 'show_employee');
        Route::post('/machine/reboot', 'reboot');
        Route::post('/machine/update_user', 'update_user');
        Route::post('/machine/send_user', 'send_user');
    });

    Route::controller(\Humans\AttendanceController::class)->group(function () {
        Route::get('/attendance', 'show');
        Route::get('/attendance/get', 'get');
    });
    Route::controller(\Humans\EmployeeController::class)->group(function () {
        Route::get('/employee', 'show');
        Route::get('/employee/get', 'get');
        Route::delete('/employee', 'destroy');
        Route::post('/employee', 'post');
        Route::get('/employee/print', 'print');
        Route::get('/employee/document', 'document');
        Route::post('/employee/photo', 'uploadfoto');
        Route::get('/employee/photo', 'downloadfoto');
        Route::delete('/employee/photo', 'deletefoto');
    });

    Route::controller(\Humans\DepartmentController::class)->group(function () {
        Route::get('/department', 'show');
        Route::get('/department/get', 'get');
        Route::delete('/department', 'destroy');
        Route::post('/department', 'post');
        Route::get('/department/list', 'getDepartment');
    });
});

Route::group(['prefix' => 'reports', 'as' => 'reports','middleware'=>'appauth'], function () {
    Route::controller(\Reports\GeneratorController::class)->group(function () {
        Route::get('/reportbuilder/getreport', 'getReport');
    });
});
