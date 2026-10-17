/**
 * Registrations list — bulk selection, slide-over preview drawer and the
 * styled confirm dialog.
 *
 * Progressive enhancement only: with JavaScript disabled the page still
 * filters, sorts, paginates and (via the inline fallback in registrations.php)
 * confirms destructive actions with window.confirm(). Nothing here is required
 * for the list to work.
 */
(function () {
    'use strict';

    var cfg = window.BDST_ADMIN || {
        csrf: '',
        state: {},
        exportUrl: 'registrations_export.php',
        detailUrl: 'registration_json.php'
    };

    var DEFAULT_STATE = { q: '', status: '', sort: 'date', dir: 'desc', per: '15' };

    /** Small DOM builder so rendered data is never interpolated as HTML. */
    function el(tag, attrs, children) {
        var node = document.createElement(tag);

        if (attrs) {
            Object.keys(attrs).forEach(function (key) {
                var value = attrs[key];
                if (value === null || value === undefined || value === false) {
                    return;
                }
                if (key === 'class') {
                    node.className = value;
                } else if (key === 'text') {
                    node.textContent = value;
                } else {
                    node.setAttribute(key, value);
                }
            });
        }

        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(function (child) {
                if (child === null || child === undefined || child === false) {
                    return;
                }
                node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
            });
        }

        return node;
    }

    function formatDate(value) {
        if (!value) {
            return '';
        }
        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return String(value);
        }
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    /* ---------------------------------------------------------------- */
    /* Bulk selection                                                    */
    /* ---------------------------------------------------------------- */

    function initBulk() {
        var form = document.getElementById('bulkForm');
        var bar = document.getElementById('bulkBar');
        var selectAll = document.getElementById('selectAll');
        if (!form) {
            return;
        }

        function boxes() {
            return Array.prototype.slice
                .call(document.querySelectorAll('input[name="ids[]"]'))
                .filter(function (box) { return box.form === form; });
        }

        function sync() {
            var all = boxes();
            var checked = all.filter(function (box) { return box.checked; });

            if (bar) {
                bar.hidden = checked.length === 0;
                var count = bar.querySelector('[data-bulk-count]');
                if (count) {
                    count.textContent = checked.length + ' selected';
                }
            }

            if (selectAll) {
                selectAll.checked = all.length > 0 && checked.length === all.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            }

            all.forEach(function (box) {
                var row = box.closest('tr');
                if (row) {
                    row.classList.toggle('is-selected', box.checked);
                }
            });
        }

        document.addEventListener('change', function (ev) {
            var target = ev.target;
            if (target && target.name === 'ids[]' && target.form === form) {
                sync();
            }
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                boxes().forEach(function (box) { box.checked = selectAll.checked; });
                sync();
            });
        }

        var exportButton = document.getElementById('bulkExport');
        if (exportButton) {
            exportButton.addEventListener('click', function () {
                var ids = boxes()
                    .filter(function (box) { return box.checked; })
                    .map(function (box) { return box.value; });

                if (ids.length === 0) {
                    return;
                }

                var params = [];
                Object.keys(cfg.state || {}).forEach(function (key) {
                    if (key === 'page') {
                        return;
                    }
                    var fallback = DEFAULT_STATE[key] === undefined ? '' : DEFAULT_STATE[key];
                    if (String(cfg.state[key]) !== String(fallback)) {
                        params.push(encodeURIComponent(key) + '=' + encodeURIComponent(cfg.state[key]));
                    }
                });
                params.push('ids=' + encodeURIComponent(ids.join(',')));

                window.location.href = cfg.exportUrl + '?' + params.join('&');
            });
        }

        sync();
    }

    /* ---------------------------------------------------------------- */
    /* Confirm dialog                                                    */
    /* ---------------------------------------------------------------- */

    function initConfirm() {
        var modal = document.getElementById('confirmModal');
        var text = document.getElementById('confirmText');
        var okButton = document.getElementById('confirmOk');
        var cancelButton = document.getElementById('confirmCancel');
        if (!modal || typeof modal.showModal !== 'function') {
            return;
        }

        var pending = null;

        // Replaces the inline window.confirm() fallback.
        window.bdstUiConfirm = function (ev, form, message, submitter) {
            ev.preventDefault();
            pending = { form: form, submitter: submitter };

            text.textContent = message;
            okButton.textContent =
                (submitter && submitter.getAttribute('data-confirm-ok')) ||
                form.getAttribute('data-confirm-ok') ||
                'Confirm';

            modal.showModal();
        };

        cancelButton.addEventListener('click', function () {
            pending = null;
            modal.close();
        });

        okButton.addEventListener('click', function () {
            if (!pending) {
                modal.close();
                return;
            }

            var form = pending.form;
            var submitter = pending.submitter;
            pending = null;
            modal.close();

            // form.submit() does not fire the submit event (so we do not loop)
            // but it also drops the clicked button's name/value — carry it over.
            if (submitter && submitter.name) {
                form.appendChild(el('input', {
                    type: 'hidden',
                    name: submitter.name,
                    value: submitter.value
                }));
            }

            form.submit();
        });
    }

    /* ---------------------------------------------------------------- */
    /* Preview drawer                                                    */
    /* ---------------------------------------------------------------- */

    function initDrawer() {
        var drawer = document.getElementById('regDrawer');
        var scrim = document.getElementById('drawerScrim');
        if (!drawer || !scrim) {
            return;
        }

        var body = document.getElementById('drawerBody');
        var foot = document.getElementById('drawerFoot');
        var headRef = document.getElementById('drawerRef');
        var headTitle = document.getElementById('drawerTitle');
        var headStatus = document.getElementById('drawerStatus');
        var closeButton = document.getElementById('drawerClose');

        var isOpen = false;
        var lastFocus = null;

        function pair(label, value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }
            var fragment = document.createDocumentFragment();
            fragment.appendChild(el('dt', { text: label }));
            fragment.appendChild(el('dd', { text: value }));
            return fragment;
        }

        function section(title, rows) {
            var list = el('dl', { class: 'drawer-list' });
            rows.filter(Boolean).forEach(function (row) { list.appendChild(row); });
            if (list.children.length === 0) {
                return null;
            }
            return el('section', { class: 'drawer-section' }, [
                el('h3', { class: 'drawer-section-title', text: title }),
                list
            ]);
        }

        function chipBlock(title, items) {
            if (!items || items.length === 0) {
                return null;
            }
            return el('section', { class: 'drawer-section' }, [
                el('h3', { class: 'drawer-section-title', text: title }),
                el('div', { class: 'drawer-chips' }, items.map(function (item) {
                    return el('span', { class: 'dchip', text: item });
                }))
            ]);
        }

        function cvBlock(data) {
            var cv = data.cv || {};
            var inner;

            if (!cv.available) {
                inner = el('p', { class: 'drawer-empty', text: 'No CV attached to this registration.' });
            } else if (cv.inline) {
                inner = el('div', { class: 'drawer-frame-wrap' }, [
                    el('iframe', {
                        class: 'drawer-frame',
                        src: 'cv.php?id=' + encodeURIComponent(data.id) + '&inline=1',
                        title: 'CV preview for ' + (data.name || 'applicant'),
                        loading: 'lazy'
                    }),
                    el('p', { class: 'drawer-hint' }, [
                        el('a', {
                            href: 'cv.php?id=' + encodeURIComponent(data.id),
                            text: 'Download ' + (cv.original_name || 'CV')
                        })
                    ])
                ]);
            } else {
                inner = el('p', { class: 'drawer-hint' }, [
                    el('a', {
                        href: 'cv.php?id=' + encodeURIComponent(data.id),
                        text: 'Download ' + (cv.original_name || 'CV')
                    }),
                    document.createTextNode(' — this file type cannot be previewed inline.')
                ]);
            }

            return el('section', { class: 'drawer-section' }, [
                el('h3', { class: 'drawer-section-title', text: 'CV / Résumé' }),
                inner
            ]);
        }

        function statusForm(id, status, label, danger) {
            var form = el('form', {
                method: 'post',
                action: 'registrations.php',
                class: 'inline-form'
            });

            form.appendChild(el('input', { type: 'hidden', name: 'csrf', value: cfg.csrf }));
            form.appendChild(el('input', { type: 'hidden', name: 'id', value: id }));
            form.appendChild(el('input', { type: 'hidden', name: 'status', value: status }));

            Object.keys(cfg.state || {}).forEach(function (key) {
                form.appendChild(el('input', {
                    type: 'hidden',
                    name: 'return_' + key,
                    value: cfg.state[key]
                }));
            });

            form.appendChild(el('button', {
                type: 'submit',
                class: 'btn btn-sm ' + (danger ? 'btn-danger' : 'btn-ghost'),
                text: label
            }));

            return form;
        }

        function buildFoot(data) {
            foot.textContent = '';

            foot.appendChild(el('a', {
                class: 'btn btn-primary btn-sm',
                href: data.url,
                text: 'Open full record'
            }));

            var actions = el('div', { class: 'drawer-foot-actions' });
            [
                ['approved', 'Approve', false],
                ['reviewed', 'Mark under review', false],
                ['rejected', 'Reject', true]
            ].forEach(function (option) {
                if (option[0] === data.status) {
                    return;
                }
                actions.appendChild(statusForm(data.id, option[0], option[1], option[2]));
            });

            foot.appendChild(actions);
        }

        function render(data) {
            headRef.textContent = data.reference || '';
            headTitle.textContent = data.name || 'Registration';
            headStatus.textContent = '';
            headStatus.appendChild(el('span', {
                class: 'badge badge-reg-' + data.status,
                text: data.status_label || data.status
            }));

            var sections = [
                section('Contact', [
                    pair('Email', data.email),
                    pair('Phone', data.phone),
                    pair('Gender', data.gender),
                    pair('Age group', data.age_group)
                ]),
                section('Location', [
                    pair('Region', data.region),
                    pair('District', data.district)
                ]),
                section('Professional profile', [
                    pair('Registration status', data.status_label),
                    pair('Professional status', data.professional_status),
                    pair('Firm', data.firm_name),
                    pair('Experience', data.experience_years),
                    pair('Education', data.education),
                    pair('Clients served', data.clients_served)
                ]),
                chipBlock('Service areas', data.service_areas),
                chipBlock('Industries', data.industries),
                chipBlock('Client segments', data.client_segments),
                chipBlock('Nature of services', data.service_nature),
                chipBlock('Certifications', data.certifications),
                chipBlock('Coverage zones', data.zones),
                section('Timeline and notes', [
                    pair('Received', formatDate(data.submitted_at)),
                    pair('Last reviewed', data.reviewed_at ? formatDate(data.reviewed_at) : ''),
                    pair('Admin notes', data.admin_notes),
                    pair('Rejection reason', data.rejection_reason)
                ]),
                cvBlock(data)
            ];

            body.textContent = '';
            sections.filter(Boolean).forEach(function (node) { body.appendChild(node); });

            buildFoot(data);
        }

        function renderError(message) {
            headRef.textContent = '';
            headTitle.textContent = 'Unavailable';
            headStatus.textContent = '';
            foot.textContent = '';
            body.textContent = '';
            body.appendChild(el('p', { class: 'drawer-empty', text: message }));
        }

        function show() {
            drawer.hidden = false;
            scrim.hidden = false;
            document.body.classList.add('drawer-open');
            window.requestAnimationFrame(function () {
                drawer.classList.add('is-open');
                scrim.classList.add('is-open');
            });
            isOpen = true;
            if (closeButton) {
                closeButton.focus();
            }
        }

        function hide() {
            if (!isOpen) {
                return;
            }
            isOpen = false;
            drawer.classList.remove('is-open');
            scrim.classList.remove('is-open');
            document.body.classList.remove('drawer-open');

            window.setTimeout(function () {
                drawer.hidden = true;
                scrim.hidden = true;
            }, 240);

            if (lastFocus && typeof lastFocus.focus === 'function') {
                lastFocus.focus();
            }
        }

        function load(id, opener) {
            lastFocus = opener || document.activeElement;

            headRef.textContent = '';
            headTitle.textContent = 'Loading…';
            headStatus.textContent = '';
            body.textContent = '';
            foot.textContent = '';
            show();

            window.fetch(cfg.detailUrl + '?id=' + encodeURIComponent(id), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json()
                        .catch(function () { return {}; })
                        .then(function (json) { return { ok: response.ok, json: json }; });
                })
                .then(function (result) {
                    if (!result.ok) {
                        renderError(result.json.error || 'Could not load this registration.');
                        return;
                    }
                    render(result.json);
                })
                .catch(function () {
                    renderError('Could not load this registration.');
                });
        }

        function trapFocus(ev) {
            var focusable = drawer.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select, textarea, iframe, [tabindex]:not([tabindex="-1"])'
            );
            if (focusable.length === 0) {
                return;
            }
            var list = Array.prototype.slice.call(focusable);
            var first = list[0];
            var last = list[list.length - 1];

            if (ev.shiftKey && document.activeElement === first) {
                ev.preventDefault();
                last.focus();
            } else if (!ev.shiftKey && document.activeElement === last) {
                ev.preventDefault();
                first.focus();
            }
        }

        // Clicking a row, or the Preview button, opens the drawer. The Preview
        // button is a real link to the full record, so it still works without JS.
        document.addEventListener('click', function (ev) {
            var trigger = ev.target.closest ? ev.target.closest('[data-preview]') : null;
            if (!trigger) {
                return;
            }

            if (trigger.tagName === 'A') {
                ev.preventDefault();
                load(trigger.getAttribute('data-preview'), trigger);
                return;
            }

            if (trigger.tagName === 'TR') {
                if (ev.target.closest('a, button, input, label, form')) {
                    return;
                }
                load(trigger.getAttribute('data-preview'), trigger);
            }
        });

        document.addEventListener('keydown', function (ev) {
            if (!isOpen) {
                return;
            }
            if (ev.key === 'Escape') {
                ev.preventDefault();
                hide();
            } else if (ev.key === 'Tab') {
                trapFocus(ev);
            }
        });

        scrim.addEventListener('click', hide);
        if (closeButton) {
            closeButton.addEventListener('click', hide);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Toolbar                                                           */
    /* ---------------------------------------------------------------- */

    function initToolbar() {
        Array.prototype.slice.call(document.querySelectorAll('[data-autosubmit]')).forEach(function (control) {
            control.addEventListener('change', function () {
                if (control.form) {
                    control.form.submit();
                }
            });
        });
    }

    initBulk();
    initConfirm();
    initDrawer();
    initToolbar();
})();
