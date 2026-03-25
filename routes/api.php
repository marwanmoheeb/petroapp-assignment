<?php

use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/transfers', [TransferController::class, 'store']);
