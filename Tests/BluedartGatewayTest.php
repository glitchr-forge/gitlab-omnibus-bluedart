<?php

namespace Omnibus\Bluedart\Tests;

use Omnibus\Bluedart\BluedartGatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Model\TrackingStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BluedartGatewayTest extends TestCase
{
    private array $calls = [];

    private static function shipment(): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['Nariman Point'], '400021', 'Mumbai', 'IN', phone: '02212345678'), new Address('Alex Martin', ['MG Road'], '560001', 'Bengaluru', 'IN', phone: '9876543210'), [new Parcel(1500, 30, 20, 10, 150000, 'INR')], reference: 'ORDER-1042', options: ['prices' => ['A' => 45000, 'D' => 18000]]);
    }

    private function gateway(): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertStringStartsWith('https://apigateway-sandbox.bluedart.com', $url);
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$method, $path, \is_string($options['body'] ?? null) && str_starts_with($options['body'], '{') ? json_decode($options['body'], true) : [], $options['headers']];
            $product = $this->calls[array_key_last($this->calls)][2]['pProductCode'] ?? '';

            return match (true) {
                str_ends_with($path, '/token/v1/login') => new MockResponse(json_encode(['JWTToken' => 'jwt'])),
                str_ends_with($path, 'GetDomesticTransitTimeForPinCodeandProduct') => 'E' === $product ? new MockResponse(json_encode(['GetDomesticTransitTimeForPinCodeandProductResult' => ['IsError' => true]])) : new MockResponse(json_encode(['GetDomesticTransitTimeForPinCodeandProductResult' => ['IsError' => false, 'ExpectedDateDelivery' => '/Date('.((time() + ('A' === $product ? 1 : 3) * 86400) * 1000).')/']])),
                str_ends_with($path, '/GenerateWayBill') => new MockResponse(json_encode(['GenerateWayBillResult' => ['IsError' => false, 'AWBNo' => '79123456789', 'AWBPrintContent' => array_map('ord', str_split('%PDF-1.4 bd'))]])),
                str_ends_with($path, '/tracking/v1/shipment') => new MockResponse(json_encode(['ShipmentData' => ['Shipment' => ['WaybillNo' => '79123456789', 'StatusType' => 'DL', 'Scans' => ['ScanDetail' => [['Scan' => 'SHIPMENT DELIVERED', 'ScanCode' => '000', 'ScanDate' => '02-Oct-2026', 'ScanTime' => '11:30', 'ScanType' => 'DL', 'ScannedLocation' => 'Bengaluru'], ['Scan' => 'SHIPMENT PICKED UP', 'ScanCode' => '001', 'ScanDate' => '01-Oct-2026', 'ScanTime' => '17:00', 'ScanType' => 'PU', 'ScannedLocation' => 'Mumbai']]]]]])),
                str_ends_with($path, '/CancelWaybill') => new MockResponse(json_encode(['CancelWaybillResult' => ['IsError' => false]])),
                default => new MockResponse(json_encode(['error-response' => [['ErrorCode' => '404', 'Message' => 'No such resource']]]), ['http_code' => 404]),
            };
        });

        return (new BluedartGatewayFactory($http))->create(['client_id' => 'id', 'client_secret' => 'secret', 'login_id' => 'BOM12345', 'licence_key' => 'lic', 'customer_code' => '123456', 'origin_area' => 'BOM', 'sandbox' => true]);
    }

    public function testServedProductsAreRatedWithConfiguredPricesAndTransitDays(): void
    {
        $rates = $this->gateway()->rate(self::shipment());
        self::assertSame(['D', 'A'], array_map(fn ($r) => $r->service, $rates), 'E is not served');
        self::assertSame(18000, $rates[0]->amount);
        self::assertSame('INR', $rates[0]->currency);
        self::assertSame(1, $rates[1]->days);
        self::assertSame('BOM12345', $this->calls[1][2]['profile']['LoginID']);
        self::assertContains('JWTToken: jwt', $this->calls[1][3]);
    }

    public function testAWaybillIsGeneratedWithItsPdf(): void
    {
        $label = $this->gateway()->ship(self::shipment());
        self::assertSame('79123456789', $label->trackingNumber);
        self::assertSame('%PDF-1.4 bd', $label->content);
        $sent = $this->calls[1][2]['Request'];
        self::assertSame('A', $sent['Services']['ProductCode']);
        self::assertSame('560001', $sent['Consignee']['ConsigneePincode']);
        self::assertSame('123456', $sent['Shipper']['CustomerCode']);
        self::assertEquals(1500.0, $sent['Services']['DeclaredValue']);
    }

    public function testTrackingAndCancel(): void
    {
        $gateway = $this->gateway();
        $tracking = $gateway->track('79123456789');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame('SHIPMENT PICKED UP', $tracking->events[0]->description);
        self::assertSame('Bengaluru', $tracking->latest()->location);
        self::assertTrue($gateway->cancel('79123456789'));
    }
}
