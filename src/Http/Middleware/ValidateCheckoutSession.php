<?php

declare(strict_types=1);

namespace FelixMuhoro\MpesaCheckout\Http\Middleware;

use Closure;
use FelixMuhoro\MpesaCheckout\CheckoutManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateCheckoutSession
{
    public function __construct(
        private readonly CheckoutManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sessionId = $request->route('sessionId') ?? $request->input('session_id');

        if (empty($sessionId)) {
            return response()->json(['error' => 'Session ID required'], 400);
        }

        $session = $this->manager->find($sessionId);

        if ($session === null) {
            return response()->json(['error' => 'Checkout session not found'], 404);
        }

        if ($session->isExpired() && ! $session->status->isTerminal()) {
            return response()->json(['error' => 'Checkout session expired'], 410);
        }

        $request->attributes->set('checkout_session', $session);

        return $next($request);
    }
}
