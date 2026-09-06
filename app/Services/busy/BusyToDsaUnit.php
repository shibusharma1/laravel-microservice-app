<?php

namespace App\Services\busy;

use App\Services\BusyApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusyToDsaUnit
{
    private string $unitsTable = 'unit_types';
    public function __construct(
        private BusyApiService $busyApiService
    ) {}

    public function fetchUnits(int $company_id): array
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
            $response = $this->busyApiService->getUnits();
            Log::channel('busy')->info('Unit data received', [
                'response' => $response,
            ]);

            if (!($response['success'] ?? false)) {
                throw new \RuntimeException($response['description'] ?? 'BUSY unit fetch failed.');
            }

            $units = $this->parseBusyUnits($response['body'] ?? '');
            $result['fetched'] = count($units);
            $sync = $this->createOrUpdateDsaUnits($units, $company_id);
            $result['inserted'] = $sync['inserted'];
            $result['updated'] = $sync['updated'];
            $result['skipped'] = $sync['skipped'];

            $result['deactivated'] = $this->deactivateMissingUnits(
                $units,
                $company_id
            );

            $result['success'] = true;

            Log::channel('busy')->info('Unit Pull Completed', [
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

            Log::channel('busy')->error('BUSY Unit Sync Failed', [
                'company_id' => $company_id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $result;
        } finally {

            $result['duration_ms'] = (int) round(
                (microtime(true) - $startedAt) * 1000
            );
        }
    }

    private function parseBusyUnits(string $body): array
    {
        $body = trim($body);

        if ($body === '') {
            return [];
        }

        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($body);

        if ($xml === false) {
            Log::channel('busy')->error('Failed to parse BUSY unit XML', [
                'body' => $body,
                'errors' => libxml_get_errors(),
            ]);

            libxml_clear_errors();

            return [];
        }

        $xml->registerXPathNamespace('z', '#RowsetSchema');

        $rows = $xml->xpath('//z:row') ?: [];

        $units = [];

        foreach ($rows as $row) {

            $attributes = $row->attributes();

            $masterCode = trim(
                (string) ($attributes['Code'] ?? '')
            );

            $name = trim(
                (string) ($attributes['Name'] ?? '')
            );

            $symbol = trim(
                (string) ($attributes['Symbol'] ?? $name)
            );

            $status = ($attributes['DeactiveMaster'] ?? '') === 'True'
                ? 'Inactive'
                : 'Active';

            if ($masterCode === '' || $name === '') {
                continue;
            }

            $units[] = [
                'master_code' => $masterCode,
                'name' => $name,
                'symbol' => $symbol,
                'status' => $status,
            ];
        }

        Log::channel('busy')->info('Parsed BUSY Units', [
            'count' => count($units),
            'units' => $units,
        ]);

        return $units;
    }

    private function createOrUpdateDsaUnits(array $units,int $company_id): array {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($units as $unit) {
            $busyUnitId = trim((string) ($unit['master_code'] ?? ''));
            $name = trim((string) ($unit['name'] ?? ''));
            if ($busyUnitId === '' || $name === '') {
                $skipped++;
                continue;
            }

            $data = [
                'name' => $name,
                'symbol' => $unit['symbol'] ?? $name,
                'status' => $unit['status'] ?? 'Active',
                'updated_at' => now(),
            ];

            $existing = DB::table($this->unitsTable)
                ->where('company_id', $company_id)
                ->where('busyunit_id', $busyUnitId)
                ->first();

            if ($existing) {
                DB::table($this->unitsTable)
                    ->where('id', $existing->id)
                    ->update($data);

                $updated++;
            } else {
                DB::table($this->unitsTable)->insert([
                    'company_id' => $company_id,
                    'busyunit_id' => $busyUnitId,
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

    private function deactivateMissingUnits(array $units, int $company_id): int {
        $busyUnitIds = collect($units)
            ->pluck('master_code')
            ->filter()
            ->map(fn($id) => trim((string) $id))
            ->unique()
            ->values()
            ->toArray();

        if (empty($busyUnitIds)) {
            return 0;
        }

        return DB::table($this->unitsTable)
            ->where('company_id', $company_id)
            ->whereNotNull('busyunit_id')
            ->whereNotIn('busyunit_id', $busyUnitIds)
            ->update([
                'status' => 'Inactive',
                'updated_at' => now(),
            ]);
    }
}
