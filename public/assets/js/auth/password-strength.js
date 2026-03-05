/**
 * Password Strength Validator (Client-Side)
 *
 * Provides real-time visual feedback on password strength.
 * Mirrors server-side PasswordValidator rules (OWASP Top 10 2025 compliant).
 *
 * Rules:
 * - Minimum 12 characters
 * - At least 1 uppercase letter
 * - At least 1 lowercase letter
 * - At least 1 digit
 * - At least 1 special character
 *
 * @package DashMed
 * @author DashMed Team
 */
(() => {
    'use strict';

    const MIN_LENGTH = 12;

    const RULES = [
        { id: 'length', label: '12 caractères minimum', test: (p) => p.length >= MIN_LENGTH },
        { id: 'uppercase', label: 'Une lettre majuscule', test: (p) => /[A-Z]/.test(p) },
        { id: 'lowercase', label: 'Une lettre minuscule', test: (p) => /[a-z]/.test(p) },
        { id: 'digit', label: 'Un chiffre', test: (p) => /[0-9]/.test(p) },
        { id: 'special', label: 'Un caractère spécial (!@#$%...)', test: (p) => /[^A-Za-z0-9]/.test(p) },
    ];

    /**
     * Creates the password strength indicator UI.
     *
     * @param {HTMLInputElement} input - The password input element
     * @returns {void}
     */
    function createStrengthIndicator(input) {
        const wrapper = input.closest('.form-group') || input.parentElement;
        if (!wrapper || wrapper.querySelector('.pw-strength-container')) return;

        const container = document.createElement('div');
        container.className = 'pw-strength-container';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');

        // Strength bar
        const barWrapper = document.createElement('div');
        barWrapper.className = 'pw-strength-bar-wrapper';
        const bar = document.createElement('div');
        bar.className = 'pw-strength-bar';
        bar.id = 'pw-bar-' + input.id;
        barWrapper.appendChild(bar);

        // Strength label
        const label = document.createElement('div');
        label.className = 'pw-strength-label';
        label.id = 'pw-label-' + input.id;
        label.textContent = '';

        // Rules checklist
        const checklist = document.createElement('ul');
        checklist.className = 'pw-rules-checklist';

        RULES.forEach(rule => {
            const li = document.createElement('li');
            li.className = 'pw-rule';
            li.id = `pw-rule-${rule.id}-${input.id}`;
            li.innerHTML = `
                <span class="pw-rule-icon">○</span>
                <span class="pw-rule-text">${rule.label}</span>
            `;
            checklist.appendChild(li);
        });

        container.appendChild(barWrapper);
        container.appendChild(label);
        container.appendChild(checklist);

        wrapper.appendChild(container);

        // Event listener
        input.addEventListener('input', () => updateStrength(input));
        input.addEventListener('focus', () => {
            container.classList.add('pw-visible');
        });
        input.addEventListener('blur', () => {
            if (input.value === '') {
                container.classList.remove('pw-visible');
            }
        });
    }

    /**
     * Calculates password strength and updates the UI.
     *
     * @param {HTMLInputElement} input - The password input element
     * @returns {void}
     */
    function updateStrength(input) {
        const password = input.value;
        const bar = document.getElementById('pw-bar-' + input.id);
        const label = document.getElementById('pw-label-' + input.id);
        const container = input.closest('.form-group')?.querySelector('.pw-strength-container');

        if (!bar || !label) return;

        if (container) container.classList.add('pw-visible');

        let passed = 0;

        RULES.forEach(rule => {
            const li = document.getElementById(`pw-rule-${rule.id}-${input.id}`);
            if (!li) return;
            const icon = li.querySelector('.pw-rule-icon');

            if (rule.test(password)) {
                passed++;
                li.classList.add('pw-rule-pass');
                li.classList.remove('pw-rule-fail');
                if (icon) icon.textContent = '✓';
            } else {
                li.classList.remove('pw-rule-pass');
                if (password.length > 0) {
                    li.classList.add('pw-rule-fail');
                } else {
                    li.classList.remove('pw-rule-fail');
                }
                if (icon) icon.textContent = '○';
            }
        });

        const percent = (passed / RULES.length) * 100;
        bar.style.width = percent + '%';

        if (password.length === 0) {
            bar.className = 'pw-strength-bar';
            label.textContent = '';
            label.className = 'pw-strength-label';
        } else if (passed <= 2) {
            bar.className = 'pw-strength-bar pw-weak';
            label.textContent = 'Faible';
            label.className = 'pw-strength-label pw-label-weak';
        } else if (passed <= 3) {
            bar.className = 'pw-strength-bar pw-fair';
            label.textContent = 'Moyen';
            label.className = 'pw-strength-label pw-label-fair';
        } else if (passed <= 4) {
            bar.className = 'pw-strength-bar pw-good';
            label.textContent = 'Bon';
            label.className = 'pw-strength-label pw-label-good';
        } else {
            bar.className = 'pw-strength-bar pw-strong';
            label.textContent = 'Excellent';
            label.className = 'pw-strength-label pw-label-strong';
        }
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        // Target password fields that are for creating/resetting passwords (not login)
        const passwordInputs = document.querySelectorAll(
            'input[type="password"][autocomplete="new-password"], ' +
            'input[type="password"][name="password"]:not([autocomplete="current-password"])'
        );

        passwordInputs.forEach(input => {
            // Skip confirm password fields
            if (input.name === 'password_confirm') return;

            // Skip login page (check if there's no signup/reset context)
            const form = input.closest('form');
            if (form && form.querySelector('input[name="search"]')) return;

            createStrengthIndicator(input);
        });
    });
})();
