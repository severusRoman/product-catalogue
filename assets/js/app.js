/**
 * Shelfwise front-end behaviour.
 * Client-side checks give quick feedback only. The server re-validates everything and is the real gatekeeper.
 */
(function () {
    'use strict';

    // ---- Mobile navigation ----------------------------------------------------------
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    // ---- Confirm before destructive actions (forms with data-confirm) ----------------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // ---- Filters that apply as soon as they change ----------------------------------------
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (el.form) { el.form.submit(); }
        });
    });

    // ---- Validation helpers ---------------------------------------------------------------
    function fieldOf(input) { return input.closest('.field'); }

    function setError(input, message) {
        var field = fieldOf(input);
        if (!field) { return; }
        var slot = field.querySelector('.field__error');
        if (message) {
            field.classList.add('field--error');
            input.setAttribute('aria-invalid', 'true');
            if (slot) { input.setAttribute('aria-describedby', slot.id); }
        } else {
            field.classList.remove('field--error');
            input.removeAttribute('aria-invalid');
        }
        if (slot) { slot.textContent = message || ''; }
    }

    var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var NAME = /^[\p{L}\p{M}][\p{L}\p{M} .'-]{1,59}$/u;
    var PRICE = /^\d{1,8}(\.\d{1,2})?$/;

    // Each function returns { fieldName: 'error message' or '' }
    var rules = {
        register: function (f) {
            var e = {};
            var name = f.elements.name.value.trim();
            var pw = f.elements.password.value;
            e.name = NAME.test(name) ? '' : 'Enter your name using 2 to 60 letters.';
            e.email = EMAIL.test(f.elements.email.value.trim()) ? '' : 'Enter a valid email address, like name@example.com.';
            if (pw.length < 8) { e.password = 'Use at least 8 characters.'; }
            else if (pw.length > 72) { e.password = 'Use 72 characters or fewer.'; }
            else if (!/[A-Za-z]/.test(pw) || !/\d/.test(pw)) { e.password = 'Include at least one letter and one number.'; }
            else { e.password = ''; }
            e.password_confirm = (pw === f.elements.password_confirm.value) ? '' : 'The two passwords do not match.';
            return e;
        },
        login: function (f) {
            return {
                email: EMAIL.test(f.elements.email.value.trim()) ? '' : 'Enter the email you registered with.',
                password: f.elements.password.value ? '' : 'Enter your password.'
            };
        },
        product: function (f) {
            var e = {};
            var name = f.elements.name.value.trim();
            var price = f.elements.price.value.trim();
            var stock = f.elements.stock.value.trim();
            e.name = (name.length >= 2 && name.length <= 120) ? '' : 'Enter a product name between 2 and 120 characters.';
            e.category_id = f.elements.category_id.value ? '' : 'Choose a category from the list.';
            if (!PRICE.test(price)) { e.price = 'Enter a price like 1250 or 1250.50 (up to 2 decimal places).'; }
            else if (parseFloat(price) <= 0) { e.price = 'The price must be greater than zero.'; }
            else { e.price = ''; }
            e.stock = /^\d{1,7}$/.test(stock) ? '' : 'Enter the stock as a whole number, 0 or more.';
            e.description = f.elements.description.value.length <= 2000 ? '' : 'Keep the description under 2000 characters.';

            var file = f.elements.image && f.elements.image.files && f.elements.image.files[0];
            e.image = '';
            if (file) {
                if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) { e.image = 'Upload a JPG, PNG or WebP image.'; }
                else if (file.size > 2 * 1024 * 1024) { e.image = 'That image is too large. The limit is 2 MB.'; }
            }
            return e;
        }
    };

    function applyErrors(form, errors, onlyName) {
        var firstInvalid = null;
        Object.keys(errors).forEach(function (name) {
            if (onlyName && name !== onlyName) { return; }
            var input = form.elements[name];
            if (!input) { return; }
            setError(input, errors[name]);
            if (errors[name] && !firstInvalid) { firstInvalid = input; }
        });
        return firstInvalid;
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        var check = rules[form.getAttribute('data-validate')];
        if (!check) { return; }

        form.addEventListener('submit', function (event) {
            var firstInvalid = applyErrors(form, check(form));
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });

        // Re-check a field when the visitor leaves it, and clear its message once it becomes valid.
        form.addEventListener('focusout', function (event) {
            var target = event.target;
            if (target && target.name && target.value !== '') { applyErrors(form, check(form), target.name); }
        });
        form.addEventListener('input', function (event) {
            var target = event.target;
            var field = target && fieldOf(target);
            if (field && field.classList.contains('field--error')) { applyErrors(form, check(form), target.name); }
        });
    });

    // ---- Password strength meter (register page) ----------------------------------------------
    var strength = document.querySelector('[data-strength]');
    var pwInput = document.getElementById('password');
    if (strength && pwInput && pwInput.form && pwInput.form.getAttribute('data-validate') === 'register') {
        var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        pwInput.addEventListener('input', function () {
            var v = pwInput.value;
            var score = 0;
            if (v.length >= 8) { score++; }
            if (v.length >= 12) { score++; }
            if (/[A-Za-z]/.test(v) && /\d/.test(v)) { score++; }
            if (/[a-z]/.test(v) && /[A-Z]/.test(v) || /[^A-Za-z0-9]/.test(v)) { score++; }
            strength.hidden = v.length === 0;
            strength.setAttribute('data-level', String(score));
            strength.querySelector('.strength__text').textContent = labels[score] || 'Weak';
        });
    }

    // ---- Character counter for the description field ------------------------------------------------
    document.querySelectorAll('[data-counter-for]').forEach(function (counter) {
        var source = document.getElementById(counter.getAttribute('data-counter-for'));
        if (!source) { return; }
        var update = function () { counter.textContent = String(source.value.length); };
        source.addEventListener('input', update);
        update();
    });

    // ---- Image preview before upload ---------------------------------------------------------------------
    var imageInput = document.getElementById('image');
    var preview = document.getElementById('image-preview');
    if (imageInput && preview) {
        imageInput.addEventListener('change', function () {
            var file = imageInput.files && imageInput.files[0];
            var okType = file && ['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) !== -1;
            if (file && okType && file.size <= 2 * 1024 * 1024) {
                preview.src = URL.createObjectURL(file);
                preview.hidden = false;
            } else {
                preview.hidden = true;
                preview.removeAttribute('src');
            }
        });
    }
})();
