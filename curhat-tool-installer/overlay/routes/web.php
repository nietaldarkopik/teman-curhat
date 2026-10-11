<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/sync', [DashboardController::class, 'sync'])->name('sync');

Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
Route::post('/inbox', [InboxController::class, 'store'])->name('inbox.store');
Route::post('/inbox/bulk', [InboxController::class, 'bulk'])->name('inbox.bulk');
Route::get('/inbox/{curhat}', [InboxController::class, 'show'])->name('inbox.show');
Route::post('/inbox/{curhat}', [InboxController::class, 'update'])->name('inbox.update');
Route::post('/inbox/{curhat}/analyze', [InboxController::class, 'analyze'])->name('inbox.analyze');
Route::post('/inbox/{curhat}/reply', [InboxController::class, 'reply'])->name('inbox.reply');
Route::post('/inbox/{curhat}/tts', [InboxController::class, 'tts'])->name('inbox.tts');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
Route::post('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
Route::post('/projects/{project}/delete', [ProjectController::class, 'destroy'])->name('projects.destroy');
Route::post('/projects/{project}/items', [ProjectController::class, 'addItems'])->name('projects.items.add');
Route::post('/projects/{project}/items/{item}', [ProjectController::class, 'updateItem'])->name('projects.items.update');
Route::post('/projects/{project}/items/{item}/move/{dir}', [ProjectController::class, 'moveItem'])->name('projects.items.move');
Route::post('/projects/{project}/items/{item}/remove', [ProjectController::class, 'removeItem'])->name('projects.items.remove');
Route::post('/projects/{project}/items/{item}/generate', [ProjectController::class, 'generateItem'])->name('projects.items.generate');
Route::post('/projects/{project}/generate-frame', [ProjectController::class, 'generateFrame'])->name('projects.frame');
Route::get('/projects/{project}/script', [ProjectController::class, 'script'])->name('projects.script');
Route::get('/projects/{project}/screen', [ProjectController::class, 'screen'])->name('projects.screen');
Route::get('/projects/{project}/publish', [ProjectController::class, 'publish'])->name('projects.publish');
Route::post('/projects/{project}/video', [ProjectController::class, 'saveVideo'])->name('projects.video');
Route::post('/projects/{project}/meta/generate', [ProjectController::class, 'generateMeta'])->name('projects.meta.generate');
Route::post('/projects/{project}/meta', [ProjectController::class, 'saveMeta'])->name('projects.meta');
Route::post('/projects/{project}/thumbnail/generate', [ProjectController::class, 'generateThumbTexts'])->name('projects.thumb.generate');
Route::post('/projects/{project}/thumbnail', [ProjectController::class, 'saveThumbnail'])->name('projects.thumb.save');
Route::post('/projects/{project}/thumbnail/delete', [ProjectController::class, 'deleteThumbnail'])->name('projects.thumb.delete');
Route::get('/projects/{project}/thumbnail', [ProjectController::class, 'thumbnail'])->name('projects.thumb.show');
Route::post('/projects/{project}/schedule', [ProjectController::class, 'schedule'])->name('projects.schedule');

Route::get('/uploads', [PublicationController::class, 'index'])->name('uploads.index');
Route::post('/uploads/run', [PublicationController::class, 'runNow'])->name('uploads.run');
Route::post('/uploads/{publication}/retry', [PublicationController::class, 'retry'])->name('uploads.retry');
Route::post('/uploads/{publication}/delete', [PublicationController::class, 'destroy'])->name('uploads.destroy');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::get('/oauth/facebook', [SettingsController::class, 'facebookRedirect'])->name('oauth.facebook');
Route::get('/oauth/facebook/callback', [SettingsController::class, 'facebookCallback'])->name('oauth.facebook.callback');
Route::post('/settings/facebook/page', [SettingsController::class, 'facebookPage'])->name('settings.facebook.page');
Route::post('/settings/facebook/disconnect', [SettingsController::class, 'facebookDisconnect'])->name('settings.facebook.disconnect');
Route::get('/oauth/google', [SettingsController::class, 'googleRedirect'])->name('oauth.google');
Route::get('/oauth/google/callback', [SettingsController::class, 'googleCallback'])->name('oauth.google.callback');
Route::post('/settings/youtube/disconnect', [SettingsController::class, 'youtubeDisconnect'])->name('settings.youtube.disconnect');
