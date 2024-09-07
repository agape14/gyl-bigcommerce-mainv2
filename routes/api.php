<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BigcommerceController;


Route::get('/uploadfilecsv', [BigcommerceController::class, 'uploadFileCsv']);
Route::get('/descargafileexcel', [BigcommerceController::class, 'downloadFileExcel']);
Route::get('/process-excel', [BigcommerceController::class, 'processExcelCsv']);
Route::post('/processbigcommerce', [BigcommerceController::class, 'processBigcommerce']);

Route::get('/downloadFileCsv', [BigcommerceController::class, 'downloadFileCsv']);
Route::get('/createProducts', [BigcommerceController::class, 'createProductInCrm']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
