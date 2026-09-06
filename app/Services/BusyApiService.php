<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BusyApiService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.busy.base_url'), 'http://127.0.0.1:981');
        $this->username = config('services.busy.username', 'a');
        $this->password = config('services.busy.password', 'a');
    }

    public function request(array $headers): array
    {
        try {
            $headers['UserName'] = $this->username;
            $headers['Pwd'] = $this->password;

            Log::channel('busy')->info('BUSY Request', [
                'url' => $this->baseUrl,
                'headers' => $headers,
            ]);

            // $response = Http::withHeaders($headers)->get($this->baseUrl);
            $response = Http::withHeaders($headers)->get('http://127.0.0.1:981');

            // Log::info("Complete Response",[$response]);

            Log::channel('busy')->info('BUSY Response', [
                'status' => $response->status(),
                'result' => $response->header('Result'),
                'description' => $response->header('Description'),
            ]);

            return [
                'success' => $response->successful() && $response->header('Result') === 'T',
                'status' => $response->status(),
                'result' => $response->header('Result'),
                'description' => $response->header('Description'),
                'body' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::channel('busy')->error('BUSY Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'result' => 'F',
                'description' => $e->getMessage(),
                'body' => null,
            ];
        }
    }

    /**
     * Execute SQL Query (SC=1).
     */
    public function executeQuery(string $query): array
    {
        return $this->request([
            'SC' => 1,
            'Qry' => $query,
        ]);
    }

    /**
     * Create Master (SC=5).
     */
    public function createMaster(int $masterType, string $xml): array
    {
        return $this->request([
            'SC' => 5,
            'MasterType' => $masterType,
            'MasterXML' => $xml,
        ]);
    }

    /**
     * Create Voucher (SC=2).
     */
    public function createVoucher(int $voucherType, string $xml): array
    {
        return $this->request([
            'SC' => 2,
            'VchType' => $voucherType,
            'VchXML' => $xml,
        ]);
    }

    /**
     * Fetch Master XML (SC=9).
     */
    public function getMaster(int $masterCode): array
    {
        return $this->request([
            'SC' => 9,
            'MasterCode' => $masterCode,
        ]);
    }

    /**
     * Connector health check.
     */
    public function healthCheck(): bool
    {
        $response = $this->executeQuery("SELECT TOP 5 * FROM MASTER1");
        return $response['success'];
    }

    /**
     * Fetch BUSY customers.
     */
    public function getCustomers(): array
    {
        $query = "SELECT * FROM MASTER1 WHERE MASTERTYPE = 5 AND PARENTGRP = 0";
        return $this->executeQuery($query);
    }
    // public function getCustomers(): array
    // {
    //     $query = "SELECT * FROM MASTER1 WHERE MASTERTYPE = 2 AND PARENTGRP = 116";
    //     return $this->executeQuery($query);
    // }

    public function getTaxes(): array
    {
        $query = "SELECT * FROM MASTER1 WHERE MASTERTYPE = 25 AND PARENTGRP = 0";
        return $this->executeQuery($query);
    }

    public function getUnits(): array
    {
        $query = "SELECT * FROM MASTER1 WHERE MASTERTYPE = 8 AND PARENTGRP = 0";
        return $this->executeQuery($query);
    }

    /**
     * Find BUSY customer by name.
     */
    public function findCustomerByName(string $name): array
    {
        $name = str_replace("'", "''", trim($name));
        $query = "SELECT TOP 1 * FROM MASTER1 WHERE MASTERTYPE = 2 AND PARENTGRP = 116 AND NAME = '{$name}'";
        return $this->executeQuery($query);
    }
    /**
     * Fetch BUSY item categories.
     */
    public function getItemCategories(): array
    {
        $query = "SELECT * FROM MASTER1 WHERE MASTERTYPE = 5 AND PARENTGRP = 0";
        return $this->executeQuery($query);
    }

    /**
     * Fetch BUSY products/items.
     */
    public function getItems(): array
    {
        $query = "SELECT * FROM MASTER1 WHERE MASTERTYPE = 6 AND PARENTGRP = 401";
        return $this->executeQuery($query);
    }

    /**
     * Find BUSY product by name.
     */
    public function findItemByName(string $name): array
    {
        $name = str_replace("'", "''", trim($name));
        $query = "SELECT TOP 1 * FROM MASTER1 WHERE MASTERTYPE = 6 AND PARENTGRP = 401 AND NAME = '{$name}'";
        return $this->executeQuery($query);
    }

    /**
     * Find voucher by reference number.
     */
    public function findVoucherByRefNo(string $refNo, string $voucherTable = 'SALE_VOUCHER'): array
    {
        $refNo = str_replace("'", "''", trim($refNo));
        $query = "SELECT TOP 1 VOUCHERID,REFNO FROM {$voucherTable} WHERE REFNO = '{$refNo}'";
        return $this->executeQuery($query);
    }

    /**
     * Check whether a customer/ledger exists in BUSY.
     */
    public function ledgerExists(string $name): bool
    {
        $result = $this->findCustomerByName($name);
        if (!($result['success'] ?? false)) {
            return false;
        }

        return str_contains($result['body'] ?? '', '<NAME>') || str_contains($result['body'] ?? '', $name);
    }
}
