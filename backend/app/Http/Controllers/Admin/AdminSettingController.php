<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    /**
     * Ambil semua settings.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Setting::allAsArray(),
        ]);
    }

    /**
     * Update settings (batch key-value).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            Setting::set($key, $value);
        }

        return response()->json([
            'message' => 'Pengaturan berhasil disimpan.',
            'data'    => Setting::allAsArray(),
        ]);
    }

    /**
     * Upload logo aplikasi.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        $path = $request->file('logo')->store('logos', 'public');
        Setting::set('app_logo', $path);

        return response()->json([
            'message' => 'Logo berhasil diupload.',
            'data'    => ['app_logo' => asset('storage/' . $path)],
        ]);
    }

    /**
     * Generate sertifikat QZTray.
     * Sertifikat ini digunakan untuk import/menghubungkan browser dengan printer thermal KIOSK.
     */
    public function generateQzTrayCertificate(): JsonResponse
    {
        $certDir = storage_path('app/qztray');
        if (! is_dir($certDir)) {
            mkdir($certDir, 0755, true);
        }

        $keyPath  = $certDir . '/private-key.pem';
        $certPath = $certDir . '/digital-certificate.txt';

        // Generate RSA private key
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (! $privateKey) {
            return response()->json([
                'message' => 'Gagal generate private key. Pastikan OpenSSL tersedia di server.',
            ], 500);
        }

        // Generate self-signed certificate (100 tahun = 36500 hari)
        $dn = [
            'commonName'         => 'localhost',
            'organizationName'   => 'Pandai Antrian System',
            'countryName'        => 'ID',
        ];

        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha512']);
        $cert = openssl_csr_sign($csr, null, $privateKey, 36500, ['digest_alg' => 'sha512']);

        // Export
        openssl_pkey_export_to_file($privateKey, $keyPath);
        openssl_x509_export_to_file($cert, $certPath);

        // Simpan ke settings
        $privateKeyPem = '';
        openssl_pkey_export($privateKey, $privateKeyPem);
        Setting::set('qztray_private_key', $privateKeyPem);

        $certPem = '';
        openssl_x509_export($cert, $certPem);
        Setting::set('qztray_certificate', $certPem);

        return response()->json([
            'message'     => 'Sertifikat QZTray berhasil di-generate (masa berlaku 100 tahun).',
            'certificate' => $certPem,
        ]);
    }

    /**
     * Download sertifikat QZTray untuk import di browser/QZTray.
     */
    public function downloadQzTrayCertificate(): mixed
    {
        $cert = Setting::get('qztray_certificate');
        if (! $cert) {
            return response()->json(['message' => 'Sertifikat belum di-generate.'], 404);
        }

        return response($cert)
            ->header('Content-Type', 'application/x-x509-ca-cert')
            ->header('Content-Disposition', 'attachment; filename="digital-certificate.txt"');
    }
}
