const REF_FIELDS = ['target_ref_order', 'start_vertex_ref_order', 'end_vertex_ref_order'];

export function mappingForDelete(count, deleted) {
    const mapping = {};

    for (let i = 0; i < count; i++) {
        if (i === deleted) {
            mapping[i] = null;
        } else if (i > deleted) {
            mapping[i] = i - 1;
        } else {
            mapping[i] = i;
        }
    }

    return mapping;
}

export function mappingForMove(count, from, to) {
    const oldOrder = Array.from({ length: count }, (_, i) => i);
    const [moved] = oldOrder.splice(from, 1);
    oldOrder.splice(to, 0, moved);

    const mapping = {};
    oldOrder.forEach((oldIndex, newIndex) => {
        mapping[oldIndex] = newIndex;
    });

    return mapping;
}

export function remapActions(actions, mapping) {
    for (const action of actions) {
        for (const field of REF_FIELDS) {
            if (action[field] === null || action[field] === undefined || action[field] === '') {
                continue;
            }

            const newRef = mapping[action[field]];
            action[field] = newRef === undefined ? null : newRef;
        }
    }
}
