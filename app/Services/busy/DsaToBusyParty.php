<?php

namespace App\Services\busy;

use App\Services\BusyApiService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DsaToBusyParty
{
    private const MASTER_TYPE = 2;
    private const PARENT_GROUP = 'Sundry Debtors';
    private const BILL_BY_BILL = 'True';
    private const SUPPLIER_TYPE = 1;
    private const PRICE_LEVEL = '@';
    private const TAX_TYPE = 'Others';
    private const DEALER_TYPE = 'Registered';
    private const REVERSE_CHARGE = 'Not Applicable';
    private const INPUT_TYPE = 'Section 17(5)-ITC None';
    private const AREA_NAME = '---Others---';
    private const COUNTRY_NAME = 'India';

    public function __construct(
        private BusyApiService $busyApiService
    ) {}

    public function pushParty(array $client): array
    {
        $startedAt = microtime(true);

        $result = [
            'success' => false,
            'client_id' => $client['id'] ?? null,
            'client_name' => $client['name'] ?? null,
            'status' => null,
            'result' => null,
            'description' => null,
            'body' => null,
            'duration_ms' => 0,
            'error' => null,
        ];

        try {
            $this->validateClient($client);
            $xml = $this->buildAccountXml($client);
            Log::channel('busy')->info('BUSY Party Request', [
                'client_id' => $client['id'] ?? null,
                'client_name' => $client['name'] ?? null,
                'xml_length' => strlen($xml),
            ]);

            $response = $this->busyApiService->createMaster(self::MASTER_TYPE,$xml);
            $result['status'] = $response['status'] ?? null;
            $result['result'] = $response['result'] ?? null;
            $result['description'] = $response['description'] ?? null;
            $result['body'] = $response['body'] ?? null;
            $result['success'] = $response['success'] ?? false;
            Log::channel('busy')->info('BUSY Party Response', [
                'client_id' => $client['id'] ?? null,
                'client_name' => $client['name'] ?? null,
                'success' => $result['success'],
                'status' => $result['status'],
                'result' => $result['result'],
                'description' => $result['description'],
            ]);
            return $result;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::channel('busy')->error('BUSY Party Push Failed', [
                'client_id' => $client['id'] ?? null,
                'client_name' => $client['name'] ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $result;
        } finally {
            $result['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
        }
    }

    private function validateClient(array $client): void
    {
        if (empty($client['id'])) {
            throw new \InvalidArgumentException('Client ID is required.');
        }

        if (empty(trim((string) ($client['name'] ?? '')))) {
            throw new \InvalidArgumentException('Client name is required.');
        }
    }

    private function buildAccountXml(array $client): string
    {
        $name = $this->escapeXml(trim((string) $client['name']));
        $address1 = $this->escapeXml($client['address_1'] ?? '');
        $address2 = $this->escapeXml($client['address_2'] ?? '');
        $mobile = $this->escapeXml($client['mobile'] ?? $client['phone'] ?? '');
        $whatsapp = $this->escapeXml($client['phone'] ?? $client['mobile'] ?? '');
        $pan = $this->escapeXml($client['pan'] ?? '');
        $gst = $this->escapeXml($client['gst'] ?? $client['gst_no'] ?? '');
        $state = $this->escapeXml($client['state'] ?? '');
        $accNo = $this->escapeXml($client['acc_no'] ?? '');
        $ifsc = $this->escapeXml($client['ifsc'] ?? '');
        $bank = $this->escapeXml($client['bank'] ?? '');
        $branch = $this->escapeXml($client['branch'] ?? '');

        $parentGroup = self::PARENT_GROUP;
        $billByBill = self::BILL_BY_BILL;
        $supplierType = self::SUPPLIER_TYPE;
        $priceLevel = self::PRICE_LEVEL;
        $taxType = self::TAX_TYPE;
        $dealerType = self::DEALER_TYPE;
        $reverseCharge = self::REVERSE_CHARGE;
        $inputType = self::INPUT_TYPE;
        $areaName = self::AREA_NAME;
        $countryName = self::COUNTRY_NAME;

        return <<<XML
        <Account>
            <Name>{$name}</Name>
            <Alias>{$name}</Alias>
            <PrintName>{$name}</PrintName>
            <ParentGroup>{$parentGroup}</ParentGroup>
            <BillByBillBalancing>{$billByBill}</BillByBillBalancing>

            <Address>
                <Address1>{$address1}</Address1>
                <Address2>{$address2}</Address2>
                <Mobile>{$mobile}</Mobile>
                <WhatsAppNo>{$whatsapp}</WhatsAppNo>
                <ITPAN>{$pan}</ITPAN>
                <GSTNo>{$gst}</GSTNo>
                <CountryName>{$countryName}</CountryName>
                <StateName>{$state}</StateName>
                <AreaName>{$areaName}</AreaName>
                <AccNo>{$accNo}</AccNo>
                <C3>{$ifsc}</C3>
                <C4>{$bank}</C4>
                <C5>{$branch}</C5>
            </Address>

            <SupplierType>{$supplierType}</SupplierType>
            <PriceLevel>{$priceLevel}</PriceLevel>
            <PriceLevelForPurc>{$priceLevel}</PriceLevelForPurc>
            <TaxType>{$taxType}</TaxType>
            <TypeOfDealerGST>{$dealerType}</TypeOfDealerGST>
            <ChequePrintName>{$name}</ChequePrintName>
            <ReverseChargeType>{$reverseCharge}</ReverseChargeType>
            <InputType>{$inputType}</InputType>
        </Account>
        XML;
    }

    private function escapeXml($value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
