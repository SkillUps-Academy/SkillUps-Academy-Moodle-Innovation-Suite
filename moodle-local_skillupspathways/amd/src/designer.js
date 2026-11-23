// This file is part of Moodle - https://moodle.org/
//
// Distributed under the GNU GPL v3 or later.

/** Size of visual grid cells in CSS pixels. */
const CELL = {width: 235, height: 94};

const element = (tag, text = '', css = '') => {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = css;
    return node;
};

const viewport = () => {
    const scroll = element('div', '', 'pathways-scroll');
    scroll.tabIndex = 0;
    return scroll;
};

const button = (text, callback, css = 'btn btn-outline-secondary btn-sm') => {
    const control = element('button', text, css);
    control.type = 'button';
    control.addEventListener('click', callback);
    return control;
};

const dimensions = nodes => ({
    width: Math.max(2, ...nodes.map(node => node.lane + 1)) * CELL.width,
    height: Math.max(2, ...nodes.map(node => node.row + 1)) * CELL.height,
});

const position = node => ({x: node.lane * CELL.width + 12, y: node.row * CELL.height + 12});

const paint = (canvas, nodes, edges) => {
    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.strokeStyle = '#668ab1';
    ctx.fillStyle = '#668ab1';
    const keys = new Map(nodes.map(node => [node.key, position(node)]));
    for (const edge of edges) {
        if (!keys.has(edge.from) || !keys.has(edge.to)) {
            continue;
        }
        const from = keys.get(edge.from);
        const to = keys.get(edge.to);
        const x = from.x + 200;
        const y = from.y + 25;
        const tx = to.x;
        const ty = to.y + 25;
        const middle = (x + tx) / 2;
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.bezierCurveTo(middle, y, middle, ty, tx - 1, ty);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(tx, ty);
        ctx.lineTo(tx - 8, ty - 4);
        ctx.lineTo(tx - 8, ty + 4);
        ctx.closePath();
        ctx.fill();
    }
};

const map = (scroll, graph, labels, hooks) => {
    const size = dimensions(graph.nodes);
    const stage = element('div', '', 'pathways-stage');
    stage.style.width = size.width + 'px';
    stage.style.height = size.height + 'px';
    const canvas = element('canvas', '', 'pathways-edges');
    canvas.width = size.width;
    canvas.height = size.height;
    canvas.setAttribute('aria-hidden', 'true');
    stage.append(canvas);
    paint(canvas, graph.nodes, graph.edges);
    for (const node of graph.nodes) {
        const card = hooks
            ? button(node.title || labels.steptitle, () => hooks.select(node), 'pathways-node')
            : element('div', node.title, 'pathways-node');
        const xy = position(node);
        card.style.left = xy.x + 'px';
        card.style.top = xy.y + 'px';
        card.dataset.key = node.key;
        if (node.state) {
            card.dataset.state = node.state;
            card.append(element('small', labels.states[node.state]));
        }
        if (hooks) {
            card.draggable = true;
            card.setAttribute('aria-pressed', String(node.key === hooks.selected));
            card.addEventListener('dragstart', event => event.dataTransfer.setData('text/plain', node.key));
            card.addEventListener('keydown', event => {
                const delta = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]}[event.key];
                if (delta) {
                    event.preventDefault();
                    hooks.move(node, node.lane + delta[0], node.row + delta[1]);
                    scroll.querySelector('[data-key="' + node.key + '"]')?.focus();
                }
            });
        }
        stage.append(card);
    }
    if (hooks) {
        stage.addEventListener('dragover', event => event.preventDefault());
        stage.addEventListener('drop', event => {
            event.preventDefault();
            const node = graph.nodes.find(candidate => candidate.key === event.dataTransfer.getData('text/plain'));
            if (node) {
                const bounds = stage.getBoundingClientRect();
                hooks.move(node, Math.floor((event.clientX - bounds.left) / CELL.width),
                    Math.floor((event.clientY - bounds.top) / CELL.height));
            }
        });
    }
    scroll.replaceChildren(stage);
};

/**
 * Render a read-only map. The same information is exposed in a native HTML list.
 *
 * @param {string} id Container ID.
 */
const initMap = (id) => {
    const root = document.getElementById(id);
    if (!root) {
        return;
    }
    const graph = JSON.parse(root.dataset.graph);
    const labels = JSON.parse(root.dataset.labels);
    if (!graph.nodes.length) {
        return;
    }
    const scroll = viewport();
    scroll.setAttribute('aria-label', labels.map);
    root.append(scroll);
    map(scroll, graph, labels, null);
};

const reachable = (edges, from, target) => {
    const seen = new Set();
    const stack = [from];
    while (stack.length) {
        const current = stack.pop();
        if (current === target) {
            return true;
        }
        if (seen.has(current)) {
            continue;
        }
        seen.add(current);
        stack.push(...edges.filter(edge => edge.from === current).map(edge => edge.to));
    }
    return false;
};

/**
 * Build an accessible pathway designer tied to Moodle's native form.
 *
 * @param {string} id Editor placeholder ID.
 * @param {number} entry Entry course ID used as a starting value.
 */
