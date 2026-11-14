<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    public function captureQr(Request $request): Response
    {
        $url = route('public.capture');

        $renderer = new ImageRenderer(
            new RendererStyle(320, 2),
            new SvgImageBackEnd()
        );
        $svg = (new Writer($renderer))->writeString($url);

        $headers = ['Content-Type' => 'image/svg+xml'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="qrcode-captacao-aguia-das-promos.svg"';
        }

        return response($svg, 200, $headers);
    }
}
