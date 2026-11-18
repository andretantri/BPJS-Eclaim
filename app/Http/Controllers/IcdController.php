<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Services\IcdValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IcdController extends Controller
{
    public function __construct(protected IcdValidationService $validationService) {}

    public function search10(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Claim::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->validationService->searchIcd10($validated['q'] ?? ''),
        ]);
    }

    public function search9(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Claim::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json([
            'data' => $this->validationService->searchIcd9cm($validated['q'] ?? ''),
        ]);
    }
}
