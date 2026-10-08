<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Net
{
    /**
     * HTTP client dengan sertifikat CA bawaan, supaya tidak kena
     * "cURL error 60" yang sering muncul di PHP WAMP.
     */
    public static function http(): PendingRequest
    {
        $ca = resource_path('cacert.pem');

        return Http::withOptions(is_file($ca) ? ['verify' => $ca] : []);
    }
}
