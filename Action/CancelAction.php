<?php

namespace Omnibus\Bluedart\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Bluedart\Api;
use Omnibus\Request\Cancel;
use Omnibus\Request\Request;

/** The Waybill API's CancelWaybill. */
final class CancelAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $data = $this->api->call('POST', '/in/transportation/waybill/v1/CancelWaybill', ['Request' => ['AWBNo' => $request->trackingNumber], 'Profile' => $this->api->profile()]);
        $result = $data['CancelWaybillResult'] ?? $data;
        $request->setResult(empty($result['IsError']));
    }
}
