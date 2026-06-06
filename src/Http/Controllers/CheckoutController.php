<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout\Http\Controllers;

use FelixMuhoro\MpesaCheckout\CheckoutManager;
use FelixMuhoro\MpesaCheckout\CheckoutStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutManager $manager,
    ) {}

    /**
     * POST /mpesa-checkout/initiate
     * Initiates an STK Push and returns the session.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount'       => ['required', 'numeric', 'min:1'],
            'phone'        => ['required', 'string', 'min:9'],
            'reference'    => ['required', 'string', 'max:12'],
            'description'  => ['required', 'string', 'max:13'],
            'callback_url' => ['nullable', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data    = $validator->validated();
        $session = $this->manager->initiate(
            amount:      (float) $data['amount'],
            phone:       $data['phone'],
            reference:   $data['reference'],
            description: $data['description'],
            callbackUrl: $data['callback_url'] ?? null,
        );

        return response()->json([
            'success'    => $session->status !== CheckoutStatus::Failed,
            'session_id' => $session->sessionId,
            'status'     => $session->status->value,
            'message'    => $session->status->label(),
            'expires_at' => $session->expiresAt->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * GET /mpesa-checkout/poll/{sessionId}
     * Called by the JS poller every 3 s.
     */
    public function poll(Request $request, string $sessionId): JsonResponse
    {
        $status  = $this->manager->poll($sessionId);
        $session = $this->manager->find($sessionId);

        return response()->json([
            'status'        => $status->value,
            'label'         => $status->label(),
            'is_terminal'   => $status->isTerminal(),
            'is_success'    => $status->isSuccess(),
            'receipt'       => $session?->mpesaReceiptNumber,
            'failure_reason'=> $session?->failureReason,
        ]);
    }

    /**
     * POST /mpesa-checkout/webhook
     * M-Pesa STK Push callback / confirmation URL.
     */
    public function webhook(Request $request): Response
    {
        $payload = $request->all();

        Log::info('[MpesaCheckout] Webhook received', ['payload' => $payload]);

        try {
            // Safaricom STK Push callback structure
            $body = $payload['Body']['stkCallback'] ?? null;

            if ($body === null) {
                return response('', 200);
            }

            $checkoutRequestId = $body['CheckoutRequestID'] ?? null;
            $resultCode        = (int) ($body['ResultCode'] ?? -1);
            $resultDesc        = $body['ResultDesc'] ?? 'Unknown';

            if ($checkoutRequestId === null) {
                return response('', 200);
            }

            if ($resultCode === 0) {
                // Extract receipt number from CallbackMetadata
                $items   = $body['CallbackMetadata']['Item'] ?? [];
                $receipt = '';

                foreach ($items as $item) {
                    if ($item['Name'] === 'MpesaReceiptNumber') {
                        $receipt = $item['Value'] ?? '';
                        break;
                    }
                }

                $this->manager->recordSuccess($checkoutRequestId, $receipt);
            } else {
                $this->manager->recordFailure($checkoutRequestId, $resultDesc);
            }
        } catch (\Throwable $e) {
            Log::error('[MpesaCheckout] Webhook processing error', [
                'error' => $e->getMessage(),
            ]);
        }

        return response('', 200);
    }

    /**
     * DELETE /mpesa-checkout/{sessionId}
     */
    public function cancel(Request $request, string $sessionId): JsonResponse
    {
        $this->manager->cancel($sessionId);

        return response()->json(['success' => true, 'message' => 'Session cancelled']);
    }

    /**
     * GET /mpesa-checkout/pay
     * Standalone checkout page (redirect flow).
     */
    public function page(Request $request): mixed
    {
        $validated = $request->validate([
            'amount'      => ['required', 'numeric', 'min:1'],
            'phone'       => ['nullable', 'string'],
            'reference'   => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        return view('mpesa-checkout::checkout', [
            'amount'      => $validated['amount'],
            'phone'       => $validated['phone'] ?? '',
            'reference'   => $validated['reference'],
            'description' => $validated['description'] ?? config('app.name') . ' Payment',
            'config'      => config('mpesa-checkout'),
        ]);
    }
}
