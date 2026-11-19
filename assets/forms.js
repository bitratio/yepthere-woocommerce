/* global yepthereAA */
(function () {
    'use strict';

    var config = window.yepthereAA;
    if (!config || !/^sab_live_[A-Za-z0-9_-]+$/.test(config.key || '')) {
        return;
    }

    var entries = new Map();
    var timer;
    var running = false;
    var observer;
    var mapping = {
        line1: 'address_1',
        line2: 'address_2',
        suburb: 'city',
        state: 'state',
        postcode: 'postcode'
    };

    function field(prefix, name) {
        return document.getElementById(prefix + '_' + name);
    }

    function country(prefix) {
        var element = field(prefix, 'country');
        if (element && /^[A-Z]{2}$/.test(element.value)) {
            return element.value;
        }
        return '';
    }

    function fields(prefix) {
        var result = {};
        Object.keys(mapping).forEach(function (name) {
            // Resolve replacement state/address fields at selection time.
            result[name] = '#' + prefix + '_' + mapping[name];
        });
        return result;
    }

    function destroy(entry) {
        try {
            entry.widget.destroy();
        } catch (error) {
            // An enhancement must never prevent manual entry or submission.
        }
    }

    function attach(prefix, input) {
        var entry = { input: input, widget: null };
        try {
            entry.widget = window.Yepthere.attach(input, {
                key: config.key,
                baseUrl: 'https://yepthere.au',
                limit: config.limit,
                fields: fields(prefix),
                onSelect: function () {
                    if (window.jQuery && country(prefix) === 'AU') {
                        // AU abbreviations are the WooCommerce option values.
                        // jQuery change refreshes selectWoo/select2 as well.
                        window.jQuery(field(prefix, 'state')).trigger('change');
                        if (config.context === 'classic') {
                            window.jQuery(document.body).trigger('update_checkout');
                        }
                    }
                    schedule();
                }
            });
            // The widget owns its required G-NAF attribution.
            entries.set(prefix, entry);
        } catch (error) {
            if (entry.widget) {
                destroy(entry);
            }
        }
    }

    function reconcile() {
        if (running || !window.Yepthere || typeof window.Yepthere.attach !== 'function') {
            return;
        }
        running = true;
        try {
            ['billing', 'shipping'].forEach(function (prefix) {
                var input = field(prefix, 'address_1');
                var enabled = input && input.isConnected && !input.disabled && !input.readOnly && country(prefix) === 'AU';
                var entry = entries.get(prefix);
                if (entry && (!enabled || entry.input !== input)) {
                    destroy(entry);
                    entries.delete(prefix);
                    entry = null;
                }
                if (enabled && !entry) {
                    attach(prefix, input);
                }
            });
        } finally {
            running = false;
        }
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(reconcile, 80);
    }

    function countryChanged(event) {
        if (event.target && /^(billing|shipping)_country$/.test(event.target.id)) {
            // Destroy immediately on a country change to cancel pending AU requests.
            reconcile();
            schedule();
        }
    }

    function start() {
        reconcile();
        document.addEventListener('change', countryChanged, true);
        document.addEventListener('input', countryChanged, true);
        if (window.jQuery) {
            window.jQuery(document.body)
                .on('updated_checkout.yepthereAA country_to_state_changed.yepthereAA', schedule)
                .on('change.yepthereAA', '#billing_country, #shipping_country', countryChanged);
        }
        observer = new MutationObserver(function (records) {
            // Ignore result/status changes made by the widget itself. Its initial
            // wrapping schedules at most one extra, idempotent reconciliation.
            if (records.some(function (record) {
                var target = record.target.nodeType === 1 ? record.target : record.target.parentElement;
                return !target || !target.closest('.sa-widget');
            })) {
                schedule();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'readonly'] });
    }

    // Dependency ordering normally guarantees the widget has loaded. If it is
    // blocked or fails, start still leaves every field as an ordinary input.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
    window.addEventListener('pagehide', function () {
        window.clearTimeout(timer);
        if (observer) {
            observer.disconnect();
        }
        entries.forEach(destroy);
        entries.clear();
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            // A restored back/forward-cache document keeps its event listeners.
            if (observer) {
                observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'readonly'] });
            }
            schedule();
        }
    });
}());
