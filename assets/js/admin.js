/**
 * Rapid — admin settings progressive enhancement.
 *
 * Shows or hides the category picker based on the selected product scope.
 * No dependencies.
 */
(function () {
	'use strict';

	var scope = document.querySelector('.rapid-scope-select');
	var row = document.querySelector('.rapid-categories-row');

	if (scope && row) {
		var sync = function () {
			if (scope.value === 'categories') {
				row.removeAttribute('data-hidden');
			} else {
				row.setAttribute('data-hidden', '1');
			}
		};
		scope.addEventListener('change', sync);
		sync();
	}
})();
