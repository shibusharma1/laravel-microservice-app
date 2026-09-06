<?php

namespace App\Services\busy;

use App\Services\BusyApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusyToDsaItemCategories
{
    private string $categoryTable = 'item_categories';
    public function __construct(
        private BusyApiService $busyApiService
    ) {}

    public function fetchItemCategories(int $company_id): array
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
            $response = $this->busyApiService->getItemCategories();
            if (!($response['success'] ?? false)) {
                throw new \RuntimeException(
                    $response['description'] ?? 'BUSY item category fetch failed.'
                );
            }
            $categories = $this->parseBusyItemCategories($response['body'] ?? '');
            $result['fetched'] = count($categories);
            $sync = $this->createOrUpdateDsaItemCategories($categories, $company_id);
            $result['inserted'] = $sync['inserted'];
            $result['updated'] = $sync['updated'];
            $result['skipped'] = $sync['skipped'];

            $result['deactivated'] = $this->deactivateMissingItemCategories($categories, $company_id);
            $result['success'] = true;
            Log::channel('busy')->info('Item Category Pull Completed', [
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
            Log::channel('busy')->error('BUSY Item Category Sync Failed', [
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

    private function parseBusyItemCategories(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            Log::channel('busy')->error(
                'Failed to parse BUSY item category XML',
                [
                    'errors' => libxml_get_errors(),
                ]
            );
            libxml_clear_errors();
            return [];
        }

        $xml->registerXPathNamespace('z','#RowsetSchema');
        $rows = $xml->xpath('//z:row') ?: [];
        $categories = [];
        foreach ($rows as $row) {
            $attributes = $row->attributes();
            $masterCode = trim((string) ($attributes['Code'] ?? ''));
            $name = trim((string) ($attributes['Name'] ?? ''));
            $masterType = trim((string) ($attributes['MasterType'] ?? ''));
            $parentGroup = trim((string) ($attributes['ParentGrp'] ?? ''));
            $deactive = trim((string) ($attributes['DeactiveMaster'] ?? 'False'));
            if ($masterCode === '' || $name === '') {
                continue;
            }
            if ($masterType !== '5') {
                continue;
            }
            $categories[] = [
                'master_code' => $masterCode,
                'name' => $name,
                'parent_group' => $parentGroup,
                'status' => $deactive === 'True' ? 'Inactive' : 'Active',
            ];
        }

        Log::channel('busy')->info('Parsed BUSY Item Categories', [
            'count' => count($categories),
            'categories' => $categories,
        ]);
        return $categories;
    }

    private function createOrUpdateDsaItemCategories(array $categories, int $company_id): array {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($categories as $category) {
            $busyCategoryId = trim(
                (string) ($category['master_code'] ?? '')
            );

            $name = trim((string) ($category['name'] ?? ''));
            if ($busyCategoryId === '' || $name === '') {
                $skipped++;
                continue;
            }

            $data = [
                'name' => $name,
                'updated_at' => now(),
            ];

            $existing = DB::table($this->categoryTable)
                ->where('company_id', $company_id)
                ->where('busyitemcategory_id', $busyCategoryId)
                ->first();
            if ($existing) {
                DB::table($this->categoryTable)
                    ->where('id', $existing->id)
                    ->update($data);

                $updated++;
            } else {
                DB::table($this->categoryTable)->insert([
                    'company_id' => $company_id,
                    'busyitemcategory_id' => $busyCategoryId,
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

    private function deactivateMissingItemCategories(array $categories,int $company_id): int {
        return 0;
    }
}