const init = (id, entry) => {
    const root = document.getElementById(id);
    const hidden = root?.closest('form')?.querySelector('input[name="graph"]');
    if (!hidden) {
        return;
    }
    const labels = JSON.parse(root.dataset.labels);
    let graph;
    try {
        graph = JSON.parse(hidden.value);
        if (!Array.isArray(graph.nodes) || !Array.isArray(graph.edges)) {
            throw new Error('Unexpected graph');
        }
    } catch {
        graph = {nodes: [], edges: []};
    }

    let selected = graph.nodes[0]?.key || '';
    const summary = element('div', '', 'small text-muted mb-2');
    summary.setAttribute('role', 'status');
    const scroll = viewport();
    scroll.setAttribute('aria-label', labels.map);
    const panel = element('section', '', 'pathways-path-panel border rounded p-3 mt-3');
    const persist = () => {
        hidden.value = JSON.stringify(graph);
    };
    const move = (node, lane, row) => {
        const requested = Math.max(0, Math.min(59, row));
        node.lane = Math.max(0, Math.min(7, lane));
        for (let offset = 0; offset < 60; offset++) {
            const candidate = (requested + offset) % 60;
            if (!graph.nodes.some(other => other !== node && other.lane === node.lane && other.row === candidate)) {
                node.row = candidate;
                break;
            }
        }
        summary.textContent = labels.moved;
        persist();
        refreshMap();
    };
    const refreshMap = () => map(scroll, graph, labels, {
        selected,
        move,
        select: node => {
            selected = node.key;
            refreshMap();
            details();
            panel.querySelector('input')?.focus();
        },
    });

    const details = () => {
        panel.replaceChildren();
        const node = graph.nodes.find(candidate => candidate.key === selected);
        if (!node) {
            panel.append(element('p', labels.nosteps));
            return;
        }
        panel.append(element('h4', labels.stepoptions, 'h5'));
        const fields = element('div', '', 'pathways-fields');
        const field = (name, type, min, max) => {
            const label = element('label', labels[name], 'form-label');
            const input = element('input', '', type === 'checkbox' ? 'form-check-input ms-2' : 'form-control');
            input.type = type;
            if (type === 'checkbox') {
                input.checked = node[name];
            } else {
                input.value = node[name];
                if (type === 'number') {
                    input.min = min;
                    input.max = max;
                    input.step = 1;
                    input.required = true;
                } else {
                    input.maxLength = 120;
                    input.required = true;
                }
            }
            input.addEventListener('input', () => {
                node[name] = type === 'checkbox' ? input.checked : type === 'number' ? Number(input.value) : input.value;
                persist();
                if (name === 'title') {
                    refreshMap();
                }
            });
            label.append(input);
            fields.append(label);
        };
        field('title', 'text');
        field('course', 'number', 1, 2147483647);
        field('cm', 'number', 0, 2147483647);
        field('grade', 'number', -1, 100);
        field('competency', 'number', 0, 2147483647);
        field('completion', 'checkbox');
        field('required', 'checkbox');
        const join = element('label', labels.join, 'form-label');
        const strategy = element('select', '', 'form-select');
        for (const key of ['all', 'any']) {
            const option = element('option', labels[key]);
            option.value = key;
            option.selected = key === node.join;
            strategy.append(option);
        }
        strategy.addEventListener('change', () => {
            node.join = strategy.value;
            persist();
        });
        join.append(strategy);
        fields.append(join);
        panel.append(fields);

        const prerequisites = element('fieldset', '', 'mt-3');
        prerequisites.append(element('legend', labels.prerequisites, 'h6'));
        const choices = graph.nodes.filter(candidate => candidate !== node);
        if (!choices.length) {
            prerequisites.append(element('p', labels.nochoices, 'small text-muted'));
        }
        for (const candidate of choices) {
            const label = element('label', '', 'form-check form-check-inline');
            const checkbox = element('input', '', 'form-check-input');
            checkbox.type = 'checkbox';
            checkbox.checked = graph.edges.some(edge => edge.from === candidate.key && edge.to === node.key);
            checkbox.addEventListener('change', () => {
                if (checkbox.checked && (graph.edges.length >= 150 || reachable(graph.edges, node.key, candidate.key))) {
                    checkbox.checked = false;
                    summary.textContent = graph.edges.length >= 150 ? labels.edgelimit : labels.cycle;
                    return;
                }
                graph.edges = graph.edges.filter(edge => edge.from !== candidate.key || edge.to !== node.key);
                if (checkbox.checked) {
                    graph.edges.push({from: candidate.key, to: node.key});
                }
                persist();
                refreshMap();
            });
            label.append(checkbox, element('span', candidate.title || labels.steptitle));
            prerequisites.append(label);
        }
        panel.append(prerequisites);
        const controls = element('div', '', 'd-flex flex-wrap gap-2 mt-3');
        controls.append(button(labels.right, () => move(node, node.lane + 1, node.row)));
        controls.append(button(labels.down, () => move(node, node.lane, node.row + 1)));
        controls.append(button(labels.remove, () => {
            graph.nodes = graph.nodes.filter(candidate => candidate !== node);
            graph.edges = graph.edges.filter(edge => edge.from !== node.key && edge.to !== node.key);
            selected = graph.nodes[0]?.key || '';
            persist();
            refreshMap();
            details();
        }, 'btn btn-outline-danger btn-sm'));
        panel.append(controls);
    };

    root.append(element('h3', labels.map, 'h4'), element('p', labels.editorhint));
    root.append(button(labels.addstep, () => {
        if (graph.nodes.length >= 60) {
            summary.textContent = labels.stepmax;
            return;
        }
        const random = Uint32Array.from([0, 0]);
        crypto.getRandomValues(random);
        const node = {
            key: 'n' + Array.from(random, number => number.toString(16).padStart(8, '0')).join(''),
            title: labels.steptitle, course: entry, cm: 0, completion: true,
            grade: -1, competency: 0, required: true, join: 'all', lane: graph.nodes.length % 3,
            row: Math.floor(graph.nodes.length / 3),
        };
        graph.nodes.push(node);
        selected = node.key;
        move(node, node.lane, node.row);
        details();
        panel.querySelector('input')?.select();
    }, 'btn btn-primary mb-2'));
    root.append(summary, scroll, panel);
    refreshMap();
    details();
};

export {init, initMap};
