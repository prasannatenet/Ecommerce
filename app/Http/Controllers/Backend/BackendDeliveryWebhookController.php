<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackendDeliveryWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $partnerCode,
        DeliveryManager $manager
    ): array {
        $partner = DeliveryPartner::where('code', $partnerCode)
            ->where('is_active', true)
            ->first();

        if (! $partner) {
            Log::warning('Delivery webhook: unknown partner', ['code' => $partnerCode]);
            return ['ok' => false, 'message' => 'Partner not found'];
        }

        $signatureValid = $manager->verifyWebhook($partner, $request);

        if (! $signatureValid) {
            Log::warning('Delivery webhook: signature verification failed', [
                'partner_id' => $partner->id,
                'partner_code' => $partner->code,
            ]);

            return ['ok' => false, 'message' => 'Unauthorized'];
        }

        $payload = $request->json()->all();

        if (empty($payload)) {
            Log::warning('Delivery webhook: empty payload', [
                'partner_id' => $partner->id,
            ]);

            return ['ok' => false, 'message' => 'Empty payload'];
        }

        try {
            $handled = $manager->handleWebhook($partner, $payload);

            Log::info('Delivery webhook processed', [
                'partner_id' => $partner->id,
                'partner_code' => $partner->code,
                'shipments_handled' => $handled,
            ]);

            return ['ok' => true, 'handled' => $handled];
        } catch (\Throwable $e) {
            Log::error('Delivery webhook processing failed', [
                'partner_id' => $partner->id,
                'partner_code' => $partner->code,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Processing error'];
        }
    }
}