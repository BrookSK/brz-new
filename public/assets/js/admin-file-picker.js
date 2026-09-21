/*
 * Admin File Picker
 *
 * Replaces the browser-specific presentation of administrative file inputs
 * while keeping the original input in place for native validation, FormData,
 * existing listeners, previews, and upload endpoints.
 */
(function () {
    'use strict';

    var INPUT_SELECTOR = 'input[type="file"]';
    var generatedId = 0;

    function getLabels() {
        var configured = window.ADMIN_FILE_PICKER_I18N || {};
        if (configured.choose && configured.none && configured.count) {
            return configured;
        }

        var isEnglish = String(window.ADMIN_PREF_IDIOMA || '').toLowerCase() === 'en';
        return isEnglish
            ? { choose: 'Choose file', none: 'No file selected', count: '{n} files selected' }
            : { choose: 'Escolher arquivo', none: 'Nenhum arquivo selecionado', count: '{n} arquivos selecionados' };
    }

    function isCustomPicker(input) {
        return input.dataset.adminFilePickerEnhanced === 'true'
            || input.dataset.productEditorFilePicker === 'true'
            || input.classList.contains('product-editor-file-input')
            || input.classList.contains('purchase-group-file-input')
            || input.id === 'grupoBanner';
    }

    function ensureInputId(input) {
        if (input.id) {
            return input.id;
        }

        generatedId += 1;
        input.id = 'admin-file-input-' + generatedId;
        return input.id;
    }

    function getSelectionText(input, labels) {
        var files = input.files;
        if (!files || files.length === 0) {
            return labels.none;
        }

        if (files.length === 1) {
            return files[0].name;
        }

        return labels.count.replace('{n}', String(files.length));
    }

    function updatePicker(input) {
        var picker = input.nextElementSibling;
        if (!picker || !picker.classList.contains('admin-file-picker')) {
            return;
        }

        var labels = getLabels();
        var button = picker.querySelector('.admin-file-picker__button');
        var status = picker.querySelector('.admin-file-picker__status');
        if (!button || !status) {
            return;
        }

        button.textContent = labels.choose;
        button.disabled = input.disabled;
        button.setAttribute('aria-label', labels.choose);
        button.setAttribute('aria-required', input.required ? 'true' : 'false');
        status.textContent = getSelectionText(input, labels);
        status.title = status.textContent;
    }

    function enhance(input) {
        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || isCustomPicker(input)) {
            return;
        }

        input.dataset.adminFilePickerEnhanced = 'true';
        input.classList.add('admin-file-picker__input');
        input.setAttribute('tabindex', '-1');
        input.setAttribute('aria-hidden', 'true');

        var inputId = ensureInputId(input);
        var picker = document.createElement('div');
        picker.className = 'admin-file-picker';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary admin-file-picker__button';
        button.setAttribute('aria-controls', inputId);

        var status = document.createElement('span');
        status.className = 'admin-file-picker__status';
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');

        picker.appendChild(button);
        picker.appendChild(status);
        input.insertAdjacentElement('afterend', picker);

        button.addEventListener('click', function () {
            if (!input.disabled) {
                input.click();
            }
        });

        input.addEventListener('change', function () {
            updatePicker(input);
        });

        input.addEventListener('focus', function () {
            button.classList.add('focus-visible');
        });

        input.addEventListener('blur', function () {
            button.classList.remove('focus-visible');
        });

        updatePicker(input);
    }

    function enhanceWithin(root) {
        if (!root) {
            return;
        }

        if (root.matches && root.matches(INPUT_SELECTOR)) {
            enhance(root);
        }

        if (root.querySelectorAll) {
            root.querySelectorAll(INPUT_SELECTOR).forEach(enhance);
        }
    }

    function injectStyles() {
        if (document.getElementById('admin-file-picker-styles')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'admin-file-picker-styles';
        style.textContent = [
            '.admin-file-picker__input{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;white-space:nowrap!important;border:0!important;}',
            '.admin-file-picker{display:flex;flex:1 1 240px;align-items:center;gap:.75rem;min-width:0;max-width:100%;}',
            '.admin-file-picker__button{flex:0 0 auto;}',
            '.admin-file-picker__button.focus-visible{outline:3px solid rgba(13,110,253,.35);outline-offset:2px;}',
            '.admin-file-picker__status{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6c757d;font-size:.875rem;line-height:1.5;}',
            '@media (max-width:575.98px){.admin-file-picker{align-items:flex-start;flex-direction:column;gap:.4rem;}.admin-file-picker__status{max-width:100%;}}'
        ].join('');
        document.head.appendChild(style);
    }

    function observeDynamicInputs() {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.target.matches(INPUT_SELECTOR)) {
                    updatePicker(mutation.target);
                    return;
                }

                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        enhanceWithin(node);
                    }
                });
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['disabled', 'required']
        });
    }

    injectStyles();
    enhanceWithin(document);
    observeDynamicInputs();
}());
