/**
 * Rapid — storefront quick-order form.
 *
 * Progressive enhancement: the form works without JavaScript (it renders the
 * first page of products and the submit batches them server-side). This script
 * upgrades the search box to query products via AJAX and rebuild the table in
 * place, preserving any quantities already typed.
 *
 * No dependencies.
 */
(function () {
	'use strict';

	var data = window.rapidData || {};
	var root = document.querySelector('.rapid');

	if (!root || !data.ajaxUrl || !data.nonce || !data.action) {
		return;
	}

	var i18n = data.i18n || {};
	var search = root.querySelector('.rapid__search');
	var body = root.querySelector('.rapid__body');
	var status = root.querySelector('.rapid__status');

	var showImage = root.getAttribute('data-show-image') === '1';
	var showSku = root.getAttribute('data-show-sku') === '1';
	var showPrice = root.getAttribute('data-show-price') === '1';
	var showStock = root.getAttribute('data-show-stock') === '1';

	var debounceTimer = null;
	var currentController = null;

	function setStatus(message) {
		if (status) {
			status.textContent = message || '';
		}
	}

	// Capture quantities already entered so a re-render does not lose them.
	function capturedQuantities() {
		var map = {};
		var inputs = body.querySelectorAll('.rapid__qty');

		Array.prototype.forEach.call(inputs, function (input) {
			var name = input.getAttribute('name') || '';
			var match = name.match(/rapid_qty\[(\d+)\]/);

			if (match && input.value && input.value !== '0') {
				map[match[1]] = input.value;
			}
		});

		return map;
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (text !== undefined && text !== null) {
			node.textContent = text;
		}
		return node;
	}

	function buildRow(product, quantities) {
		var tr = el('tr', 'rapid__row');
		var id = String(product.id);

		if (showImage) {
			var imgCell = el('td', 'rapid__col-image');
			imgCell.setAttribute('data-label', '');
			var img = document.createElement('img');
			img.src = product.imageUrl || '';
			img.alt = '';
			img.width = 48;
			img.height = 48;
			img.loading = 'lazy';
			img.decoding = 'async';
			imgCell.appendChild(img);
			tr.appendChild(imgCell);
		}

		var nameCell = el('td', 'rapid__col-name');
		nameCell.setAttribute('data-label', 'Product');
		if (product.permalink) {
			var link = el('a', null, product.name);
			link.href = product.permalink;
			nameCell.appendChild(link);
		} else {
			nameCell.textContent = product.name;
		}
		tr.appendChild(nameCell);

		if (showSku) {
			var skuCell = el('td', 'rapid__col-sku', product.sku || '');
			skuCell.setAttribute('data-label', 'SKU');
			tr.appendChild(skuCell);
		}

		if (showPrice) {
			var priceCell = el('td', 'rapid__col-price');
			priceCell.setAttribute('data-label', 'Price');
			// priceHtml is WooCommerce-generated price markup; safe to inject.
			priceCell.innerHTML = product.priceHtml || '';
			tr.appendChild(priceCell);
		}

		if (showStock) {
			var stockCell = el('td', 'rapid__col-stock', product.stockHtml || '');
			stockCell.setAttribute('data-label', 'Stock');
			tr.appendChild(stockCell);
		}

		var qtyCell = el('td', 'rapid__col-qty');
		qtyCell.setAttribute('data-label', 'Quantity');

		var label = el('label', 'screen-reader-text', 'Quantity');
		label.setAttribute('for', 'rapid-qty-' + id);
		qtyCell.appendChild(label);

		var input = document.createElement('input');
		input.type = 'number';
		input.min = '0';
		input.step = '1';
		input.inputMode = 'numeric';
		input.id = 'rapid-qty-' + id;
		input.name = 'rapid_qty[' + id + ']';
		input.className = 'rapid__qty';
		input.value = quantities[id] || '0';
		if (!product.inStock) {
			input.disabled = true;
		}
		qtyCell.appendChild(input);
		tr.appendChild(qtyCell);

		return tr;
	}

	function render(products) {
		var quantities = capturedQuantities();
		body.textContent = '';

		if (!products || !products.length) {
			var tr = el('tr', 'rapid__empty-row');
			var td = el('td', null, i18n.noResults || 'No products found.');
			td.setAttribute('colspan', '99');
			tr.appendChild(td);
			body.appendChild(tr);
			return;
		}

		var fragment = document.createDocumentFragment();
		products.forEach(function (product) {
			fragment.appendChild(buildRow(product, quantities));
		});
		body.appendChild(fragment);
	}

	function fetchProducts() {
		var term = search ? search.value.trim() : '';

		if (currentController && typeof currentController.abort === 'function') {
			currentController.abort();
		}

		var params = new URLSearchParams();
		params.set('action', data.action);
		params.set('nonce', data.nonce);
		params.set('term', term);

		setStatus(i18n.searching || 'Searching…');

		var options = {};
		if (typeof AbortController === 'function') {
			currentController = new AbortController();
			options.signal = currentController.signal;
		}

		fetch(data.ajaxUrl + '?' + params.toString(), options)
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (payload && payload.success && payload.data) {
					render(payload.data.products || []);
					setStatus('');
				} else {
					setStatus((payload && payload.data && payload.data.message) || i18n.error || 'Error');
				}
			})
			.catch(function (error) {
				if (error && error.name === 'AbortError') {
					return;
				}
				setStatus(i18n.error || 'Error');
			});
	}

	function debouncedFetch() {
		window.clearTimeout(debounceTimer);
		debounceTimer = window.setTimeout(fetchProducts, 280);
	}

	if (search) {
		search.addEventListener('input', debouncedFetch);
	}

	// --- Presentation only: dispatch feedback. Does not alter submit behaviour. ---
	var form = root.querySelector('.rapid__form');
	var submit = root.querySelector('.rapid__submit');
	var tallyCount = root.querySelector('.rapid__tally-count');

	// Mark a row as queued and keep the running tally in sync. Rows with a
	// quantity get the amber edge so the buyer watches the order build.
	function syncTally() {
		var inputs = body.querySelectorAll('.rapid__qty');
		var queued = 0;

		Array.prototype.forEach.call(inputs, function (input) {
			var qty = parseInt(input.value, 10);
			var hasQty = !input.disabled && qty > 0;
			var row = input.closest ? input.closest('.rapid__row') : null;

			if (row) {
				row.classList.toggle('is-queued', hasQty);
			}
			if (hasQty) {
				queued += 1;
			}
		});

		if (tallyCount) {
			tallyCount.textContent = String(queued);
		}
	}

	// Delegate so re-rendered rows (after a search) stay wired up.
	root.addEventListener('input', function (event) {
		if (event.target && event.target.classList && event.target.classList.contains('rapid__qty')) {
			syncTally();
		}
	});

	// Fire the velocity streak when the order is dispatched, then mark busy.
	if (form && submit) {
		form.addEventListener('submit', function () {
			submit.classList.remove('is-launching');
			// Force reflow so the animation restarts on repeat submits.
			void submit.offsetWidth;
			submit.classList.add('is-launching');
			submit.setAttribute('aria-busy', 'true');
		});
	}

	syncTally();
})();
