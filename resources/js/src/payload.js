/**
 * Builds the payload a trigger sends to the host from the element's attributes.
 *
 * Only attributes are read, nothing is evaluated. Data keys come from the attribute name
 * (kebab → snake) and must be plain identifiers, so a planted `data-__proto__` is dropped.
 */
const DATA_KEY = /^[a-z][a-z0-9_]*$/;

export function payloadFromElement(element, exclusions) {
    const target = (element.getAttribute('wire:modal') ?? '').trim();

    if (target === '') {
        return null;
    }

    const payload = Object.create(null);
    const [component, inlineAction] = target.split('@');

    payload.component = component.trim();

    const action = element.getAttribute('wire:modal.action') ?? inlineAction;

    if (action && action.trim() !== '') {
        payload.action = action.trim();
    }

    const id = element.getAttribute('wire:modal.id');

    if (id !== null && id !== '') {
        payload.id = id;
    }

    const key = element.getAttribute('wire:modal.key');

    if (key !== null && key.trim() !== '') {
        payload.key = key.trim();
    }

    payload.data = dataFromElement(element, exclusions);

    return payload;
}

export function dataFromElement(element, exclusions) {
    const data = Object.create(null);

    for (const attribute of element.attributes) {
        if (!attribute.name.startsWith('data-')) {
            continue;
        }

        const name = attribute.name.slice(5);

        if (exclusions.some((prefix) => name.startsWith(prefix))) {
            continue;
        }

        const key = name.replace(/-/g, '_');

        if (!DATA_KEY.test(key)) {
            continue;
        }

        data[key] = attribute.value;
    }

    return data;
}

/**
 * Convert the historical JS object literal (`{action:'x', component:'y', data:{a:'1'}}`) to a
 * payload without evaluating it. Bare keys are quoted, single-quoted strings become
 * double-quoted, trailing commas are removed, then JSON.parse does the rest.
 */
export function payloadFromLegacyExpression(expression) {
    const json = expression
        .trim()
        .replace(/'([^'\\]*(?:\\.[^'\\]*)*)'/g, (_, value) => JSON.stringify(value.replace(/\\'/g, "'")))
        .replace(/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:/g, '$1"$2":')
        .replace(/,(\s*[}\]])/g, '$1');

    const parsed = JSON.parse(json);

    if (!parsed || typeof parsed !== 'object') {
        throw new Error('legacy trigger expression is not an object');
    }

    const payload = Object.create(null);

    if (typeof parsed.component === 'string') payload.component = parsed.component;
    if (typeof parsed.action === 'string') payload.action = parsed.action;
    if (parsed.id !== undefined && parsed.id !== null) payload.id = String(parsed.id);
    if (typeof parsed.modal === 'string') payload.modal = parsed.modal;
    if (typeof parsed.key === 'string') payload.key = parsed.key;

    payload.data = parsed.data && typeof parsed.data === 'object' ? parsed.data : {};

    return payload;
}
