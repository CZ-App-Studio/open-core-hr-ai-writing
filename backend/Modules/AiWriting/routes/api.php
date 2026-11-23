<?php

use Illuminate\Support\Facades\Route;
use Modules\AiWriting\Http\Controllers\Api\WritingController;

Route::prefix('v1/ai-writing')->name('ai-writing.')->middleware(['auth:web,api', 'throttle:20,1'])->group(function (): void {
    Route::post('/', [WritingController::class, 'store'])->name('store');
});
