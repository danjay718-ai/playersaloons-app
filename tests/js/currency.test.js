import assert from 'node:assert/strict';
import test from 'node:test';
import { formatMoney } from '../../resources/js/currency.js';

test('Euro and Pound previews keep the same numeric amount', () => {
    assert.equal(formatMoney(1234.5, 'EUR'), '€1,234.50');
    assert.equal(formatMoney(1234.5, 'GBP'), '£1,234.50');
    assert.equal(formatMoney(-25, 'GBP'), '-£25.00');
    assert.equal(formatMoney('', 'EUR'), '€0.00');
});

test('previews read the configured currency after navigation', (t) => {
    let currency = 'EUR';
    globalThis.document = { querySelector: () => ({ content: currency }) };
    t.after(() => { delete globalThis.document; });
    assert.equal(formatMoney(10), '€10.00');
    currency = 'GBP';
    assert.equal(formatMoney(10), '£10.00');
});
