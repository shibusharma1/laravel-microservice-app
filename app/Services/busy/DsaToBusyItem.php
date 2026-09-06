<?php

namespace App\Services\busy;

use App\Services\BusyApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DsaToBusyProduct
{
    private const MASTER_TYPE = 6;
    private const PARENT_GROUP = 'Stock-in-Hand';
    private const TAX_TYPE = 'Others';
    private const SALE_PRICE_LEVEL = '@';
    private const PURCHASE_PRICE_LEVEL = '@';

    public function __construct(
        private BusyApiService $busyApiService
    ) {
    }

    public function pushProduct(array $product): array
    {
        $startedAt = microtime(true);

        $result = [
            'success' => false,
            'product_id' => $product['id'] ?? null,
            'product_name' => $product['product_name'] ?? null,
            'status' => null,
            'result' => null,
            'description' => null,
            'body' => null,
            'duration_ms' => 0,
            'error' => null,
        ];

        try {
            $this->validateProduct($product);

            $xml = $this->buildProductXml($product);

            Log::channel('busy')->info('BUSY Product Request', [
                'product_id' => $product['id'] ?? null,
                'product_name' => $product['product_name'] ?? null,
                'xml_length' => strlen($xml),
            ]);

            $response = $this->busyApiService->createMaster(
                self::MASTER_TYPE,
                $xml
            );

            $result['status'] = $response['status'] ?? null;
            $result['result'] = $response['result'] ?? null;
            $result['description'] = $response['description'] ?? null;
            $result['body'] = $response['body'] ?? null;
            $result['success'] = $response['success'] ?? false;

            Log::channel('busy')->info('BUSY Product Response', [
                'product_id' => $product['id'] ?? null,
                'product_name' => $product['product_name'] ?? null,
                'success' => $result['success'],
                'status' => $result['status'],
                'result' => $result['result'],
                'description' => $result['description'],
            ]);

            return $result;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();

            Log::channel('busy')->error('BUSY Product Push Failed', [
                'product_id' => $product['id'] ?? null,
                'product_name' => $product['product_name'] ?? null,
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

    private function validateProduct(array $product): void
    {
        if (empty($product['id'])) {
            throw new \InvalidArgumentException('Product ID is required.');
        }

        if (empty(trim((string) ($product['product_name'] ?? '')))) {
            throw new \InvalidArgumentException('Product name is required.');
        }

        if (empty(trim((string) ($product['unit_name'] ?? '')))) {
            throw new \InvalidArgumentException('Product unit is required.');
        }
    }

    private function buildProductXml(array $product): string
    {
        $name = $this->escapeXml(trim((string) $product['product_name']));
        $alias = $this->escapeXml(trim((string) ($product['product_code'] ?? $product['product_name'])));
        $unit = $this->escapeXml(trim((string) $product['unit_name']));
        $mrp = $this->formatNumber($product['mrp'] ?? 0);
        $salePrice = $this->formatNumber($product['mrp'] ?? 0);
        $purchasePrice = $this->formatNumber($product['mrp'] ?? 0);
        $parentGroup = self::PARENT_GROUP;
        $taxType = self::TAX_TYPE;
        $salePriceLevel = self::SALE_PRICE_LEVEL;
        $purchasePriceLevel = self::PURCHASE_PRICE_LEVEL;

        return <<<XML
                <Item>
                    <Name>{$name}</Name>
                    <Alias>{$alias}</Alias>
                    <PrintName>{$name}</PrintName>
                    <ParentGroup>{$parentGroup}</ParentGroup>
                    <UnitName>{$unit}</UnitName>
                    <MRP>{$mrp}</MRP>
                    <SalePrice>{$salePrice}</SalePrice>
                    <PurchasePrice>{$purchasePrice}</PurchasePrice>
                    <PriceLevel>{$salePriceLevel}</PriceLevel>
                    <PriceLevelForPurc>{$purchasePriceLevel}</PriceLevelForPurc>
                    <TaxType>{$taxType}</TaxType>
                </Item>
                XML;
    }

    private function formatNumber($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $value = str_replace(',', '', (string) $value);

        if (!is_numeric($value)) {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''),'0'),'.');
    }

    private function escapeXml($value): string
    {
        return htmlspecialchars((string) $value,ENT_XML1 | ENT_QUOTES,'UTF-8');
    }
}