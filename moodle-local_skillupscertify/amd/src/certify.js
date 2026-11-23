// This file is part of Moodle - https://moodle.org/
//
// Distributed under the GNU GPL v3 or later.

const element = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;
    return node;
};

/**
 * Render the layout with synthetic data as the operator changes fields.
 *
 * @param {string} id Preview container ID.
 */
const preview = id => {
    const root = document.getElementById(id);
    const form = root?.closest('form');
    const sample = document.getElementById('skillups-certify-sample')?.dataset;
    if (!form || !sample) {
        return;
    }
    const page = element('div', 'certify-paper', '');
    const issuer = element('p', 'certify-paper-issuer', '');
    const title = element('h4', 'certify-paper-title', '');
    const recipient = element('p', 'certify-paper-name', sample.recipient);
    const text = element('p', 'certify-paper-body', '');
    const signer = element('p', 'certify-paper-signer', '');
    page.append(issuer, title, recipient, text, signer);
    root.classList.add('certify-preview');
    root.append(element('h3', 'h5 mt-3', sample.preview), page);
    const date = new Intl.DateTimeFormat(document.documentElement.lang).format(new Date());
    const field = name => form.elements.namedItem(name).value;
    const update = () => {
        page.dataset.color = field('color');
        page.dataset.orientation = field('orientation');
        issuer.textContent = field('issuer');
        title.textContent = field('title');
        signer.textContent = field('signer');
        const values = {
            recipient: sample.recipient, course: sample.course, issuer: field('issuer'),
            issuedate: date, completiondate: date, serial: sample.example,
        };
        text.textContent = field('body').replace(/\{(\w+)\}/g, (token, key) => Object.hasOwn(values, key) ? values[key] : token);
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
};

/**
 * Compare a local PDF buffer against the registry, without uploading it.
 *
 * @param {string} id Fingerprint controls.
 */
const checkFile = id => {
    const root = document.getElementById(id);
    if (!root || !window.crypto?.subtle) {
        return;
    }
    root.hidden = false;
    const input = root.querySelector('input[type="file"]');
    const feedback = root.querySelector('[role="status"]');
    input.addEventListener('change', async() => {
        const candidate = input.files[0];
        feedback.textContent = '';
        if (!candidate) {
            return;
        }
        if (candidate.size > 3 * 1024 * 1024) {
            feedback.textContent = root.dataset.size;
            return;
        }
        try {
            const digest = await window.crypto.subtle.digest('SHA-256', await candidate.arrayBuffer());
            const hash = [...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('');
            if (input.files[0] === candidate) {
                feedback.textContent = hash === root.dataset.sha ? root.dataset.match : root.dataset.mismatch;
            }
        } catch {
            if (input.files[0] === candidate) {
                feedback.textContent = root.dataset.mismatch;
            }
        }
    });
};

export {preview, checkFile};
