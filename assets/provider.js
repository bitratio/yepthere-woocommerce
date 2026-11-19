/* global yepthereAAProvider */
(function () {
    'use strict';

    var config = window.yepthereAAProvider;
    var registry = window.wc && window.wc.addressAutocomplete;
    if (!config || !/^sab_live_[A-Za-z0-9_-]+$/.test(config.key || '') ||
        !registry || typeof registry.registerAddressAutocompleteProvider !== 'function') {
        return;
    }

    var limit = Math.max(1, Math.min(10, parseInt(config.limit, 10) || 6));
    var pending = new Map();
    var sessions = new Map();
    // Keep records for the lifetime of this page so another form's search cannot
    // invalidate a still-visible suggestion. No address data goes into storage.
    var addresses = new Map();
    var nextId = 0;

    function value(input) {
        return typeof input === 'string' ? input : '';
    }

    function sessionToken() {
        var alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
        return Array.from(window.crypto.getRandomValues(new Uint8Array(22)), function (byte) {
            return alphabet[byte & 63];
        }).join('');
    }

    function cancel(slot) {
        var previous = pending.get(slot);
        if (previous) {
            window.clearTimeout(previous.timer);
            window.clearTimeout(previous.timeout);
            if (previous.controller) {
                previous.controller.abort();
            }
            previous.resolve([]);
            pending.delete(slot);
        }
    }

    function search(text, country, type) {
        // Classic supplies a form type; Blocks 11.3 calls search(text, country).
        var slot = type === 'billing' || type === 'shipping' ? type : 'checkout';
        cancel(slot);
        text = value(text).trim();
        var at = Date.now();
        var session = sessions.get(slot);
        if (!text || session && at - session.last >= 180000) {
            sessions.delete(slot);
            session = null;
        }
        if (session) {
            session.last = at;
        }
        if (country !== 'AU' || text.length < 3) {
            return Promise.resolve([]);
        }
        if (!session) {
            session = { token: sessionToken(), last: at };
            sessions.set(slot, session);
        }

        return new Promise(function (resolve) {
            var request = { resolve: resolve, controller: null, timer: null, timeout: null };
            pending.set(slot, request);
            // Resolve cancelled debounce calls as well as cancelled HTTP calls.
            request.timer = window.setTimeout(async function () {
                try {
                    request.controller = new AbortController();
                    request.timeout = window.setTimeout(function () {
                        if (pending.get(slot) === request) {
                            cancel(slot);
                        }
                    }, 8000);
                    var response = await fetch('https://yepthere.au/v1/addresses?q=' +
                        encodeURIComponent(text) + '&limit=' + limit, {
                        method: 'GET',
                        headers: { 'x-api-key': config.key, 'X-Search-Session': session.token },
                        credentials: 'omit',
                        signal: request.controller.signal
                    });
                    if (!response.ok) {
                        resolve([]);
                        return;
                    }
                    var data = await response.json();
                    if (pending.get(slot) !== request || !data || data.ok !== true || !Array.isArray(data.results)) {
                        resolve([]);
                        return;
                    }
                    var suggestions = [];
                    data.results.slice(0, limit).forEach(function (record) {
                        if (!record || typeof record !== 'object' || !value(record.line1).trim()) {
                            return;
                        }
                        var address = {
                            address_1: value(record.line1),
                            address_2: value(record.line2),
                            city: value(record.suburb),
                            state: value(record.state),
                            postcode: value(record.postcode),
                            country: 'AU'
                        };
                        var label = [address.address_1, address.address_2, address.city, address.state, address.postcode]
                            .filter(Boolean).join(', ');
                        var offset = label.toLowerCase().indexOf(text.toLowerCase());
                        var id = 'yepthere-' + (++nextId);
                        addresses.set(id, { address: address, slot: slot });
                        suggestions.push({
                            id: id,
                            label: label,
                            matchedSubstrings: offset < 0 ? [] : [{ offset: offset, length: text.length }]
                        });
                    });
                    resolve(suggestions);
                } catch (error) {
                    // Offline, refused key, malformed response or abort: manual entry.
                    resolve([]);
                } finally {
                    window.clearTimeout(request.timeout);
                    if (pending.get(slot) === request) {
                        pending.delete(slot);
                    }
                }
            }, 250);
        });
    }

    registry.registerAddressAutocompleteProvider({
        id: 'yepthere',
        canSearch: function (country) { return country === 'AU'; },
        search: search,
        select: function (id) {
            var selected = addresses.get(id);
            if (selected) {
                sessions.delete(selected.slot);
            }
            // An empty object is a harmless no-op for unknown IDs; never fetch again.
            return Promise.resolve(Object.assign({}, selected ? selected.address : {}));
        }
    });

    // WooCommerce does not call the provider for text shorter than 3 characters.
    // Observe standard fields only to reset sessions; WooCommerce still owns them.
    window.addEventListener('input', function (event) {
        var input = event.target;
        var match = /^(billing|shipping)[_-]address_1$/.exec(input && (input.name || input.id) || '');
        if (!match) {
            return;
        }
        var at = Date.now();
        [match[1], 'checkout'].forEach(function (slot) {
            var session = sessions.get(slot);
            if (!value(input.value).trim() || session && at - session.last >= 180000) {
                sessions.delete(slot);
                cancel(slot);
            } else if (session) {
                session.last = at;
            }
        });
    }, true);

    window.addEventListener('pagehide', function () {
        pending.forEach(function (request, slot) { cancel(slot); });
    });
}());
