<?php

use App\Http\Controllers\TransferController;
use App\Http\Controllers\StationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/transfers', [TransferController::class, 'store']);

Route::get('/stations/{station_id}/summary', [StationController::class, 'summary']);
