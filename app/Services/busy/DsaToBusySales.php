<?php

namespace App\Services\busy;

use App\Services\BusyApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DsaToBusySales
{
    private const VOUCHER_TYPE = 9;
    private const VOUCHER_SERIES = 'Main';
    private const STORE_NAME = 'Main Store';
    private const CURRENCY = 'Rs.';
    private const INPUT_TYPE = 1;
    private const AUTO_VOUCHER_NO = 6;
    private const TAX_CATEGORY = 'GST 18%';
    private const DISCOUNT_STRUCTURE = 'Simple Discount, % of Price';

    public function __construct(
        private BusyApiService $busyApiService
    ) {}

    public function pushSale(array $order): array
    {
        $startedAt = microtime(true);

        $result = [
            'success' => false,
            'order_id' => $order['id'] ?? null,
            'order_no' => $order['order_no'] ?? null,
            'status' => null,
            'result' => null,
            'description' => null,
            'body' => null,
            'duration_ms' => 0,
            'error' => null,
        ];

        try {
            $this->validateOrder($order);

            $xml = $this->buildSaleXml($order);

            Log::channel('busy')->info('BUSY Sales Request', [
                'order_id' => $order['id'] ?? null,
                'order_no' => $order['order_no'] ?? null,
                'xml_length' => strlen($xml),
            ]);

            $response = $this->busyApiService->createVoucher(
                self::VOUCHER_TYPE,
                $xml
            );

            $result['status'] = $response['status'] ?? null;
            $result['result'] = $response['result'] ?? null;
            $result['description'] = $response['description'] ?? null;
            $result['body'] = $response['body'] ?? null;
            $result['success'] = $response['success'] ?? false;

            Log::channel('busy')->info('BUSY Sales Response', [
                'order_id' => $order['id'] ?? null,
                'order_no' => $order['order_no'] ?? null,
                'success' => $result['success'],
                'status' => $result['status'],
                'result' => $result['result'],
                'description' => $result['description'],
            ]);

            return $result;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();

            Log::channel('busy')->error('BUSY Sales Push Failed', [
                'order_id' => $order['id'] ?? null,
                'order_no' => $order['order_no'] ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $result;
        } finally {
            $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        }
    }

    private function validateOrder(array $order): void
    {
        if (empty($order['order_no'])) {
            throw new \InvalidArgumentException('Order number is required.');
        }

        if (empty($order['order_date'])) {
            throw new \InvalidArgumentException('Order date is required.');
        }

        if (empty($order['client_name'])) {
            throw new \InvalidArgumentException('Client name is required.');
        }

        if (!isset($order['orderproducts']) || !is_array($order['orderproducts']) || empty($order['orderproducts'])) {
            throw new \InvalidArgumentException('At least one order product is required.');
        }
    }

    private function buildSaleXml(array $order): string
    {
        $date = date('d-m-Y', strtotime($order['order_date']));
        $orderNo = $this->escapeXml($order['order_no']);
        $clientName = $this->escapeXml($order['client_name']);
        $itemEntries = $this->buildItemEntries($order['orderproducts'], $date, $orderNo);

        return <<<XML
        <Sale>
            <VchSeriesName>{self::VOUCHER_SERIES}</VchSeriesName>
            <Date>{$date}</Date>
            <VchType>{self::VOUCHER_TYPE}</VchType>
            <StockUpdationDate>{$date}</StockUpdationDate>
            <VchNo>{$orderNo}</VchNo>
            <AutoVchNo>{self::AUTO_VOUCHER_NO}</AutoVchNo>
            <STPTName>VAT/13%</STPTName>
            <MasterName1>{$clientName}</MasterName1>
            <MasterName2>{self::STORE_NAME}</MasterName2>
            <TranCurName>{self::CURRENCY}</TranCurName>
            <InputType>{self::INPUT_TYPE}</InputType>

            <BillingDetails>
                <PartyName>{$clientName}</PartyName>
                <Address1></Address1>
                <Address2></Address2>
                <MobileNo></MobileNo>
            </BillingDetails>

            <VchOtherInfoDetails>
                <OFInfo/>
                <Transport></Transport>
                <Station></Station>
                <PurchaseBillNo></PurchaseBillNo>
                <PurchaseBillDate>{$date}</PurchaseBillDate>
                <Narration1></Narration1>
                <GrDate>{$date}</GrDate>
            </VchOtherInfoDetails>

            <ItemEntries>
                {$itemEntries}
            </ItemEntries>
        </Sale>
        XML;
    }

    private function buildItemEntries(array $products, string $date, string $orderNo): string {
        $entries = '';
        foreach ($products as $index => $product) {
            $entries .= $this->buildItemEntry($product, $index + 1, $date, $orderNo);
        }
        return $entries;
    }

    private function buildItemEntry(array $product, int $serialNumber, string $date, string $orderNo): string {
        $itemName = trim((string) ($product['product_name'] ?? ''));
        $unitName = trim((string) ($product['unit_name'] ?? ''));
        $quantity = $this->parseNumber($product['quantity'] ?? 0);
        $rate = $this->parseNumber($product['rate'] ?? 0);
        $amount = $this->parseNumber($product['amount'] ?? null);
        if ($amount === null) {
            $amount = $quantity * $rate;
        }

        if ($itemName === '') {
            throw new \InvalidArgumentException("Product name is missing for item {$serialNumber}.");
        }

        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Quantity must be greater than zero for {$itemName}.");
        }

        if ($rate < 0) {
            throw new \InvalidArgumentException("Rate cannot be negative for {$itemName}.");
        }

        $itemName = $this->escapeXml($itemName);
        $unitName = $this->escapeXml($unitName);

        return <<<XML
        <ItemDetail>
            <Date>{$date}</Date>
            <VchType>{self::VOUCHER_TYPE}</VchType>
            <VchNo>{$orderNo}</VchNo>
            <SrNo>{$serialNumber}</SrNo>
            <ItemName>{$itemName}</ItemName>
            <UnitName>{$unitName}</UnitName>
            <AltUnitName>{$unitName}</AltUnitName>
            <ConFactor>1</ConFactor>
            <Qty>{$quantity}</Qty>
            <QtyMainUnit>{$quantity}</QtyMainUnit>
            <QtyAltUnit>{$quantity}</QtyAltUnit>
            <ItemTaxCategory>{self::TAX_CATEGORY}</ItemTaxCategory>
            <Price>{$rate}</Price>
            <PriceAltUnit>{$rate}</PriceAltUnit>
            <ListPrice>{$rate}</ListPrice>
            <Amt>{$amount}</Amt>
            <NettAmount>0</NettAmount>
            <CompoundDiscount>0.00</CompoundDiscount>
            <STAmount>0</STAmount>
            <STPercent>0</STPercent>
            <TaxBeforeSurcharge1>0</TaxBeforeSurcharge1>
            <STPercent1>0</STPercent1>
            <TaxBeforeSurcharge>0</TaxBeforeSurcharge>
            <MC>{self::STORE_NAME}</MC>
            <DiscountStructure>{self::DISCOUNT_STRUCTURE}</DiscountStructure>
        </ItemDetail>
        XML;
    }

    private function parseNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = str_replace(',', '', (string) $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === '.') {
            return 0;
        }
        return (float) $value;
    }

    private function escapeXml($value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
