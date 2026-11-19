# 0.2.0 verification

The implementation was checked against the supplied WooCommerce 11.3.0 source. Local JavaScript syntax and offline provider-contract checks were run. PHP is unavailable here, so PHP lint, WordPress execution and browser testing were not run. A reviewer must run these release checks on WordPress 7.1.5 / WooCommerce 11.3.0. Source references and the file list are in REPORT.md.

## Local checks

No dependency installation or network access is required:

```sh
node --check assets/provider.js
node --check assets/forms.js
node tests/provider-contract.cjs /path/to/wc-src
```

The contract test loads WooCommerce's actual shared registration script and uses fake fetch/timers. It verifies registration, AU gating, URL/header/credentials, labels/matches, line-2 clearing, leading-zero postcodes, no selection request, immutable stored records, cancelled debounce promises, HTTP cancellation, late-response rejection, errors/malformed JSON, timeout, independent classic billing/shipping searches and pagehide cleanup. It does not test rendering or WordPress hooks. Lint all PHP files when PHP is available.

## Settings and activation

1. Activate with WooCommerce active; also check activation without WooCommerce does not fatal. Confirm version 0.2.0, WC tested up to 11.3, HPOS and Cart/Checkout blocks compatibility declarations.
2. With no key, the provider filter must not include yepthere and no yepthere scripts/config should be emitted. Save a hostname-restricted `sab_live_` key; confirm a `WC_Address_Provider` with id/name `yepthere` appears in the controller's providers on the next request.
3. Submit `sak_live_`, malformed strings, arrays and whitespace-only keys; confirm existing validation/errors, capability checks and nonce checks. Rejected credentials must not be echoed. Empty key disables yepthere; surrounding whitespace on a valid key is trimmed.
4. With WooCommerce autocomplete unset or `no`, saving a key must leave it unchanged. The yepthere tab must direct the merchant to tick “Enable predictive address search” and link to General. Enable it there; choose yepthere as preferred if another provider exists. Plugin settings must never alter WooCommerce's enable/preference options.
5. Confirm billing/shipping toggles are gone and old `no` values no longer disable either integration. Keep My Account and limit controls. Test limits 1, 6, 10 and forged 0, 11, fractional and nonnumeric values; expect bounding/integer conversion or fallback 6. The API request uses this limit, but both native checkout UIs display at most five.
6. Confirm missing-key notice, persistent nonce-protected dismissal, settings tab and Privacy Policy Guide still work. Other roles cannot save settings or dismiss the notice.

## Native classic checkout and Checkout block

Run each check on both checkout types with a physical product, then a virtual-only order. Test guests and logged-in customers with saved addresses.

1. With key + WooCommerce setting enabled, confirm `yepthere-aa-provider` loads with dependency `wc-address-autocomplete` and the common registry contains yepthere. No `widget.js`, `yepthere-aa-forms`, old block handle or `.sa-widget` may appear. Core may enqueue its own autocomplete assets on other pages; the plugin must not emit its key/script there.
2. Choose an AU address and inspect street, line 2, suburb, state and postcode. Choose a building name, then a result without line 2 to check clearing; test NT postcode `0800`. Check selectWoo's state label on classic and actual cart/customer state on Blocks. Complete orders and inspect persisted billing/shipping addresses.
3. Test separate billing/shipping, same-as-shipping on/off, collapsed forms, an unmounted optional line-2 input, two selections, selecting the same result again, manual edits and name/company/email/phone preservation. Core owns block state updates; visible values alone are insufficient evidence.
4. Trigger `update_checkout`, shipping-method changes, coupons and validation errors. Confirm values survive and suggestions continue without duplicate dropdowns. Test block rerenders, delayed hydration, back/forward navigation and an AU-only country configuration with a hidden country selector.
5. Start non-AU, switch AU/non-AU repeatedly, and change country during a pending request. No new yepthere search may be issued for non-AU. Check that core closes stale suggestions and cannot apply an AU result after switching away. Country-change/rerender handling belongs to WooCommerce and is a runtime release check.
6. Verify GET `https://yepthere.au/v1/addresses?q=...&limit=...`, browser key only in `x-api-key`, no cookies (`credentials: omit`), superseded requests cancelled and no request on selection. Rapidly type in each form; old responses must not replace newer results. Blocks omits the type argument so its searches share a cancellation slot; classic supplies separate billing/shipping slots.
7. Return 401/403/429/500, disconnect, return invalid JSON or `{ ok: false }`, or delay beyond eight seconds. Expect no suggestions and working manual checkout. Block the provider script and complete an order manually. Test optimisation/defer tools with dependency order preserved.
8. Test keyboard selection, Escape, Tab, pointer selection, screen readers, mobile widths and zoom. Verify “Incorporates or developed using G-NAF © Geoscape Australia” below every nonempty suggestion list. It closes with the list; it is not expected on empty/error lists. Check the entire text remains legible with the production theme.

## My Account

1. Native scripts also bind standard `billing_*` and `shipping_*` account fields. With the provider API, global autocomplete enabled and My Account on, verify native suggestions, branding and persistence after saving/reopening each form. No hosted widget should load.
2. Switch My Account off, or WooCommerce autocomplete off: yepthere JS/config must not load on account forms. With another provider installed, the My Account switch controls yepthere only.
3. Login, dashboard, Addresses overview and unrelated endpoints must not load yepthere scripts. Direct edit URLs as a guest must not expose the key. Order-pay, order-received, unrelated shop/cart pages and empty-cart checkout must also omit yepthere scripts/config.

## Older WooCommerce without WC_Address_Provider

Use a real older supported install; disabling the global autocomplete option does not activate this fallback.

1. Classic checkout and enabled account edit forms load the existing widget + `forms.js` + CSS, and no provider script. The block loads none of these and remains manual entry. No IntegrationInterface or wp-data code remains in the plugin.
2. Repeat the known-good 0.1.0 classic/account checks: AU-only attachment, state/selectWoo fill, line-2 clearing, update_checkout persistence, country-change detach/cancellation, keyboard selection, attribution/licence link, account save/reopen and API/script-failure manual entry.
3. Both standard forms are eligible now that billing/shipping switches are removed; My Account still has its own opt-out. Missing/invalid key disables all yepthere assets. Compatibility declarations must not require the provider base class.

## Removal

Deactivation preserves settings. Uninstall removes the four current options (`browser_key`, `account`, `limit`, `notice_dismissed`) and two retired switches (`billing`, `shipping`), all prefixed `yepthere_aa_`, on every multisite site. WooCommerce autocomplete/preferred-provider options, customer addresses and orders must remain untouched.
