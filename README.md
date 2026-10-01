# omnibus/bluedart

Blue Dart for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): serviceability and
transit time (Transit API), waybills with their labels (Waybill API), tracking (Tracking API) and
cancellation - Blue Dart's API gateway with a JWT.

```yaml
omnibus:
    gateways:
        bluedart:
            factory: bluedart
            options:
                client_id: '%env(BLUEDART_CLIENT_ID)%'
                client_secret: '%env(BLUEDART_CLIENT_SECRET)%'
                login_id: '%env(BLUEDART_LOGIN)%'
                licence_key: '%env(BLUEDART_LICENCE)%'
                customer_code: '%env(BLUEDART_CUSTOMER)%'
                origin_area: BOM
                sandbox: true
                rates: [...]        # or prices per product through the shipment option "prices"
```

The service is the product code (A Domestic Priority, E Dart Apex, D Dart Surfaceline). Blue Dart
quotes no price: rating checks which products serve the lane and in how many days, and takes the
amount from the shipment option `prices` (product => minor units) or from configured `rates`.
Shipment options: `description`, `instructions`, `sub_product` (P by default), `register_pickup`.
No pickup points: Blue Dart delivers to the door.

Credentials: an app on [Blue Dart's developer portal](https://www.bluedart.com/developer) gives
the client id and secret; the ShipDart profile (login id, licence key, customer code, area) comes
from your account manager.

Built from Blue Dart's published API documentation and tested on recorded answers; **unverified**
against the sandbox until an account's keys are at hand.

License: LGPL-3.0-or-later.
