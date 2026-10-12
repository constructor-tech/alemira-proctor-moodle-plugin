// Run with: node --experimental-vm-modules --test tests/proctor_start_test.cjs
// Exercise the real browser entry point with isolated SDK, DOM and network boundaries.
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

const deferred = () => {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return {promise, resolve, reject};
};

const element = () => {
    const classes = new Set(['proctor-sdk-hidden']);
    const attributes = new Map();
    const children = new Map();
    return {
        classList: {add: (name) => classes.add(name), remove: (name) => classes.delete(name),
            contains: (name) => classes.has(name)},
        setAttribute: (key, value) => attributes.set(key, value),
        removeAttribute: (key) => attributes.delete(key),
        hasAttribute: (key) => attributes.has(key),
        querySelector: (key) => {
            if (!children.has(key)) { children.set(key, element()); }
            return children.get(key);
        },
        addEventListener(name, handler) { this[name] = handler; },
        appendChild() {},
        focus() {},
        disabled: false,
    };
};

const settle = () => new Promise((resolve) => setImmediate(resolve));
const hidden = (node) => node.classList.contains('proctor-sdk-hidden');

async function launch({importGate, tokenGate, initGate = deferred(), initError, rejectInit = false} = {}) {
    const root = element();
    root.dataset = {
        starturl: '/start', tokenurl: '/token', eventurl: '/event', sdksrc: 'sdk',
        errorlead: 'Could not start', errorserver: 'Server error', errortechnical: 'Details',
        timeoutmessage: 'Start timed out', timeoutpendingmessage: 'Still starting; wait or return to quiz',
    };
    const timers = new Map();
    let nextTimer = 0;
    const calls = {imports: 0, tokens: 0, inits: 0, events: [], guards: 0, errors: []};
    let tokenSignal;
    const context = vm.createContext({
        AbortController, DOMException,
        document: {readyState: 'complete', createElement: element},
        window: {
            setTimeout: (fn, ms) => { assert.equal(ms, 30000); timers.set(++nextTimer, fn); return nextTimer; },
            clearTimeout: (id) => timers.delete(id),
            console: {error: (...args) => calls.errors.push(args)},
            addEventListener: () => calls.guards++,
            location: {assign() {}},
        },
        fetch: async(url, options) => {
            if (url === '/token') {
                calls.tokens++;
                tokenSignal = options.signal;
                // Intentionally allow a late reply to prove it cannot start an expired operation.
                if (tokenGate) { await tokenGate.promise; }
                return {ok: true, json: async() => ({serverOrigin: 'server', integrationName: 'test', jwt: 'fake', config: {}})};
            }
            calls.events.push(url);
            return {json: async() => ({})};
        },
    });
    const sdk = new vm.SyntheticModule(['init'], function() {
        this.setExport('init', () => {
            calls.inits++;
            return rejectInit ? Promise.reject(initError) : initGate.promise;
        });
    }, {context});
    await sdk.link(() => {});
    await sdk.evaluate();
    const module = new vm.SourceTextModule(readFileSync(`${__dirname}/../js/proctor.js`, 'utf8'), {
        context,
        importModuleDynamically: async() => {
            calls.imports++;
            if (importGate) { await importGate.promise; }
            return sdk;
        },
    });
    await module.link(() => {});
    await module.evaluate();
    module.namespace.start(root);
    await settle();
    return {
        calls, initGate, timers,
        node: (id) => root.querySelector(`#proctor-sdk-${id}`),
        detail: () => root.querySelector('#proctor-sdk-error').querySelector('#proctor-sdk-error-detail')
            .querySelector('pre').textContent,
        retry: async() => { root.querySelector('#proctor-sdk-retry').click(); await settle(); },
        expire: async() => { const fn = timers.values().next().value; assert.ok(fn); fn(); await settle(); },
        signal: () => tokenSignal,
    };
}

const session = () => {
    const started = deferred();
    return {started, value: {examStarted: started.promise, examFinished: deferred().promise,
        videoUploaded: deferred().promise, appUnmounted: deferred().promise}};
};

