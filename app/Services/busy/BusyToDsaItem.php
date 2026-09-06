<?php

namespace App\Services\busy;

use App\Services\BusyApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusyToDsaItem
{
    private string $productsTable = 'products';
    private string $unitsTable = 'unit_types';
    private array $unitCache = [];

    public function __construct(
        private BusyApiService $busyApiService
    ) {}

    public function fetchProducts(int $company_id): array
    {
        $startedAt = microtime(true);
        $result = [
            'success' => false,
            'company_id' => $company_id,
            'fetched' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'deactivated' => 0,
            'duration_ms' => 0,
            'error' => null,
        ];

        try {
            $response = $this->busyApiService->getItems();
            Log::info("Message", [$response]);
            if (!($response['success'] ?? false)) {
                throw new \RuntimeException(
                    $response['description'] ?? 'BUSY product fetch failed.'
                );
            }

            $items = $this->parseBusyProducts($response['body'] ?? '');
            $result['fetched'] = count($items);
            $sync = $this->createOrUpdateDsaProducts($items, $company_id);
            $result['inserted'] = $sync['inserted'];
            $result['updated'] = $sync['updated'];
            $result['skipped'] = $sync['skipped'];
            $result['deactivated'] = $this->deactivateMissingProducts($items, $company_id);
            $result['success'] = true;
            Log::channel('busy')->info('Item Pull Completed', [
                'company_id' => $company_id,
                'fetched' => $result['fetched'],
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'deactivated' => $result['deactivated'],
            ]);
            return $result;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::channel('busy')->error('BUSY Product Sync Failed', [
                'company_id' => $company_id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $result;
        } finally {
            $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        }
    }

    private function parseBusyProducts(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            Log::channel('busy')->error('Failed to parse BUSY product XML', [
                'body' => $body,
                'errors' => libxml_get_errors(),
            ]);
            libxml_clear_errors();
            return [];
        }
        $xml->registerXPathNamespace('z', '#RowsetSchema');
        $rows = $xml->xpath('//z:row') ?: [];
        $products = [];
        foreach ($rows as $row) {
            $attributes = $row->attributes();
            $masterCode = trim((string) ($attributes['Code'] ?? ''));
            $name = trim((string) ($attributes['Name'] ?? ''));
            $unit = trim((string) ($attributes['UnitName'] ?? $attributes['Unit'] ?? ''));
            $salePrice = $attributes['D2'] ?? 0;
            $status = ($attributes['DeactiveMaster'] ?? '') === 'True' ? 'Inactive' : 'Active';
            if ($masterCode === '' || $name === '') {
                continue;
            }

            $products[] = [
                'master_code' => $masterCode,
                'name' => $name,
                'unit' => $unit !== '' ? $unit : null,
                'sale_price' => (string) $salePrice,
                'status' => $status,
            ];
        }

        Log::channel('busy')->info('Parsed BUSY Products', [
            'count' => count($products),
            'products' => $products,
        ]);

        return $products;
    }

    private function createOrUpdateDsaProducts(array $items, int $company_id): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $busyProductId = trim((string) ($item['master_code'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            if ($busyProductId === '' || $name === '') {
                $skipped++;
                continue;
            }
            $unitCode = trim((string) ($item['unit'] ?? ''));
            $unitId = $unitCode !== '' ? $this->ensureUnitId($unitCode, $company_id) : null;
            $mrp = $this->parseNumeric($item['sale_price'] ?? null);
            $data = [
                'product_name' => $name,
                'product_code' => null,
                'category_id' => null,
                'brand' => null,
                'unit' => $unitId,
                'mrp' => $mrp,
                'details' => null,
                'short_desc' => null,
                'status' => 'Active',
                'updated_at' => now(),
            ];

            $existing = DB::table($this->productsTable)
                ->where('company_id', $company_id)
                ->where('busyproduct_id', $busyProductId)
                ->first();

            if ($existing) {
                DB::table($this->productsTable)
                    ->where('id', $existing->id)
                    ->update($data);

                $updated++;
            } else {
                DB::table($this->productsTable)->insert([
                    'company_id' => $company_id,
                    'busyproduct_id' => $busyProductId,
                    ...$data,
                    'created_at' => now(),
                ]);
                $inserted++;
            }
        }
        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function ensureUnitId(string $unitCode, int $company_id): ?int
    {
        $unit = $this->normalizeName($unitCode);
        if ($unit === '') {
            return null;
        }
        $key = mb_strtolower($unit);
        if (isset($this->unitCache[$company_id][$key])) {
            return (int) $this->unitCache[$company_id][$key];
        }
        $existing = DB::table($this->unitsTable)
            ->where('company_id', $company_id)
            ->where(function ($query) use ($key) {
                $query->whereRaw('LOWER(name) = ?', [$key])->orWhereRaw('LOWER(symbol) = ?', [$key]);
            })->first();

        if ($existing) {
            return $this->unitCache[$company_id][$key] = (int) $existing->id;
        }

        $id = DB::table($this->unitsTable)->insertGetId([
            'company_id' => $company_id,
            'name' => 'unit'.$unit,
            'symbol' => 'unit',
            'busyunit_id' => $unit,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->unitCache[$company_id][$key] = (int) $id;
    }

    private function deactivateMissingProducts(array $items, int $company_id): int
    {
        $busyProductIds = collect($items)
            ->pluck('master_code')
            ->filter()
            ->map(fn($id) => trim((string) $id))
            ->unique()
            ->values()
            ->toArray();

        if (empty($busyProductIds)) {
            return 0;
        }

        return DB::table($this->productsTable)
            ->where('company_id', $company_id)
            ->whereNotNull('busyproduct_id')
            ->whereNotIn('busyproduct_id', $busyProductIds)
            ->update([
                'status' => 'Inactive',
                'updated_at' => now(),
            ]);
    }

    private function normalizeName(?string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        return $name;
    }

    private function parseNumeric($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '', $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if ($value === '' || $value === '-' || $value === '.') {
            return null;
        }
        return (float) $value;
    }
}
