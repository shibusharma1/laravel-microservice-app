<?php

namespace App\Http\Controllers;

use App\Services\busy\BusyToDSAParty;
use App\Services\busy\BusyToDsaItem;
use App\Services\busy\BusyToDsaItemCategories;
use App\Services\busy\BusyToDsaTaxes;
use App\Services\busy\BusyToDsaUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BusySyncController extends Controller
{
    public function parties(Request $request, BusyToDSAParty $busyToDSAParty): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        try {
            $result = $busyToDSAParty->fetchParties((int) $validated['company_id']);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function products(Request $request, BusyToDsaItem $BusyToDsaItem): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        try {
            $result = $BusyToDsaItem->fetchProducts((int) $validated['company_id']);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function units(Request $request, BusyToDsaUnit $busyToDsaUnits): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);
        try {
            $result = $busyToDsaUnits->fetchUnits((int) $validated['company_id']);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function taxes(Request $request, BusyToDsaTaxes $busyToDsaTaxes): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        try {
            $result = $busyToDsaTaxes->fetchTaxes((int) $validated['company_id']);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function itemCategories(Request $request, BusyToDsaItemCategories $busyToDsaItemCategories): JsonResponse {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);
        try {
            $result = $busyToDsaItemCategories->fetchItemCategories(
                (int) $validated['company_id']
            );
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
