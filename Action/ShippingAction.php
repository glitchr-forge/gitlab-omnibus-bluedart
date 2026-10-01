<?php

namespace Omnibus\Bluedart\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Bluedart\Api;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;

/** The Waybill API's GenerateWayBill: the AWB number and its label (PDF). Service: the product code (A Domestic Priority by default, E, D). */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $date = $s->shippingDate ?? new \DateTimeImmutable();
        $data = $this->api->call('POST', '/in/transportation/waybill/v1/GenerateWayBill', ['Request' => [
            'Consignee' => self::consignee($s->recipient),
            'Shipper' => self::shipper($s->sender, $this->api),
            'Services' => array_filter([
                'ActualWeight' => round(max(0.1, $s->weight() / 1000), 2),
                'CollectableAmount' => 0,
                'Commodity' => ['CommodityDetail1' => (string) $s->option('description', 'Merchandise')],
                'CreditReferenceNo' => mb_substr((string) ($s->reference ?? uniqid('omnibus', false)), 0, 50),
                'DeclaredValue' => array_sum(array_map(static fn ($p) => $p->value ?? 0, $s->parcels)) / 100,
                'Dimensions' => array_map(static fn ($p) => ['Breadth' => $p->width ?? 10, 'Count' => 1, 'Height' => $p->height ?? 10, 'Length' => $p->length ?? 10], $s->parcels),
                'PickupDate' => '/Date('.($date->getTimestamp() * 1000).')/',
                'PickupTime' => '1600',
                'PieceCount' => \count($s->parcels),
                'ProductCode' => $s->service ?? 'A',
                'ProductType' => 'Dutables',
                'RegisterPickup' => (bool) $s->option('register_pickup', false),
                'SubProductCode' => (string) $s->option('sub_product', 'P'),
                'SpecialInstruction' => mb_substr((string) $s->option('instructions', ''), 0, 100),
            ], static fn ($v) => null !== $v),
            'Returnadds' => self::shipper($s->sender, $this->api),
        ], 'Profile' => $this->api->profile()]);
        $result = $data['GenerateWayBillResult'] ?? $data;
        if (!empty($result['IsError'])) {
            throw new CarrierException('bluedart', (string) ($result['Status'][0]['StatusInformation'] ?? 'Blue Dart refused the waybill.'), isset($result['Status'][0]['StatusCode']) ? (string) $result['Status'][0]['StatusCode'] : null);
        }
        $number = (string) ($result['AWBNo'] ?? '');
        if ('' === $number) {
            throw new CarrierException('bluedart', 'Blue Dart issued no waybill.');
        }
        $pdf = $result['AWBPrintContent'] ?? null;
        $content = \is_array($pdf) ? implode('', array_map('chr', $pdf)) : (\is_string($pdf) ? base64_decode($pdf) : null);
        $request->setResult(new Label('bluedart', $number, $content, Label::PDF, null, 'https://www.bluedart.com/tracking?trackfor=0&trackno='.rawurlencode($number)));
    }

    private static function consignee(Address $a): array
    {
        return array_filter(['ConsigneeAddress1' => mb_substr($a->line(0), 0, 30), 'ConsigneeAddress2' => mb_substr($a->line(1), 0, 30), 'ConsigneeAddress3' => mb_substr($a->city, 0, 30), 'ConsigneeAttention' => mb_substr($a->name, 0, 30), 'ConsigneeEmailID' => $a->email, 'ConsigneeMobile' => preg_replace('/\D+/', '', (string) $a->phone), 'ConsigneeName' => mb_substr($a->company ?? $a->name, 0, 30), 'ConsigneePincode' => $a->postcode, 'ConsigneeTelephone' => preg_replace('/\D+/', '', (string) $a->phone)], static fn ($v) => null !== $v && '' !== $v);
    }

    private static function shipper(Address $a, Api $api): array
    {
        return array_filter(['CustomerAddress1' => mb_substr($a->line(0), 0, 30), 'CustomerAddress2' => mb_substr($a->line(1), 0, 30), 'CustomerAddress3' => mb_substr($a->city, 0, 30), 'CustomerCode' => $api->customerCode, 'CustomerEmailID' => $a->email, 'CustomerMobile' => preg_replace('/\D+/', '', (string) $a->phone), 'CustomerName' => mb_substr($a->company ?? $a->name, 0, 30), 'CustomerPincode' => $a->postcode, 'CustomerTelephone' => preg_replace('/\D+/', '', (string) $a->phone), 'IsToPayCustomer' => false, 'OriginArea' => $api->originArea ?? 'BOM', 'Sender' => mb_substr($a->name, 0, 30)], static fn ($v) => null !== $v && '' !== $v);
    }
}
