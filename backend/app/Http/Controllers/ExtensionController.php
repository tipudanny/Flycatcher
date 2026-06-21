<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ExtensionController extends Controller
{
    /**
     * Public config the browser extension reads (no auth needed — it's fetched
     * before the user connects). Admin-managed via the backoffice settings panel.
     */
    public function config(): JsonResponse
    {
        $s = Setting::map();

        return response()->json([
            'data' => [
                'brand'         => config('app.name', 'Flycatcher'),
                'enabled'       => (bool) $s['extension_enabled'],
                'notifications' => (bool) $s['extension_notifications'],
                'badge'         => (bool) $s['extension_badge'],
                'poll_interval' => (int) $s['extension_poll_interval'],
                'app_url'       => $s['app_url'],
            ],
        ]);
    }
}
