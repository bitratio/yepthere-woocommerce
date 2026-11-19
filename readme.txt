=== yepthere Address Autocomplete ===
Tags: woocommerce, address autocomplete, checkout, australia
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.2.0
WC requires at least: 8.0
WC tested up to: 11.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Australian address suggestions for WooCommerce checkout and My Account, powered by yepthere and G-NAF.

== Description ==

Let shoppers search for an Australian street address and choose a suggestion. The plugin fills street address, building or site name, suburb, state and postcode. Shoppers can still edit every field or enter an address manually.

Requires WooCommerce 8.0 or later. When WC_Address_Provider is available (the provider filter is marked since 9.9.0), yepthere uses WooCommerce's native address autocomplete system for classic checkout, the Checkout block and standard My Account edit-address forms. WooCommerce 11.3.0 source was used for this integration; real-store checks are listed in TESTING.md.

On older WooCommerce versions without that API, the hosted widget supports classic checkout and My Account only. The Checkout block remains manual entry. Settings live at WooCommerce > Settings > yepthere. The My Account switch and result limit remain; separate billing and shipping switches were removed in 0.2.0 because WooCommerce controls checkout forms. Old switch values are ignored, including in the fallback. The request limit is 1–10 (default 6); WooCommerce 11.3 displays at most five suggestions in both native checkout UIs and the native account UI.

yepthere searches only when the address country is Australia. WooCommerce manages native suggestions and country changes; the fallback widget detaches when the country changes. It does not validate an address or require a suggestion to be selected. A missing key, a refused request or a blocked widget must not prevent checkout.

The plugin declares compatibility with High-Performance Order Storage and Cart/Checkout blocks. It stores no order data and adds no tracking. No build step or additional JavaScript framework is needed.

= External service and privacy =

This plugin uses yepthere, operated by Sessional Limited. An account and a hostname-restricted browser key are required. Create a key at https://yepthere.au/account and review the current service information, terms and privacy links at https://yepthere.au before enabling the service.

On enabled native address forms, typing at least three characters sends the address search text and result limit directly to https://yepthere.au/v1/addresses. The browser key is sent in the x-api-key request header, never in the URL, and fetch uses credentials: omit. Requests are debounced, superseded searches are aborted, and failures return no suggestions. Choosing a suggestion uses its address already held in page memory, with no second request. Search records are not persisted.

Only the older-WooCommerce fallback loads https://yepthere.au/widget.js on enabled classic checkout/account edit pages. That widget also sends searches to the addresses endpoint with the browser key in the x-api-key header. The browser connection exposes information such as IP address and site origin to yepthere. The widget can load even when the selected country is not Australia, but address searches are enabled only for Australia.

The plugin makes no remote requests from PHP and does not persist address-search text. It adds suggested wording under WordPress's Privacy Policy Guide for the merchant to review and incorporate into their own policy. The snippet covers checkout and account address forms.

= Data attribution =

The native provider supplies the exact line "Incorporates or developed using G-NAF © Geoscape Australia" as branding HTML. WooCommerce renders it below the suggestion list inside .woocommerce-address-autocomplete-branding: in #address_suggestions_billing or #address_suggestions_shipping for classic/account forms, and .wc-block-components-address-autocomplete-suggestions for the block. It is visible while nonempty suggestions are shown and closes with the list. The fallback widget continues to render its own attribution and licence link. Keep the attribution visible when customising your theme. Do not use the data to compile a list for sending mail without verification from a secondary source.

== Installation ==

1. Place this plugin's files in wp-content/plugins/yepthere-address-autocomplete/ and activate it with WooCommerce active.
2. Visit https://yepthere.au/account and create a browser key beginning sab_live_, restricted to the hostname(s) that serve your store. Include your staging hostname when testing there.
3. Open WooCommerce > Settings > yepthere and paste the browser key. Never use a sak_live_ server key in this setting or in a web page.
4. Choose My Account availability and the result limit, then save changes.
5. On WooCommerce with the provider API, open WooCommerce > Settings > General and tick Address autocomplete > Enable predictive address search. If multiple providers are installed, choose yepthere as the preferred provider. The yepthere tab links to this setting and highlights when it is disabled. This plugin does not change WooCommerce options, even on first key save. Native My Account suggestions also require this setting.
6. Review the suggested privacy-policy wording and run the checks in TESTING.md on your staging store before release.

If your content security policy restricts connections, allow the yepthere API. For the older-WooCommerce fallback, also allow the hosted widget and its injected stylesheet as described in the widget documentation. Caching or script optimisation tools must preserve script dependency order and deferred loading. Purge cached checkout/account pages after changing a key or settings.

== Frequently Asked Questions ==

= What does the service cost? =

yepthere offers a free allowance and paid service options. Allowances, conditions and available plans can change. Check https://yepthere.au/account for current details. The plugin does not sell or manage subscriptions.

= Which key should I use? =

Use a browser key beginning sab_live_, restricted to your site's hostname, created in your yepthere account. Browser keys are intentionally visible to shoppers in page source and developer tools. The masked settings field does not make the key secret. Server keys beginning sak_live_ are refused because server keys must never be put in a page. Invalid submissions preserve the previous valid key; saving an empty key disables the integration.

= What happens to non-Australian addresses? =

Autocomplete is inactive unless that form's country is AU. All fields remain ordinary editable WooCommerce fields. Switching country does not clear the address; the shopper can amend it. No suggestion is required to submit an order.

= Does it support the Checkout block? =

Yes, when WooCommerce's native provider API exists and its address autocomplete setting is enabled. WooCommerce owns suggestions, address fields and customer/cart updates. The plugin supplies search results and selected addresses through the provider contract; it does not manipulate block DOM or dispatch cart-store actions. Without that API, the block uses manual entry. See TESTING.md for the real-store release checks.

= Does it work on My Account? =

Yes, on the standard edit billing address and edit shipping address forms for logged-in customers. The My Account switch must be enabled. With the provider API, WooCommerce’s address autocomplete setting must also be enabled; otherwise the older-version widget is used only when the API is absent. It does not load on the address overview or account login page.

= What if the service is unavailable or a key is refused? =

The native provider returns no suggestions on failure; the fallback widget may show a service error. The shopper can continue typing and submit WooCommerce's normal form. Check the key's allowed hostname and service allowance in your yepthere account. This plugin does not add checkout validation or intercept form submission.

= How do I remove the plugin's settings? =

Deleting the plugin through WordPress removes its browser key, form preferences, result limit and dismissed-notice option. Deactivation keeps the settings. Retired billing/shipping options are also deleted. WooCommerce’s own options, existing customer addresses and orders are untouched.

== Changelog ==

= 0.2.0 =
* Native WooCommerce address provider for both checkouts and standard account edit-address forms.
* AU-only browser requests, abortable searches and in-memory selection, with native G-NAF branding.
* Removed the block DOM integration, IntegrationInterface class, wp-data logic and billing/shipping toggles.
* Preserved classic/account widget fallback when the provider API is absent; no block fallback.
* General-settings guidance without changing WooCommerce options. HPOS and Cart/Checkout blocks compatibility declarations retained.

= 0.1.0 =
* Initial version with classic checkout, Checkout block and My Account address autocomplete.
* WooCommerce settings tab with browser-key validation and per-form controls.
* Australian-country gating, privacy-policy wording and preserved G-NAF attribution.
