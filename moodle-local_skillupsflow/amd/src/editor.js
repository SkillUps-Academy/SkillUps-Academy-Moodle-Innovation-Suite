/**
 * Visual workflow editor and accessible connection inspector.
 *
 * @module local_skillupsflow/editor
 * @copyright 2026 SkillUps Academy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const svgNamespace = 'http://www.w3.org/2000/svg';
const predicates = ['activeuser', 'visiblecourse', 'enrolled', 'hasuser', 'hascourse'];

function element(tag, properties = {}, ...children) {
    const node = Object.assign(document.createElement(tag), properties);
    node.append(...children);
    return node;
}

function field(title, input) {
    input.setAttribute('aria-label', title);
    return element('label', {className: 'sf-field'}, element('span', {textContent: title}), input);
}

function dropdown(options, value, change) {
    const input = element('select', {className: 'form-select'});
    options.forEach(([id, label]) => input.append(element('option', {value: id, textContent: label})));
    input.value = String(value);
    input.addEventListener('change', () => change(input.value));
    return input;
}

function inputField(type, value, change) {
    const input = element('input', {type, className: 'form-control', value});
    input.addEventListener('input', () => change(type === 'number' ? Number(input.value) : input.value));
    return input;
}

function button(label, handler, style = 'outline-secondary') {
    const control = element('button', {type: 'button', className: 'btn btn-' + style + ' btn-sm', textContent: label});
    control.addEventListener('click', handler);
    return control;
}

/** Initialize the editor from the Moodle form's serialized draft. */
export const init = () => {
    const root = document.querySelector('[data-flow-editor]');
    if (!root) {
        return;
    }
    const {ports, triggerTypes, strings} = JSON.parse(root.dataset.flowConfig);
    const source = root.querySelector('[name=graph]');
    const canvas = root.querySelector('[data-flow-canvas]');
    const inspector = root.querySelector('[data-flow-inspector]');
    const triggerBox = root.querySelector('[data-flow-trigger]');
    const palette = root.querySelector('[data-flow-palette]');
    const status = root.querySelector('[data-flow-status]');
    let graph;
    try {
        graph = JSON.parse(source.value);
        if (!Array.isArray(graph.nodes) || !graph.trigger) {
            return;
        }
    } catch (error) {
        // A malformed POST is handled by Moodle's form validation.
        return;
    }
    let selected = graph.entry;
    const sync = () => {
        source.value = JSON.stringify(graph);
    };
    const title = (node) => node.label || strings['node_' + node.type];
    const nodeOptions = () => graph.nodes.map((node) => [node.id, title(node) + ' · ' + node.id]);
    const position = (value) => Math.max(0, Math.min(3800, Math.round(value)));
    const svg = document.createElementNS(svgNamespace, 'svg');
    svg.classList.add('sf-connections');
    svg.setAttribute('aria-hidden', 'true');
    let cards = new Map();

    function draw() {
        const width = Math.max(700, ...graph.nodes.map((node) => node.x + 205));
        const height = Math.max(440, ...graph.nodes.map((node) => node.y + 110));
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.replaceChildren();
        graph.nodes.forEach((node) => {
            const origin = cards.get(node.id);
            (ports[node.type] || []).forEach((port) => {
                const target = graph.nodes.find((item) => item.id === node[port]);
                if (!target) {
                    return;
                }
                const color = {yes: '#287641', no: '#8d4b11', error: '#963050'}[port] || '#286c92';
                const x1 = node.x + 174;
                const y1 = node.y + (origin?.offsetHeight || 56) / 2;
                const x2 = target.x + 3;
                const y2 = target.y + (cards.get(target.id)?.offsetHeight || 56) / 2;
                const bend = Math.max(45, Math.abs(x2 - x1) / 2);
                const path = document.createElementNS(svgNamespace, 'path');
                path.setAttribute('d', 'M ' + x1 + ' ' + y1 + ' C ' + (x1 + bend) + ' ' + y1 + ' ' +
                    (x2 - bend) + ' ' + y2 + ' ' + x2 + ' ' + y2);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', color);
                path.setAttribute('stroke-width', '2');
                const arrow = document.createElementNS(svgNamespace, 'path');
                arrow.setAttribute('d', 'M ' + (x2 - 9) + ' ' + (y2 - 4) + ' L ' + x2 + ' ' + y2 +
                    ' L ' + (x2 - 9) + ' ' + (y2 + 4));
                arrow.setAttribute('fill', 'none');
                arrow.setAttribute('stroke', color);
                svg.append(path, arrow);
            });
        });
    }

    function renderCanvas() {
        cards = new Map();
        canvas.replaceChildren(svg);
        graph.nodes.forEach((node) => {
            const card = element('button', {type: 'button', className: 'sf-node'},
                element('strong', {textContent: title(node)}),
                element('small', {textContent: (node.id === graph.entry ? strings.entry + ' · ' : '') + node.id}));
            card.style.left = node.x + 'px';
            card.style.top = node.y + 'px';
            card.setAttribute('aria-pressed', String(selected === node.id));
            card.addEventListener('click', () => {
                selected = node.id;
                cards.forEach((item, id) => item.setAttribute('aria-pressed', String(id === node.id)));
                renderInspector();
            });
            let drag = null;
            card.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) {
                    return;
                }
                drag = {px: event.clientX, py: event.clientY, x: node.x, y: node.y};
                card.setPointerCapture(event.pointerId);
            });
            card.addEventListener('pointermove', (event) => {
                if (drag) {
                    node.x = position(drag.x + event.clientX - drag.px);
                    node.y = position(drag.y + event.clientY - drag.py);
                    card.style.left = node.x + 'px';
                    card.style.top = node.y + 'px';
                    draw();
                }
            });
            const end = () => {
                drag = null;
                sync();
            };
            card.addEventListener('pointerup', end);
            card.addEventListener('pointercancel', end);
            cards.set(node.id, card);
            canvas.append(card);
        });
        draw();
    }

    function renderTrigger() {
        const data = graph.trigger;
        const change = (key, value) => {
            data[key] = value;
            sync();
        };
        const types = triggerTypes.map((type) => [type, strings['trigger_' + type]]);
        triggerBox.replaceChildren(
            field(strings.trigger, dropdown(types, data.type, (value) => {
                change('type', value);
                renderTrigger();
            })),
            field(strings.userid, inputField('number', data.userid || 0, (value) => change('userid', value))),
            field(strings.courseid, inputField('number', data.courseid || 0, (value) => change('courseid', value)))
        );
        if (data.type === 'interval' || data.type === 'once') {
            const seconds = data.start || 0;
            const date = new Date(seconds * 1000);
            const local = seconds ? new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16) : '';
            triggerBox.append(field(strings.start, inputField('datetime-local', local, (value) => {
                change('start', value ? Math.floor(new Date(value).getTime() / 1000) : 0);
            })));
            if (data.type === 'interval') {
                triggerBox.append(field(strings.minutes, inputField('number', data.minutes || 60, (value) => change('minutes', value))));
            }
        }
        triggerBox.append(element('small', {textContent: strings.triggerhint}));
    }

    function renderInspector() {
        const node = graph.nodes.find((item) => item.id === selected);
        const entry = field(strings.entry, dropdown(nodeOptions(), graph.entry, (value) => {
            graph.entry = value;
            sync();
            renderCanvas();
        }));
        inspector.replaceChildren(entry);
        if (!node) {
            inspector.append(element('p', {textContent: strings.nothingselected}));
            return;
        }
        const change = (key, value) => {
            node[key] = value;
            sync();
        };
        inspector.append(field(strings.label, inputField('text', node.label || '', (value) => {
            change('label', value);
            cards.get(node.id).querySelector('strong').textContent = title(node);
            draw();
        })));
        if (['condition', 'suspend', 'enrol', 'notify', 'visibility'].includes(node.type)) {
            inspector.append(field(strings.userid, inputField('number', node.userid || 0, (value) => change('userid', value))),
                field(strings.courseid, inputField('number', node.courseid || 0, (value) => change('courseid', value))),
                element('small', {textContent: strings.targethint}));
        }
        if (['delay', 'approval'].includes(node.type)) {
            inspector.append(field(strings.minutes, inputField('number', node.minutes, (value) => change('minutes', value))));
        }
        if (['suspend', 'visibility'].includes(node.type)) {
            inspector.append(field(strings.value, dropdown([[0, strings.zero], [1, strings.one]], node.value,
                (value) => change('value', Number(value)))));
        }
        if (node.type === 'enrol') {
            inspector.append(field(strings.roleid, inputField('number', node.roleid, (value) => change('roleid', value))));
        }
        if (node.type === 'notify') {
            const text = element('textarea', {className: 'form-control', rows: 4, maxLength: 2000, value: node.body || ''});
            text.addEventListener('input', () => change('body', text.value));
            inspector.append(field(strings.body, text));
        }
        if (node.type === 'condition') {
            inspector.append(field(strings.operator, dropdown([['and', strings.and], ['or', strings.or]], node.operator,
                (value) => change('operator', value))));
            const tests = element('fieldset', {}, element('legend', {className: 'h6', textContent: strings.tests}));
            predicates.forEach((item) => {
                const checkbox = element('input', {type: 'checkbox', checked: node.tests.includes(item)});
                checkbox.addEventListener('change', () => {
                    const values = new Set(node.tests);
                    if (checkbox.checked) {
                        values.add(item);
                    } else {
                        values.delete(item);
                    }
                    change('tests', Array.from(values));
                });
                tests.append(element('label', {className: 'd-block'}, checkbox, strings['test_' + item]));
            });
            inspector.append(tests);
        }
        const destinations = [['', strings.emptyport], ...nodeOptions().filter(([id]) => id !== node.id)];
        (ports[node.type] || []).forEach((port) => {
            inspector.append(field(strings['port_' + port], dropdown(destinations, node[port] || '', (value) => {
                change(port, value);
                draw();
            })));
        });
        const move = (x, y) => {
            node.x = position(node.x + x);
            node.y = position(node.y + y);
            sync();
            renderCanvas();
        };
        const tools = element('div', {className: 'sf-actions my-3'},
            button(strings.left, () => move(-24, 0)), button(strings.right, () => move(24, 0)),
            button(strings.up, () => move(0, -24)), button(strings.down, () => move(0, 24)));
        if (graph.nodes.length > 1) {
            tools.append(button(strings.delete, () => {
                graph.nodes = graph.nodes.filter((item) => item.id !== node.id);
                graph.nodes.forEach((item) => (ports[item.type] || []).forEach((port) => {
                    if (item[port] === node.id) {
                        item[port] = '';
                    }
                }));
                if (graph.entry === node.id) {
                    graph.entry = graph.nodes[0].id;
                }
                selected = graph.entry;
                render();
            }, 'outline-danger'));
        }
        inspector.append(tools);
    }

    function render() {
        renderCanvas();
        renderInspector();
        sync();
    }

    let kind = 'condition';
    palette.append(dropdown(Object.keys(ports).map((type) => [type, strings['node_' + type]]), kind, (value) => {
        kind = value;
    }), button(strings.addnode, () => {
        if (graph.nodes.length >= 40) {
            status.textContent = strings.limitnodes;
            return;
        }
        let index = graph.nodes.length + 1;
        while (graph.nodes.some((item) => item.id === 'node' + index)) {
            index++;
        }
        const node = {id: 'node' + index, type: kind, label: '', x: 32 + (index % 3) * 205,
            y: 30 + Math.floor(index / 3) * 100};
        Object.assign(node, {
            condition: {operator: 'and', tests: ['hascourse']}, delay: {minutes: 5}, approval: {minutes: 1440},
            suspend: {value: 1}, visibility: {value: 1}, enrol: {roleid: 0}, notify: {body: ''},
        }[kind] || {});
        graph.nodes.push(node);
        selected = node.id;
        status.textContent = title(node);
        render();
        cards.get(node.id).focus();
    }, 'outline-primary'));
    renderTrigger();
    render();
};
