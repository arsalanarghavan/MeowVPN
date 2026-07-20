<?php

namespace App\Modules\Rial\Http;

use App\Http\Controllers\Controller;
use App\Modules\Rial\Services\AqayepardakhtPaymentService;
use App\Modules\Rial\Services\ZarinpalPaymentService;
use App\Modules\Rial\Services\ZibalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CallbackController extends Controller
{
    public function zarinpal(Request $request, ZarinpalPaymentService $service, string $secret): Response
    {
        if (! svp_modules()->isEnabled('rial')) {
            return response('module_disabled', 503);
        }

        return $service->handleCallback($request, $secret);
    }

    public function zibal(Request $request, ZibalPaymentService $service, string $secret): Response
    {
        if (! svp_modules()->isEnabled('rial')) {
            return response('module_disabled', 503);
        }

        return $service->handleCallback($request, $secret);
    }

    public function aqayepardakht(Request $request, AqayepardakhtPaymentService $service, string $secret): Response
    {
        if (! svp_modules()->isEnabled('rial')) {
            return response('module_disabled', 503);
        }

        return $service->handleCallback($request, $secret);
    }
}
