<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LaravelSecurityFeatureController;

Route::post('/laravel-security-feature/verify', [LaravelSecurityFeatureController::class, 'verify'])
    ->name('laravel-security-feature.verify');

Route::post('/laravel-security-feature/changeEmailAndSendOtp/{user_id}',[LaravelSecurityFeatureController::class, 'changeEmailAndSendOtp'])
    ->name('laravel-security-feature.changeEmailAndSendOtp');

Route::post('/laravel-security-features/verifyEmailOnly/{user_id}',[LaravelSecurityFeatureController::class,'verifyEmailOnlyForUser'])
    ->name('laravel-security-feature.verifyEmailOnly');

