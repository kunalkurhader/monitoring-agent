(function () {
    'use strict';

    var script = document.currentScript;
    if (!script || !script.dataset.key) return;
    var key = script.dataset.key;
    var endpoint = script.dataset.endpoint || new URL('/api/v1/browser/events', script.src).toString();
    var nativeFetch = window.fetch ? window.fetch.bind(window) : null;
    var viewId = window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
        var random = Math.random() * 16 | 0;
        return (character === 'x' ? random : (random & 3 | 8)).toString(16);
    });
    var queue = [];
    var vitals = { lcp: null, cls: 0, inp: null };
    var sentPageLoad = false;
    var htmxRequests = typeof WeakSet === 'function' ? new WeakSet() : null;
    var htmxStarted = typeof WeakMap === 'function' ? new WeakMap() : null;

    var clean = function (value) {
        try { var url = new URL(value, location.href); return url.origin + url.pathname; } catch (_) { return null; }
    };
    var isCollector = function (value) { return clean(value) === clean(endpoint); };
    var add = function (event) {
        event.view_id = viewId;
        event.page_url = clean(location.href);
        event.occurred_at = new Date().toISOString();
        queue.push(event);
        if (queue.length >= 10) flush();
    };
    var flush = function () {
        if (!queue.length) return;
        var body = JSON.stringify({ key: key, events: queue.splice(0, 50) });
        if (!navigator.sendBeacon || !navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain' }))) {
            if (nativeFetch) nativeFetch(endpoint, { method: 'POST', body: body, headers: { 'Content-Type': 'text/plain' }, keepalive: true, mode: 'cors', credentials: 'omit' }).catch(function () {});
        }
    };
    var observe = function (type, handler) {
        try { new PerformanceObserver(function (list) { list.getEntries().forEach(handler); }).observe({ type: type, buffered: true }); } catch (_) {}
    };
    var requestEvent = function (type, url, method, started, status) {
        add({
            type: type,
            message: String(method || 'GET').toUpperCase(),
            source: clean(url),
            metrics: { duration: Math.max(0, Math.round(performance.now() - started)), status: Number(status || 0) }
        });
    };

    observe('largest-contentful-paint', function (entry) { vitals.lcp = Math.round(entry.startTime); });
    observe('layout-shift', function (entry) { if (!entry.hadRecentInput) vitals.cls += entry.value; });
    observe('event', function (entry) { if (entry.duration > (vitals.inp || 0)) vitals.inp = Math.round(entry.duration); });

    if (nativeFetch) {
        window.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : input.url;
            if (isCollector(url)) return nativeFetch(input, init);
            var started = performance.now();
            var method = (init && init.method) || (typeof input !== 'string' && input.method) || 'GET';
            return nativeFetch(input, init).then(function (response) {
                requestEvent('ajax', url, method, started, response.status);
                return response;
            }, function (error) {
                requestEvent('ajax', url, method, started, 0);
                throw error;
            });
        };
    }

    if (window.XMLHttpRequest) {
        var nativeOpen = XMLHttpRequest.prototype.open;
        var nativeSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) {
            this.__monitoringAgentMethod = method;
            this.__monitoringAgentUrl = url;
            return nativeOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
            var xhr = this;
            var started = performance.now();
            if (!isCollector(xhr.__monitoringAgentUrl)) {
                xhr.addEventListener('loadend', function () {
                    if (!htmxRequests || !htmxRequests.has(xhr)) requestEvent('ajax', xhr.__monitoringAgentUrl, xhr.__monitoringAgentMethod, started, xhr.status);
                }, { once: true });
            }
            return nativeSend.apply(xhr, arguments);
        };
    }

    document.addEventListener('htmx:beforeRequest', function (event) {
        var xhr = event.detail && event.detail.xhr;
        if (xhr && htmxRequests) htmxRequests.add(xhr);
        if (xhr && htmxStarted) htmxStarted.set(xhr, performance.now());
    });
    document.addEventListener('htmx:afterRequest', function (event) {
        var detail = event.detail || {};
        var xhr = detail.xhr;
        var path = detail.pathInfo && (detail.pathInfo.requestPath || detail.pathInfo.finalRequestPath);
        requestEvent('htmx', path || (xhr && xhr.responseURL) || location.href, detail.requestConfig && detail.requestConfig.verb || 'GET', xhr && htmxStarted && htmxStarted.get(xhr) || performance.now(), xhr && xhr.status || 0);
    });

    window.addEventListener('error', function (event) {
        if (event.target !== window) {
            add({ type: 'resource_error', message: 'Failed to load ' + event.target.tagName, source: clean(event.target.src || event.target.href || location.href) });
            return;
        }
        add({ type: 'error', message: String(event.message || 'JavaScript error'), source: clean(event.filename || location.href), line: event.lineno || null, column: event.colno || null });
    }, true);
    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        add({ type: 'unhandled_rejection', message: String(reason && (reason.message || reason) || 'Unhandled promise rejection') });
    });

    var sendPageLoad = function () {
        if (sentPageLoad) return;
        sentPageLoad = true;
        var navigation = performance.getEntriesByType('navigation')[0];
        if (!navigation) return;
        add({ type: 'page_load', message: navigation.type || 'navigate', metrics: {
            load_time: Math.round(navigation.loadEventEnd || performance.now()),
            ttfb: Math.round(navigation.responseStart),
            dns: Math.round(navigation.domainLookupEnd - navigation.domainLookupStart),
            connect: Math.round(navigation.connectEnd - navigation.connectStart),
            dom_interactive: Math.round(navigation.domInteractive),
            lcp: vitals.lcp,
            cls: Math.round(vitals.cls * 1000) / 1000,
            inp: vitals.inp
        }});
        flush();
    };
    window.addEventListener('load', function () { setTimeout(sendPageLoad, 1000); });
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { sendPageLoad(); flush(); } });
    window.addEventListener('pagehide', function () { sendPageLoad(); flush(); });
    setInterval(flush, 10000);
}());
