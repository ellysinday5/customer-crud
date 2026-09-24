<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('customers', CustomerController::class);