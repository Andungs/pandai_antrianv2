<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QzTrayController extends Controller
{
    /**
     * Mengembalikan public digital certificate untuk QZTray.
     */
    public function certificate(): \Illuminate\Http\Response|JsonResponse
    {
        $certContent = Setting::get('qztray_certificate');

        if (!$certContent) {
            return response()->json(['message' => 'Sertifikat tidak ditemukan. Silakan generate di Pengaturan.'], 404);
        }

        return response($certContent)->header('Content-Type', 'text/plain');
    }

    /**
     * Menerima request string dari QZTray, menandatanganinya menggunakan
     * private key (SHA512), dan mengembalikan signature (base64).
     */
    public function sign(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $message = $request->input('request');
        
        if (!$message) {
            return response()->json(['message' => 'Parameter request dibutuhkan.'], 400);
        }

        $privateKey = Setting::get('qztray_private_key');

        if (!$privateKey) {
            return response()->json(['message' => 'Private key tidak ditemukan. Silakan generate di Pengaturan.'], 404);
        }

        $signature = '';
        $success = openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA512);

        if (!$success) {
            return response()->json(['message' => 'Gagal menandatangani request.'], 500);
        }

        return response(base64_encode($signature))->header('Content-Type', 'text/plain');
    }
}
