<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::post('/flip/{playerToken}', [ApiController::class, 'flip'])->name('api.flip');
