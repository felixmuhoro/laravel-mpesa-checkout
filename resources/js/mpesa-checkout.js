/**
 * mpesa-checkout.js
 * Vanilla JS — no dependencies.
 * Handles the M-Pesa STK Push modal + standalone page flow.
 *
 * Public API:
 *   MpesaCheckout.openModal(modalId)
 *   MpesaCheckout.closeModal(modalId)
 *   MpesaCheckout.initiatePayment(modalId)
 *   MpesaCheckout.cancelPayment(modalId)
 *   MpesaCheckout.retryPayment(modalId)
 *   MpesaCheckout.initPage(containerEl)
 *   MpesaCheckout.initiatePage()
 *   MpesaCheckout.cancelPage()
 *   MpesaCheckout.resetPage()
 *   MpesaCheckout.pageRedirect(outcome)  // 'success' | 'cancel'
 */

(function (global) {
    'use strict';

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    function getCsrfToken() {
        return (
            global._mpesaCsrfToken ||
            document.querySelector('meta[name="csrf-token"]')?.content ||
            ''
        );
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(body),
        }).then(r => r.json());
    }

    function del(url) {
        return fetch(url, {
            method: 'DELETE',
            headers: {
                'Accept':       'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        }).then(r => r.json());
    }

    function get(url) {
        return fetch(url, {
            headers: { 'Accept': 'application/json' },
        }).then(r => r.json());
    }

    function formatTime(ms) {
        const s   = Math.ceil(ms / 1000);
        const m   = Math.floor(s / 60);
        const sec = s % 60;
        return m + ':' + String(sec).padStart(2, '0');
    }

    function normalisePhone(raw) {
        let digits = raw.replace(/\D/g, '');
        if (digits.startsWith('0') && digits.length === 10) {
            digits = '254' + digits.slice(1);
        }
        if (digits.startsWith('+')) {
            digits = digits.slice(1);
        }
        if (!digits.startsWith('254')) {
            digits = '254' + digits;
        }
        return digits;
    }

    function displayPhone(raw) {
        return '+' + normalisePhone(raw);
    }

    // -------------------------------------------------------------------------
    // Modal state registry
    // -------------------------------------------------------------------------

    const _state = new Map();

    function getState(modalId) {
        if (!_state.has(modalId)) {
            _state.set(modalId, {
                sessionId:      null,
                pollTimer:      null,
                countdownTimer: null,
                totalMs:        120000,
                elapsed:        0,
                pollUrl:        null,
                cancelUrl:      null,
                pollInterval:   3000,
                pollTimeout:    120000,
                onSuccess:      null,
                onFail:         null,
            });
        }
        return _state.get(modalId);
    }

    function getModal(modalId) {
        return document.getElementById(modalId);
    }

    function getByBind(root, name) {
        return root.querySelector('[data-bind="' + name + '"]');
    }

    function showState(modal, stateName) {
        modal.querySelectorAll('.mpesa-state').forEach(el => {
            el.style.display = el.dataset.state === stateName ? '' : 'none';
        });
    }

    function showError(modal, msg) {
        const el = modal.querySelector('.mpesa-error');
        if (el) { el.textContent = msg; el.style.display = msg ? '' : 'none'; }
    }

    function clearError(modal) { showError(modal, ''); }

    // -------------------------------------------------------------------------
    // Modal open / close
    // -------------------------------------------------------------------------

    function openModal(modalId) {
        const modal = getModal(modalId);
        if (!modal) return;

        const state       = getState(modalId);
        state.pollUrl     = modal.dataset.pollUrl;
        state.cancelUrl   = modal.dataset.cancelUrl;
        state.pollInterval= parseInt(modal.dataset.pollInterval || '3000', 10);
        state.pollTimeout = parseInt(modal.dataset.pollTimeout  || '120000', 10);
        state.onSuccess   = modal.dataset.onSuccess || null;
        state.onFail      = modal.dataset.onFail    || null;

        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(() => {
            const input = modal.querySelector('.mpesa-input');
            if (input) input.focus();
        });

        modal.addEventListener('click', overlayClickHandler);
        document.addEventListener('keydown', escKeyHandler);
    }

    function closeModal(modalId) {
        const modal = getModal(modalId);
        if (!modal) return;

        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
        document.body.style.overflow = '';

        modal.removeEventListener('click', overlayClickHandler);
        document.removeEventListener('keydown', escKeyHandler);

        setTimeout(() => resetModal(modalId), 250);
    }

    function overlayClickHandler(e) {
        if (e.target === e.currentTarget) {
            const id = e.currentTarget.id;
            const st = getState(id);
            if (st.pollTimer) return;
            closeModal(id);
        }
    }

    function escKeyHandler(e) {
        if (e.key !== 'Escape') return;
        const open = document.querySelector('.mpesa-overlay.is-open');
        if (open) {
            const st = getState(open.id);
            if (!st.pollTimer) closeModal(open.id);
        }
    }

    function resetModal(modalId) {
        const modal = getModal(modalId);
        if (!modal) return;

        stopPolling(modalId);
        showState(modal, 'phone-input');
        clearError(modal);

        const input = modal.querySelector('.mpesa-input');
        if (input) {
            input.value = modal.dataset.phone
                ? modal.dataset.phone.replace(/^(\+?254|0)/, '')
                : '';
        }

        const st    = getState(modalId);
        st.sessionId = null;
        st.elapsed   = 0;
    }

    // -------------------------------------------------------------------------
    // Payment initiation (modal)
    // -------------------------------------------------------------------------

    function initiatePayment(modalId) {
        const modal = getModal(modalId);
        if (!modal) return;

        clearError(modal);

        const input    = modal.querySelector('.mpesa-input');
        const rawPhone = (input ? input.value.trim() : '') || modal.dataset.phone || '';

        if (!rawPhone || rawPhone.replace(/\D/g, '').length < 9) {
            showError(modal, 'Please enter a valid M-Pesa phone number (9 digits).');
            if (input) input.focus();
            return;
        }

        const btn = modal.querySelector('.mpesa-submit-btn');
        if (btn) {
            btn.disabled = true;
            const txt = btn.querySelector('.mpesa-submit-btn__text');
            if (txt) txt.textContent = 'Sending…';
        }

        const phone = normalisePhone(rawPhone);

        post(modal.dataset.initiateUrl, {
            amount:      parseFloat(modal.dataset.amount),
            phone:       phone,
            reference:   modal.dataset.reference,
            description: modal.dataset.description,
        })
        .then(data => {
            if (btn) {
                btn.disabled = false;
                const txt = btn.querySelector('.mpesa-submit-btn__text');
                if (txt) txt.textContent = 'Send Payment Request';
            }

            if (!data.success || data.status === 'failed') {
                const msg = data.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data.message || 'Could not initiate payment. Please try again.');
                showError(modal, msg);
                return;
            }

            const st      = getState(modalId);
            st.sessionId  = data.session_id;
            st.pollUrl    = modal.dataset.pollUrl.replace('__SESSION_ID__', data.session_id);
            st.cancelUrl  = modal.dataset.cancelUrl.replace('__SESSION_ID__', data.session_id);
            st.elapsed    = 0;
            st.totalMs    = st.pollTimeout;

            const displayPhoneEl = getByBind(modal, 'display-phone');
            if (displayPhoneEl) displayPhoneEl.textContent = displayPhone(phone);

            showState(modal, 'processing');
            startCountdown(modalId);
            startPolling(modalId);
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                const txt = btn.querySelector('.mpesa-submit-btn__text');
                if (txt) txt.textContent = 'Send Payment Request';
            }
            showError(modal, 'Network error. Please check your connection.');
            console.error('[MpesaCheckout]', err);
        });
    }

    // -------------------------------------------------------------------------
    // Polling
    // -------------------------------------------------------------------------

    function startPolling(modalId) {
        const st = getState(modalId);
        if (st.pollTimer) return;

        st.pollTimer = setInterval(() => {
            if (!st.sessionId || !st.pollUrl) { stopPolling(modalId); return; }

            get(st.pollUrl)
                .then(data => handlePollResult(modalId, data))
                .catch(err => console.warn('[MpesaCheckout] poll error', err));
        }, st.pollInterval);
    }

    function stopPolling(modalId) {
        const st = getState(modalId);
        if (st.pollTimer)      { clearInterval(st.pollTimer);      st.pollTimer      = null; }
        if (st.countdownTimer) { clearInterval(st.countdownTimer); st.countdownTimer = null; }
    }

    function handlePollResult(modalId, data) {
        if (!data.is_terminal) return;

        stopPolling(modalId);

        if (data.is_success) {
            showModalSuccess(modalId, data.receipt);
        } else if (data.status === 'expired') {
            showModalState(modalId, 'expired');
            fireCallback(modalId, 'fail', { status: 'expired' });
        } else {
            showModalFailed(modalId, data.failure_reason || data.label);
        }
    }

    function showModalSuccess(modalId, receipt) {
        const modal = getModal(modalId);
        showState(modal, 'success');

        if (receipt) {
            const block = getByBind(modal, 'receipt-block');
            const code  = getByBind(modal, 'receipt-number');
            if (block) block.style.display = 'flex';
            if (code)  code.textContent    = receipt;
        }

        fireCallback(modalId, 'success', { receipt });
    }

    function showModalFailed(modalId, reason) {
        const modal = getModal(modalId);
        showState(modal, 'failed');

        const el = getByBind(modal, 'failure-reason');
        if (el) el.textContent = reason || 'The payment could not be completed.';

        fireCallback(modalId, 'fail', { reason });
    }

    function showModalState(modalId, stateName) {
        const modal = getModal(modalId);
        if (modal) showState(modal, stateName);
    }

    function fireCallback(modalId, type, detail) {
        const st   = getState(modalId);
        const name = type === 'success' ? st.onSuccess : st.onFail;

        if (name && typeof global[name] === 'function') {
            global[name](detail);
        }

        const modal = getModal(modalId);
        if (modal) {
            modal.dispatchEvent(new CustomEvent('mpesa:' + type, { detail, bubbles: true }));
        }
    }

    function cancelPayment(modalId) {
        const st = getState(modalId);
        stopPolling(modalId);

        if (st.sessionId && st.cancelUrl) {
            del(st.cancelUrl).catch(() => {});
        }

        resetModal(modalId);
    }

    function retryPayment(modalId) {
        resetModal(modalId);
    }

    // -------------------------------------------------------------------------
    // Countdown timer (modal)
    // -------------------------------------------------------------------------

    function startCountdown(modalId) {
        const st  = getState(modalId);
        st.elapsed = 0;

        st.countdownTimer = setInterval(() => {
            st.elapsed += 1000;

            const modal    = getModal(modalId);
            const timerBar = modal ? getByBind(modal, 'timer-bar') : null;
            const timerLbl = modal ? getByBind(modal, 'timer-label') : null;

            const remaining = Math.max(0, st.totalMs - st.elapsed);
            const pct       = (remaining / st.totalMs) * 100;

            if (timerBar) timerBar.style.width   = pct + '%';
            if (timerLbl) timerLbl.textContent   = formatTime(remaining);

            if (st.elapsed >= st.totalMs) {
                stopPolling(modalId);
                showModalState(modalId, 'expired');
                fireCallback(modalId, 'fail', { status: 'expired' });
            }
        }, 1000);
    }

    // -------------------------------------------------------------------------
    // Standalone page flow
    // -------------------------------------------------------------------------

    let _page          = null;
    let _pagePollTimer = null;
    let _pageCountdown = null;
    let _pageSessionId = null;
    let _pageElapsed   = 0;

    function initPage(containerEl) {
        _page = containerEl;
    }

    function initiatePage() {
        if (!_page) return;

        const errorEl   = document.getElementById('page-error');
        const phoneIn   = document.getElementById('page-phone');
        const submitBtn = document.getElementById('page-submit-btn');

        if (errorEl) { errorEl.textContent = ''; errorEl.style.display = 'none'; }

        const rawPhone = (phoneIn ? phoneIn.value.trim() : '') || _page.dataset.phone || '';

        if (!rawPhone || rawPhone.replace(/\D/g, '').length < 9) {
            if (errorEl) { errorEl.textContent = 'Please enter a valid M-Pesa number.'; errorEl.style.display = ''; }
            if (phoneIn) phoneIn.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            const sp = submitBtn.querySelector('span');
            if (sp) sp.textContent = 'Sending…';
        }

        const phone = normalisePhone(rawPhone);

        post(_page.dataset.initiateUrl, {
            amount:      parseFloat(_page.dataset.amount),
            phone:       phone,
            reference:   _page.dataset.reference,
            description: _page.dataset.description,
        })
        .then(data => {
            if (submitBtn) {
                submitBtn.disabled = false;
                const sp = submitBtn.querySelector('span');
                if (sp) sp.textContent = 'Send Payment Request';
            }

            if (!data.success || data.status === 'failed') {
                if (errorEl) {
                    errorEl.textContent = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message || 'Could not initiate. Please try again.');
                    errorEl.style.display = '';
                }
                return;
            }

            const displayPhoneEl = document.getElementById('page-display-phone');
            if (displayPhoneEl) displayPhoneEl.textContent = displayPhone(phone);

            setPageState('processing');
            startPageCountdown();
            startPagePolling(data.session_id);
        })
        .catch(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                const sp = submitBtn.querySelector('span');
                if (sp) sp.textContent = 'Send Payment Request';
            }
            if (errorEl) { errorEl.textContent = 'Network error. Please check your connection.'; errorEl.style.display = ''; }
        });
    }

    function startPagePolling(sessionId) {
        if (!_page) return;

        _pageSessionId = sessionId;
        const pollUrl  = _page.dataset.pollUrl.replace('__SESSION_ID__', sessionId);
        const interval = parseInt(_page.dataset.pollInterval || '3000', 10);

        _pagePollTimer = setInterval(() => {
            get(pollUrl)
                .then(data => {
                    if (!data.is_terminal) return;

                    stopPageTimers();

                    if (data.is_success) {
                        const receiptBox = document.getElementById('page-receipt-box');
                        const receiptNum = document.getElementById('page-receipt-number');

                        if (data.receipt && receiptBox && receiptNum) {
                            receiptBox.style.display = 'flex';
                            receiptNum.textContent   = data.receipt;
                        }
                        setPageState('success');
                    } else if (data.status === 'expired') {
                        setPageState('expired');
                    } else {
                        const el = document.getElementById('page-failure-reason');
                        if (el) el.textContent = data.failure_reason || data.label || 'Payment failed.';
                        setPageState('failed');
                    }
                })
                .catch(() => {});
        }, interval);
    }

    function startPageCountdown() {
        if (!_page) return;

        _pageElapsed  = 0;
        const totalMs = parseInt(_page.dataset.pollTimeout || '120000', 10);
        const bar     = document.getElementById('page-timer-bar');
        const label   = document.getElementById('page-timer-label');

        _pageCountdown = setInterval(() => {
            _pageElapsed += 1000;

            const remaining = Math.max(0, totalMs - _pageElapsed);
            const pct       = (remaining / totalMs) * 100;

            if (bar)   bar.style.width    = pct + '%';
            if (label) label.textContent  = formatTime(remaining);

            if (_pageElapsed >= totalMs) {
                stopPageTimers();
                setPageState('expired');
            }
        }, 1000);
    }

    function stopPageTimers() {
        if (_pagePollTimer) { clearInterval(_pagePollTimer); _pagePollTimer = null; }
        if (_pageCountdown) { clearInterval(_pageCountdown); _pageCountdown = null; }
    }

    function cancelPage() {
        stopPageTimers();

        if (_pageSessionId && _page) {
            const cancelUrl = _page.dataset.cancelUrl.replace('__SESSION_ID__', _pageSessionId);
            del(cancelUrl).catch(() => {});
            _pageSessionId = null;
        }

        resetPage();
    }

    function resetPage() {
        stopPageTimers();
        setPageState('phone-input');

        const errorEl = document.getElementById('page-error');
        if (errorEl) { errorEl.textContent = ''; errorEl.style.display = 'none'; }
    }

    function setPageState(name) {
        if (!_page) return;
        _page.querySelectorAll('[data-state]').forEach(el => {
            el.classList.toggle('active', el.dataset.state === name);
        });
    }

    function pageRedirect(outcome) {
        if (!_page) return;
        const url = outcome === 'success'
            ? _page.dataset.redirectSuccess
            : _page.dataset.redirectCancel;

        if (url) window.location.href = url;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    global.MpesaCheckout = {
        openModal,
        closeModal,
        initiatePayment,
        cancelPayment,
        retryPayment,
        initPage,
        initiatePage,
        cancelPage,
        resetPage,
        pageRedirect,
    };

})(window);
