/**
 * N.A Fresh Fruits & Coconuts - Quick 1-Tap Ordering & Sticky Cart Bar
 * Handles AJAX quantity steppers (+/-), sticky ordering bar, and Buy Again feature
 */

(function() {
    'use strict';

    // Helper: update all cart badges on page
    window.updateCartBadges = function(count) {
        var countNum = parseInt(count, 10) || 0;
        var badges = document.querySelectorAll('.cart-count-badge, [data-cart-count]');
        badges.forEach(function(el) {
            el.textContent = countNum;
            if (countNum > 0) {
                el.classList.remove('hidden');
                el.style.display = '';
            } else {
                el.classList.add('hidden');
            }
        });

        // Update Sticky Ordering Bar
        window.updateStickyOrderingBar();
    };

    // Helper: update sticky ordering bar visibility and amounts
    window.updateStickyOrderingBar = function() {
        var bar = document.getElementById('sticky-ordering-bar');
        if (!bar) return;

        fetch('/api/cart/stats', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data || !data.success) return;
            var count = data.count || 0;
            var subtotal = data.subtotal || 0;

            var countEl = bar.querySelector('.sticky-cart-items-count');
            var subtotalEl = bar.querySelector('.sticky-cart-subtotal');

            if (countEl) {
                countEl.textContent = count + (count === 1 ? ' Item' : ' Items');
            }
            if (subtotalEl) {
                subtotalEl.textContent = '₹' + Math.round(subtotal);
            }

            // Show or hide bar based on count and current path (hide on checkout/cart itself)
            var path = window.location.pathname;
            if (count > 0 && path !== '/cart' && path !== '/checkout') {
                bar.classList.remove('translate-y-full', 'opacity-0', 'pointer-events-none');
                bar.classList.add('translate-y-0', 'opacity-100');
            } else {
                bar.classList.add('translate-y-full', 'opacity-0', 'pointer-events-none');
                bar.classList.remove('translate-y-0', 'opacity-100');
            }
        })
        .catch(function() {});
    };

    // Quick set quantity via AJAX
    window.quickSetQty = function(productId, targetQty, btnEl) {
        var card = btnEl ? btnEl.closest('[data-product-card]') : null;
        var stepper = card ? card.querySelector('.product-qty-stepper') : null;
        var addBtn = card ? card.querySelector('.product-initial-add-btn') : null;
        var qtyDisplay = card ? card.querySelector('.product-qty-value') : null;

        // Visual optimistic update
        if (targetQty > 0) {
            if (stepper) stepper.classList.remove('hidden');
            if (addBtn) addBtn.classList.add('hidden');
            if (qtyDisplay) qtyDisplay.textContent = targetQty;
        } else {
            if (stepper) stepper.classList.add('hidden');
            if (addBtn) addBtn.classList.remove('hidden');
            if (qtyDisplay) qtyDisplay.textContent = '0';
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        var formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', targetQty);
        formData.append('csrf_token', csrfToken);

        fetch('/api/cart/quick-update', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (!res.success) {
                alert(res.error || 'Could not update item quantity.');
                // Revert
                window.location.reload();
                return;
            }
            window.updateCartBadges(res.count);
        })
        .catch(function(err) {
            console.error('Error updating cart:', err);
        });
    };

    // Buy Again / Reorder past order handler
    window.handleBuyAgain = function(orderId, btnEl) {
        if (!orderId) return;

        if (btnEl) {
            btnEl.disabled = true;
            btnEl.classList.add('opacity-75');
            var origHtml = btnEl.innerHTML;
            btnEl.innerHTML = '<span class="inline-flex items-center gap-1.5"><svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Reordering...</span>';
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('csrf_token', csrfToken);

        fetch('/api/cart/reorder', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                alert(data.error || 'Unable to reorder items.');
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.classList.remove('opacity-75');
                    btnEl.innerHTML = origHtml;
                }
                return;
            }

            // Redirect smoothly to Cart
            window.location.href = '/cart';
        })
        .catch(function() {
            alert('Network error. Please try again.');
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.classList.remove('opacity-75');
                btnEl.innerHTML = origHtml;
            }
        });
    };

    // Initialize listeners on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        // Initial check for sticky ordering bar
        window.updateStickyOrderingBar();

        // Listen for clicks on stepper buttons
        document.addEventListener('click', function(e) {
            var stepBtn = e.target.closest('[data-quick-step]');
            if (stepBtn) {
                e.preventDefault();
                var productId = stepBtn.getAttribute('data-product-id');
                var delta = parseInt(stepBtn.getAttribute('data-quick-step'), 10);
                var card = stepBtn.closest('[data-product-card]');
                var qtyEl = card ? card.querySelector('.product-qty-value') : null;
                var currentQty = qtyEl ? (parseInt(qtyEl.textContent, 10) || 0) : 0;
                var targetQty = Math.max(0, currentQty + delta);
                window.quickSetQty(productId, targetQty, stepBtn);
                return;
            }

            var addBtn = e.target.closest('[data-quick-add]');
            if (addBtn) {
                e.preventDefault();
                var pId = addBtn.getAttribute('data-product-id');
                window.quickSetQty(pId, 1, addBtn);
                return;
            }

            var buyAgainBtn = e.target.closest('.buy-again-btn');
            if (buyAgainBtn) {
                e.preventDefault();
                var ordId = buyAgainBtn.getAttribute('data-order-id');
                window.handleBuyAgain(ordId, buyAgainBtn);
                return;
            }
        });
    });

})();
