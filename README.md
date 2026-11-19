# yepthere Address Autocomplete for WooCommerce

Australian address suggestions for WooCommerce classic checkout, the checkout block and My Account address forms, powered by [yepthere.au](https://yepthere.au).

## Download

**[Download the plugin zip](https://github.com/bitratio/yepthere-woocommerce/releases/latest/download/yepthere-address-autocomplete.zip)**

The zip is attached to each [release](https://github.com/bitratio/yepthere-woocommerce/releases), not stored in the file list above. Do not use GitHub's green "Code > Download ZIP" button: that archive has the wrong folder name for WordPress.

## Install

1. Download the plugin zip from the link above.
2. In WordPress, open Plugins > Add New > Upload Plugin, upload the zip and activate it (WooCommerce must be active).
3. In your [yepthere account](https://yepthere.au/account), create a browser key (it begins `sab_live_`) for your shop's hostname.
4. Paste the key under WooCommerce > Settings > yepthere and save.
5. Under WooCommerce > Settings > General, tick address autocomplete and save.

Full guide: [yepthere.au/woocommerce](https://yepthere.au/woocommerce).

## Requirements

WordPress 6.4 or later, PHP 7.4 or later, WooCommerce 8.0 or later. Native address autocomplete (including the checkout block) needs WooCommerce 9.9 or later; on older versions a widget fallback covers classic checkout and My Account.

## Notes

- Australian addresses only. Other countries are left alone.
- If the service cannot be reached, the address fields work as normal text inputs.
- Address data: Incorporates or developed using G-NAF © Geoscape Australia. The attribution is shown with the suggestions and must stay visible.
- Licence: GPL-2.0-or-later.
