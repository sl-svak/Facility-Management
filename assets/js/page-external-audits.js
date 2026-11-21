(function () {
    'use strict';

    var categoryEl = document.getElementById('ext_category_id');
    var intervalEl = document.getElementById('ext_interval_months');
    var deadlineEl = document.getElementById('ext_deadline_type');

    function applyCategoryDefaults() {
        if (!categoryEl) {
            return;
        }
        var opt = categoryEl.options[categoryEl.selectedIndex];
        if (!opt) {
            return;
        }
        var interval = parseInt(opt.getAttribute('data-interval'), 10);
        if (intervalEl && interval > 0) {
            intervalEl.value = String(interval);
        }
        var deadline = opt.getAttribute('data-deadline');
        if (deadlineEl && (deadline === 'month' || deadline === 'strict')) {
            deadlineEl.value = deadline;
        }
    }

    if (categoryEl) {
        categoryEl.addEventListener('change', applyCategoryDefaults);
        applyCategoryDefaults();
    }
})();
