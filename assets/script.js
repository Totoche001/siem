document.addEventListener('DOMContentLoaded', () => {
    const levelFilter = document.getElementById('levelFilter');
    const typeFilter = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');

    function filterAlerts() {
        const level = levelFilter.value.toLowerCase();
        const type = typeFilter.value.toLowerCase();
        const status = statusFilter.value.toLowerCase();

        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
            const lvl = row.classList.contains('critical') ? 'critical' :
                       row.classList.contains('warning') ? 'warning' :
                       row.classList.contains('info') ? 'info' : '';
            const t = row.querySelector('td:nth-child(3)')?.textContent.trim().toLowerCase() || '';
            const s = row.querySelector('td:nth-child(4)')?.textContent.trim().toLowerCase() || '';

            const isVisible = (!level || lvl === level) &&
                            (!type || t === type) &&
                            (!status || s === status);

            row.style.display = isVisible ? '' : 'none';
        });
    }

    if (levelFilter && typeFilter && statusFilter) {
        [levelFilter, typeFilter, statusFilter].forEach(filter => {
            filter.addEventListener('change', filterAlerts);
        });
    }
});