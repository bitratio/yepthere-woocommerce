// Offline contract checks against WooCommerce's actual shared registration code.
// Usage: node tests/provider-contract.cjs /path/to/wc-src
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const timers = new Map();
let timerId = 0;
let calls = [];
let respond;
const events = {};
const window = {
    yepthereAAProvider: { key: 'sab_live_test_only', limit: 6 },
    wc_address_autocomplete_common_params: { address_providers: [{ id: 'yepthere' }] },
    setTimeout(fn, ms) { timers.set(++timerId, { fn, ms }); return timerId; },
    clearTimeout(id) { timers.delete(id); },
    addEventListener(name, fn) { events[name] = fn; }
};
const context = vm.createContext({
    window, console, AbortController,
    fetch(url, options) {
        calls.push({ url, options });
        return respond(url, options);
    }
});
vm.runInContext(fs.readFileSync(path.join(process.argv[2], 'assets/js/frontend/utils/address-autocomplete-common.js'), 'utf8'), context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../assets/provider.js'), 'utf8'), context);
const provider = window.wc.addressAutocomplete.providers.yepthere;
const plain = value => JSON.parse(JSON.stringify(value));
const tick = ms => {
    for (const [id, timer] of [...timers]) {
        if (timer.ms === ms) { timers.delete(id); timer.fn(); }
    }
};
const flush = () => new Promise(resolve => setImmediate(resolve));
const record = { line1: '12 Test Street', line2: 'Unit 2', suburb: 'Darwin', state: 'NT', postcode: '0800', uprn: '123' };
const success = results => Promise.resolve({ ok: true, json: async () => ({ ok: true, results }) });

(async () => {
    assert(provider && Object.isFrozen(provider), 'registered using actual WooCommerce registry');
    assert.equal(provider.canSearch('AU'), true);
    for (const country of ['NZ', '', undefined, 'au']) assert.equal(provider.canSearch(country), false);
    assert.deepEqual(plain(await provider.search('12 Test', 'NZ')), []);
    assert.deepEqual(plain(await provider.search('12', 'AU')), []);
    assert.equal(calls.length, 0);

    respond = () => success([record, { ...record, line2: null }, null, {}]);
    const first = provider.search('12 Test', 'AU', 'billing');
    tick(250);
    const suggestions = await first;
    assert.equal(suggestions.length, 2);
    assert.equal(suggestions[0].label, '12 Test Street, Unit 2, Darwin, NT, 0800');
    assert.deepEqual(plain(suggestions[0].matchedSubstrings), [{ offset: 0, length: 7 }]);
    assert.equal(calls[0].url, 'https://yepthere.au/v1/addresses?q=12%20Test&limit=6');
    assert(!calls[0].url.includes(window.yepthereAAProvider.key));
    assert.equal(calls[0].options.headers['x-api-key'], window.yepthereAAProvider.key);
    assert.equal(calls[0].options.credentials, 'omit');
    assert.equal(calls[0].options.method, 'GET');
    assert.deepEqual(plain(await provider.select(suggestions[0].id)), {
        address_1: '12 Test Street', address_2: 'Unit 2', city: 'Darwin', state: 'NT', postcode: '0800', country: 'AU'
    });
    assert.equal((await provider.select(suggestions[1].id)).address_2, '');
    const selected = await provider.select(suggestions[0].id);
    selected.city = 'mutated';
    assert.equal((await provider.select(suggestions[0].id)).city, 'Darwin');
    assert.deepEqual(plain(await provider.select('unknown')), {});
    assert.equal(calls.length, 1, 'select makes no request');

    const debounced = provider.search('Old search', 'AU');
    const replacement = provider.search('New search', 'AU');
    assert.deepEqual(plain(await debounced), []);
    tick(250);
    await replacement;
    assert.equal(calls.length, 2, 'superseded debounce never sends');

    let releaseOld;
    respond = () => new Promise(resolve => { releaseOld = resolve; });
    const slow = provider.search('Slow search', 'AU');
    tick(250);
    const oldSignal = calls.at(-1).options.signal;
    respond = () => success([record]);
    const newer = provider.search('Newer search', 'AU');
    assert.equal(oldSignal.aborted, true);
    assert.deepEqual(plain(await slow), []);
    tick(250);
    await newer;
    releaseOld(await success([record]));
    await flush();
    assert.equal((await provider.select(suggestions[0].id)).postcode, '0800', 'another search retains visible IDs');

    for (const response of [
        () => Promise.reject(new Error('offline')),
        () => Promise.resolve({ ok: false }),
        () => Promise.resolve({ ok: true, json: async () => { throw new Error('bad JSON'); } }),
        () => Promise.resolve({ ok: true, json: async () => ({ ok: false, results: [record] }) }),
        () => Promise.resolve({ ok: true, json: async () => ({ ok: true, results: {} }) })
    ]) {
        respond = response;
        const failed = provider.search('12 Test', 'AU'); tick(250);
        assert.deepEqual(plain(await failed), []);
    }

    respond = () => new Promise(() => {});
    const timeout = provider.search('Timeout test', 'AU'); tick(250); tick(8000);
    assert.deepEqual(plain(await timeout), []);
    assert.equal(calls.at(-1).options.signal.aborted, true);

    respond = () => success([record]);
    const billing = provider.search('Billing address', 'AU', 'billing');
    const shipping = provider.search('Shipping address', 'AU', 'shipping');
    tick(250);
    assert.equal((await billing).length, 1);
    assert.equal((await shipping).length, 1);
    const leaving = provider.search('Leaving page', 'AU');
    events.pagehide();
    assert.deepEqual(plain(await leaving), []);
    assert.equal(timers.size, 0);
    console.log('PASS: real WC registration, AU gating, mapping, key transport, selection, debounce/abort, stale results, failures, timeout and form isolation.');
})().catch(error => { console.error(error); process.exitCode = 1; });
