<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {echo '404';});


// 聊天记录路由
Route::get('/chat', [App\Http\Controllers\ChatController::class, 'index'])->name('chat.index');
Route::post('/chat/verify-password', [App\Http\Controllers\ChatController::class, 'verifyPassword'])->name('chat.verify-password');
Route::get('/chat/groups', [App\Http\Controllers\ChatController::class, 'getGroups'])->name('chat.groups');
Route::get('/chat/messages', [App\Http\Controllers\ChatController::class, 'getMessages'])->name('chat.messages');

// 媒体文件路由
Route::get('/media', [App\Http\Controllers\MediaController::class, 'mediaList'])->name('chat.media');
Route::get('/media/list', [App\Http\Controllers\MediaController::class, 'getMediaList'])->name('chat.media.list');
