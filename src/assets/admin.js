(() => {
    'use strict';
    const root = document.querySelector('.tlcd-admin');
    const form = document.getElementById('tlcd-policy');
    if (!root || !form) return;
    const rows = [...form.querySelectorAll('.tlcd-row')];
    const enabled = document.getElementById('tlcd-enabled');
    const dirtyText = document.getElementById('tlcd-dirty');
    const planTopics = document.getElementById('tlcd-planned-topics');
    const planActivities = document.getElementById('tlcd-planned-activities');
    const coverage = document.getElementById('tlcd-coverage');
    const future = document.getElementById('tlcd-future');
    let dirty = false;
    function markDirty(value) {
        dirty = value;
        dirtyText.textContent = value ? dirtyText.dataset.dirty : dirtyText.dataset.clean;
        dirtyText.classList.toggle('is-dirty', value);
    }
    function update() {
        let open = 0, locked = 0, relative = 0;
        rows.forEach(row => {
            const mode = row.querySelector('.tlcd-mode').value;
            row.querySelectorAll('.tlcd-option').forEach(option => {
                const active = option.dataset.mode === mode;
                option.hidden = !active;
                option.querySelectorAll('input').forEach(input => {
                    input.disabled = !active;
                    input.required = active && (mode === 'scheduled' || mode === 'enrollment');
                });
            });
            let state = 'inactive';
            if (enabled.checked) {
                if (mode === 'open' || (mode === 'manual' && row.querySelector('.tlcd-manual').checked)) state = 'open';
                else if (mode === 'enrollment') state = 'relative';
                else if (mode === 'scheduled' && row.querySelector('.tlcd-date').value) {
                    state = row.querySelector('.tlcd-date').value <= root.dataset.now ? 'open' : 'scheduled';
                } else state = 'locked';
                if (state === 'open') open++;
                else if (state === 'relative') relative++;
                else locked++;
            }
            const status = row.querySelector('.tlcd-status');
            status.dataset.state = state;
            status.querySelector('.tlcd-status-text').textContent = root.dataset[state];
        });
        document.getElementById('tlcd-open-count').textContent = enabled.checked ? open : '—';
        document.getElementById('tlcd-locked-count').textContent = enabled.checked ? locked : '—';
        document.getElementById('tlcd-relative-count').textContent = enabled.checked ? relative : '—';
        const missing = Math.max(0, (Number(planTopics.value) || 0) - Number(future.dataset.published));
        future.hidden = missing === 0;
        document.getElementById('tlcd-future-count').textContent = missing;
        const actual = Number(coverage.dataset.actual);
        const total = Math.max(actual, Number(planActivities.value) || 0);
        const suffix = coverage.textContent.replace(/^\s*\d+\s*\/\s*\d+\s*/, '');
        coverage.textContent = actual + ' / ' + total + ' ' + suffix;
        document.getElementById('tlcd-coverage-bar').style.width = (total ? Math.min(100, actual * 100 / total) : 0) + '%';
    }
    form.addEventListener('input', event => {
        if (event.target.name && event.target.name.startsWith('policy[')) {markDirty(true);update();}
    });
    form.addEventListener('change', event => {
        if (event.target.name && event.target.name.startsWith('policy[')) {markDirty(true);update();}
    });
    form.addEventListener('submit', () => {markDirty(false);});
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();event.returnValue = '';
    });
    update();
})();
