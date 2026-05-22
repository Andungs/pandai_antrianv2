<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — Pandai Antrian
|--------------------------------------------------------------------------
| Channel 'queue.display' dan 'queue.updates' menggunakan public channel
| sehingga Display TV dan halaman Tracking tidak memerlukan autentikasi.
|
| Jika di masa depan butuh private channel (misalnya untuk notifikasi
| personal ke user tertentu), tambahkan di sini.
*/

Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api']);

// Public channels tidak memerlukan authorization callback.
// Laravel Reverb akan otomatis handle public channels.
