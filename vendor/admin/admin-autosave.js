/**
 * Browser-side draft auto-save for admin edit forms.
 * Saves to localStorage and offers restore when the local draft is newer than the server copy.
 */
(function () {
    'use strict';

    const STORAGE_PREFIX = 'elementary_draft_';
    const SAVE_DEBOUNCE_MS = 2000;
    const KEEPALIVE_INTERVAL_MS = 10 * 60 * 1000;

    function storageKey(entityType, entityId) {
        return STORAGE_PREFIX + entityType + '_' + (entityId || 'new');
    }

    function parseServerTime(value) {
        if (!value) {
            return 0;
        }
        const normalized = String(value).replace(' ', 'T');
        const ts = Date.parse(normalized);
        return Number.isNaN(ts) ? 0 : ts;
    }

    function collectFormFields(form, beforeCollect) {
        if (typeof beforeCollect === 'function') {
            beforeCollect();
        }

        const fields = {};
        const formData = new FormData(form);

        formData.forEach(function (value, key) {
            if (key === 'csrf_token') {
                return;
            }
            if (key.endsWith('[]')) {
                const plainKey = key.slice(0, -2);
                if (!fields[plainKey]) {
                    fields[plainKey] = [];
                }
                fields[plainKey].push(value);
                return;
            }
            fields[key] = value;
        });

        return fields;
    }

    function setFieldValue(form, key, value) {
        const elements = form.querySelectorAll('[name="' + key + '"], [name="' + key + '[]"]');
        if (!elements.length) {
            return;
        }

        if (Array.isArray(value)) {
            elements.forEach(function (el) {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = value.indexOf(el.value) !== -1;
                }
            });
            return;
        }

        const el = elements[0];
        if (el.type === 'checkbox' || el.type === 'radio') {
            el.checked = !!value;
            return;
        }
        if (el.tagName === 'SELECT') {
            el.value = value;
            return;
        }
        el.value = value;
    }

    function restoreFormFields(form, fields, afterRestore) {
        Object.keys(fields).forEach(function (key) {
            setFieldValue(form, key, fields[key]);
        });

        if (typeof afterRestore === 'function') {
            afterRestore(fields);
        }
    }

    function hasMeaningfulContent(fields) {
        const textFields = ['title', 'content', 'excerpt', 'builder_structure'];
        return textFields.some(function (key) {
            const val = fields[key];
            if (typeof val !== 'string') {
                return false;
            }
            const trimmed = val.trim();
            if (key === 'builder_structure') {
                return trimmed !== '' && trimmed !== '[]' && trimmed !== '{"sections":[]}';
            }
            return trimmed !== '';
        });
    }

    function formatLocalTime(isoString) {
        try {
            return new Date(isoString).toLocaleString();
        } catch (e) {
            return isoString;
        }
    }

    function showRestoreModal(strings, onRestore, onDiscard) {
        const existing = document.getElementById('draftRestoreModal');
        if (existing) {
            existing.remove();
        }

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'draftRestoreModal';
        modal.tabIndex = -1;
        modal.innerHTML =
            '<div class="modal-dialog">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h5 class="modal-title">' + strings.title + '</h5>' +
                        '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                    '</div>' +
                    '<div class="modal-body">' + strings.message + '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="draftDiscardBtn">' + strings.discard + '</button>' +
                        '<button type="button" class="btn btn-dark" id="draftRestoreBtn">' + strings.restore + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        modal.querySelector('#draftRestoreBtn').addEventListener('click', function () {
            bsModal.hide();
            onRestore();
        });
        modal.querySelector('#draftDiscardBtn').addEventListener('click', function () {
            bsModal.hide();
            onDiscard();
        });
        modal.addEventListener('hidden.bs.modal', function () {
            modal.remove();
        });

        bsModal.show();
    }

    function startSessionKeepalive(keepaliveUrl) {
        if (!keepaliveUrl) {
            return;
        }

        setInterval(function () {
            fetch(keepaliveUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .catch(function () { /* ignore network errors */ });
        }, KEEPALIVE_INTERVAL_MS);
    }

    window.AdminAutosave = {
        init: function (config) {
            const form = document.querySelector(config.formSelector || 'form');
            if (!form) {
                return;
            }

            const entityType = config.entityType;
            const entityId = config.entityId || 'new';
            const key = storageKey(entityType, entityId);
            const serverUpdatedAt = config.serverUpdatedAt || null;
            const serverTs = parseServerTime(serverUpdatedAt);
            const strings = config.strings || {};
            let saveTimer = null;
            let statusEl = null;

            if (config.statusSelector) {
                statusEl = document.querySelector(config.statusSelector);
            }

            function updateStatus(savedAt) {
                if (!statusEl || !strings.savedLocally) {
                    return;
                }
                statusEl.textContent = strings.savedLocally.replace(':time', formatLocalTime(savedAt));
                statusEl.classList.remove('d-none');
            }

            function saveDraft() {
                const fields = collectFormFields(form, config.beforeCollect);
                if (!hasMeaningfulContent(fields)) {
                    return;
                }

                const savedAt = new Date().toISOString();
                const draft = {
                    savedAt: savedAt,
                    serverUpdatedAt: serverUpdatedAt,
                    fields: fields,
                };

                try {
                    localStorage.setItem(key, JSON.stringify(draft));
                    updateStatus(savedAt);
                } catch (e) {
                    /* localStorage full or unavailable */
                }
            }

            function scheduleSave() {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(saveDraft, SAVE_DEBOUNCE_MS);
            }

            function clearDraft() {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    /* ignore */
                }
                if (statusEl) {
                    statusEl.textContent = '';
                    statusEl.classList.add('d-none');
                }
            }

            function shouldOfferRestore(draft) {
                const draftTs = parseServerTime(draft.savedAt);
                if (serverTs > 0) {
                    return draftTs > serverTs;
                }
                return hasMeaningfulContent(draft.fields);
            }

            function checkForRestore() {
                let draft = null;
                try {
                    const raw = localStorage.getItem(key);
                    if (raw) {
                        draft = JSON.parse(raw);
                    }
                } catch (e) {
                    localStorage.removeItem(key);
                    return;
                }

                if (!draft || !draft.fields || !shouldOfferRestore(draft)) {
                    return;
                }

                showRestoreModal(
                    {
                        title: strings.title || 'Restore unsaved draft?',
                        message: (strings.message || 'A newer version of this content was found in your browser.').replace(':time', formatLocalTime(draft.savedAt)),
                        restore: strings.restore || 'Restore',
                        discard: strings.discard || 'Discard',
                    },
                    function () {
                        restoreFormFields(form, draft.fields, config.afterRestore);
                        if (typeof config.onRestore === 'function') {
                            config.onRestore(draft.fields);
                        }
                        if (typeof config.onDirty === 'function') {
                            config.onDirty();
                        }
                        updateStatus(draft.savedAt);
                    },
                    function () {
                        clearDraft();
                    }
                );
            }

            form.addEventListener('input', scheduleSave);
            form.addEventListener('change', scheduleSave);

            form.addEventListener('submit', function () {
                clearDraft();
            });

            if (typeof config.onInit === 'function') {
                config.onInit({ scheduleSave: scheduleSave, clearDraft: clearDraft });
            }

            checkForRestore();
            startSessionKeepalive(config.keepaliveUrl);
        },
    };
})();
