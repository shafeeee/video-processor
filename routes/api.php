<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\VideoProcessController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// we can use sanctum, but i didn't configure it
// Route::middleware('auth:sanctum')->group(function () {
//     // Protected "videoProcess" route
//     Route::post('videoProcess', [VideoProcessController::class, 'index']);
// });

Route::post('videoProcess', [VideoProcessController::class, 'index']);
Route::post('getJobStatus', [VideoProcessController::class, 'getJobStatus']);