test('a stalled SDK download shows a timeout and allows retry without starting the expired operation', async() => {
    const gate = deferred();
    const app = await launch({importGate: gate});
    await app.expire();
    assert.equal(hidden(app.node('intro')), true);
    assert.equal(app.node('intro').hasAttribute('aria-busy'), false);
    assert.equal(hidden(app.node('error')), false);
    assert.equal(hidden(app.node('recover')), false);
    assert.equal(app.detail(), 'Start timed out');
    assert.equal(app.node('retry').disabled, false);
    await app.retry();
    gate.resolve();
    await settle();
    assert.equal(app.calls.tokens, 1);
    assert.equal(app.calls.inits, 1);
});

test('a stalled token request is aborted and its late reply cannot initialise the SDK', async() => {
    const gate = deferred();
    const app = await launch({tokenGate: gate});
    await app.expire();
    assert.equal(app.signal().aborted, true);
    assert.equal(app.detail(), 'Start timed out');
    assert.equal(app.node('retry').disabled, false);
    gate.resolve();
    await settle();
    assert.equal(app.calls.inits, 0);
});

test('a failed SDK download shows its error and allows another download', async() => {
    const gate = deferred();
    const app = await launch({importGate: gate});
    gate.reject(new Error('CDN unavailable'));
    await settle();
    assert.equal(app.detail(), 'CDN unavailable');
    assert.equal(app.node('retry').disabled, false);
    assert.equal(app.timers.size, 0);
    await app.retry();
    assert.equal(app.calls.imports, 2);
    assert.equal(app.calls.tokens, 0);
});

test('a failed token request shows its error without starting the SDK', async() => {
    const gate = deferred();
    const app = await launch({tokenGate: gate});
    gate.reject(new Error('Token unavailable'));
    await settle();
    assert.equal(app.detail(), 'Token unavailable');
    assert.equal(app.node('retry').disabled, false);
    assert.equal(app.timers.size, 0);
    assert.equal(app.calls.inits, 0);
});

test('a late SDK download rejection does not replace the timeout message', async() => {
    const gate = deferred();
    const app = await launch({importGate: gate});
    await app.expire();
    gate.reject(new Error('Late download error'));
    await settle();
    assert.equal(app.detail(), 'Start timed out');
    assert.equal(app.node('retry').disabled, false);
    assert.equal(app.calls.inits, 0);
});

test('a pending SDK init times out without allowing a second init and adopts a late success', async() => {
    const app = await launch();
    await app.expire();
    assert.equal(hidden(app.node('intro')), true);
    assert.equal(hidden(app.node('error')), false);
    assert.equal(app.detail(), 'Still starting; wait or return to quiz');
    assert.equal(app.node('retry').disabled, true);
    await app.retry();
    assert.equal(app.calls.inits, 1);
    const sdkSession = session();
    app.initGate.resolve(sdkSession.value);
    await settle();
    assert.equal(hidden(app.node('error')), true);
    assert.equal(hidden(app.node('recover')), true);
    assert.equal(app.calls.guards, 1);
    sdkSession.started.resolve();
    await settle();
    assert.equal(app.node('frame').src, '/start');
    assert.equal(app.calls.events.length, 1);
});

test('a late init rejection unlocks retry and clears its deadline', async() => {
    const app = await launch();
    await app.expire();
    app.initGate.reject(new Error('SDK unavailable'));
    await settle();
    assert.equal(app.node('retry').disabled, false);
    assert.equal(app.detail(), 'SDK unavailable');
    assert.equal(app.timers.size, 0);
    await app.retry();
    assert.equal(app.calls.inits, 2);
});

for (const error of [null, undefined]) {
    test(`a ${error} rejection still shows an error and allows retry`, async() => {
        const app = await launch({rejectInit: true, initError: error});
        assert.equal(hidden(app.node('error')), false);
        assert.equal(hidden(app.node('recover')), false);
        assert.equal(app.node('retry').disabled, false);
        assert.equal(app.detail(), String(error));
        assert.equal(app.timers.size, 0);
    });
}

test('normal startup clears the deadline and waits for examStarted before opening the quiz', async() => {
    const app = await launch();
    await app.retry();
    assert.equal(app.calls.inits, 1);
    const sdkSession = session();
    app.initGate.resolve(sdkSession.value);
    await settle();
    assert.equal(app.timers.size, 0);
    assert.equal(hidden(app.node('error')), true);
    assert.equal(hidden(app.node('recover')), true);
    assert.equal(app.node('frame').src, undefined);
    sdkSession.started.resolve();
    await settle();
    assert.equal(app.node('frame').src, '/start');
});
