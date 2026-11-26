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
    const future = document.getElementById('tlcd-future');
    const planTable = document.getElementById('tlcd-plan-table');
    const planTemplate = document.getElementById('tlcd-plan-template');
    const addPlan = document.getElementById('tlcd-add-plan');
    let nextIndex = planTable.querySelectorAll('[data-plan-row]').length;
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
        updatePlan();
    }
    function updatePlan() {
        const planRows = [...planTable.querySelectorAll('[data-plan-row]')];
        const futureRows = planRows.filter(row => row.dataset.kind === 'future');
        const linked = new Set();
        // Explicit binding consumes the new topic's default row, not its reservation twice.
        futureRows.forEach(row => {
            const select = row.querySelector('.tlcd-plan-link');
            const option = select.selectedOptions[0];
            if (option && option.dataset.published === '1') linked.add(option.value);
        });
        let topics = 0, target = 0, created = 0, pending = 0;
        planRows.forEach(row => {
            const actualRow = row.dataset.kind === 'actual';
            const replaced = actualRow && linked.has(row.dataset.topicId);
            row.hidden = replaced;
            if (actualRow) row.querySelectorAll('input').forEach(input => {input.disabled = replaced;});
            if (replaced) return;
            let actual = Number(row.dataset.actual) || 0;
            if (!actualRow) {
                const select = row.querySelector('.tlcd-plan-link');
                const option = select.selectedOptions[0];
                const isLinked = option && option.dataset.published === '1';
                actual = isLinked ? (Number(option.dataset.actual) || 0) : 0;
                if (!isLinked) pending++;
                const title = row.querySelector('.tlcd-plan-title');
                title.required = !isLinked;
                row.querySelector('.tlcd-plan-actual').textContent = actual;
                [...select.options].forEach(o => {
                    o.disabled = o.dataset.published === '1' && o.value !== select.value && linked.has(o.value);
                });
                row.querySelector('.tlcd-remove-plan').hidden = false;
            }
            const input = row.querySelector('.tlcd-plan-target');
            input.min = Math.max(1, actual);
            const count = Math.max(1, actual, Number(input.value) || 0);
            row.querySelector('.tlcd-plan-missing').textContent = count - actual;
            topics++;target += count;created += actual;
        });
        planTopics.textContent = topics;
        planActivities.textContent = target;
        document.getElementById('tlcd-created-activities').textContent = created;
        document.getElementById('tlcd-missing-activities').textContent = Math.max(0,target-created);
        document.getElementById('tlcd-plan-empty').hidden = topics > 0;
        future.hidden = pending === 0;
        document.getElementById('tlcd-future-count').textContent = pending;
    }
    addPlan.hidden = false;
    addPlan.addEventListener('click', () => {
        if (planTable.querySelectorAll('[data-plan-row]').length >= 1000) return;
        const fragment = planTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-plan-row]');
        const index = String(nextIndex++);
        row.querySelectorAll('[name],[id],[for]').forEach(el => {
            ['name','id','for'].forEach(attr => {
                if (el.hasAttribute(attr)) el.setAttribute(attr,el.getAttribute(attr).replaceAll('__INDEX__',index));
            });
        });
        planTable.insertBefore(fragment,planTemplate);
        markDirty(true);updatePlan();
        row.querySelector('.tlcd-plan-title').focus();
    });
    planTable.addEventListener('click', event => {
        const button = event.target.closest('.tlcd-remove-plan');
        if (!button) return;
        button.closest('[data-plan-row]').remove();
        markDirty(true);updatePlan();
    });
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
