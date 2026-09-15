<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\DebtReview\DebtReviewGateException;
use App\Application\Ops\DebtReview\DebtReviewReleaseGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DebtReviewDataController extends Controller
{
    public function index(DebtReviewReleaseGate $gate): JsonResponse
    {
        return $this->guard($gate);
    }

    public function show(DebtReviewReleaseGate $gate): JsonResponse
    {
        return $this->guard($gate);
    }

    public function coverage(DebtReviewReleaseGate $gate): JsonResponse
    {
        return $this->guard($gate);
    }

    private function guard(DebtReviewReleaseGate $gate): JsonResponse
    {
        try {
            $gate->assertEnabled();
        } catch (DebtReviewGateException $exception) {
            return response()->json([
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ], $exception->status);
        }

        return response()->json([
            'code' => 'DEBT_REVIEW_NOT_IMPLEMENTED',
            'message' => 'Debt Review data is gated for a future approved release.',
        ], 501);
    }
}
