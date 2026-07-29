<script setup>
/**
 * Handles: create_edge, delete_edge
 *
 * Props:
 *   modelValue          — local form object (v-model)
 *   actionType          — 'create_edge' | 'delete_edge'
 *   edgeTypes           — Array of EdgeType (with vertex_pairs)
 *   createVertexActions — Array of actions with action === 'create_vertex'
 *   routeSearchVertices — Vertex search endpoint URL
 *   routeSearchEdges    — Edge search endpoint URL
 */
import { computed, ref, watch } from 'vue';
import AgeEntitySearch from './AgeEntitySearch.vue';

const props = defineProps({
    modelValue: Object,
    actionType: String,
    edgeTypes: Array,
    createVertexActions: Array,
    routeSearchVertices: String,
    routeSearchEdges: String,
});

const emit = defineEmits(['update:modelValue']);

/** Resolved type labels for currently chosen start/end vertices (search or ref). */
const selectedStartTypeLabel = ref(null);
const selectedEndTypeLabel = ref(null);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

function formatPairSummary(edgeType) {
    const pairs = edgeType?.vertex_pairs ?? [];
    if (pairs.length === 0) {
        return '? → ?';
    }

    return pairs
        .map((pair) => `${pair.start_vertex?.name ?? '?'} → ${pair.end_vertex?.name ?? '?'}`)
        .join(' / ');
}

function uniqueVerticesByLabel(vertices) {
    const unique = [];
    const seen = new Set();

    for (const vertex of vertices) {
        if (! vertex?.age_label_name || seen.has(vertex.age_label_name)) {
            continue;
        }
        seen.add(vertex.age_label_name);
        unique.push(vertex);
    }

    return unique;
}

function formatTypeLabels(vertices) {
    const unique = uniqueVerticesByLabel(vertices);

    if (unique.length === 0) {
        return null;
    }

    return unique.map((vertex) => `${vertex.name} (${vertex.age_label_name})`).join('、');
}

function pairList(edgeType) {
    return edgeType?.vertex_pairs ?? [];
}

function labelsFromPairs(pairs, side) {
    const key = side === 'start' ? 'start_vertex' : 'end_vertex';

    return [...new Set(
        pairs
            .map((pair) => pair[key]?.age_label_name)
            .filter((label) => typeof label === 'string' && label !== ''),
    )];
}

function verticesFromPairs(pairs, side) {
    const key = side === 'start' ? 'start_vertex' : 'end_vertex';

    return pairs.map((pair) => pair[key]);
}

function pairsCompatibleWithEnd(pairs, endLabel) {
    if (! endLabel) {
        return pairs;
    }

    return pairs.filter((pair) => pair.end_vertex?.age_label_name === endLabel);
}

function pairsCompatibleWithStart(pairs, startLabel) {
    if (! startLabel) {
        return pairs;
    }

    return pairs.filter((pair) => pair.start_vertex?.age_label_name === startLabel);
}

const selectedEdgeType = computed(() =>
    props.edgeTypes.find((et) => et.age_label_name === props.modelValue.edge_type_label) ?? null,
);

const allowedStartPairs = computed(() =>
    pairsCompatibleWithEnd(pairList(selectedEdgeType.value), selectedEndTypeLabel.value),
);

const allowedEndPairs = computed(() =>
    pairsCompatibleWithStart(pairList(selectedEdgeType.value), selectedStartTypeLabel.value),
);

const startVertexTypeLabels = computed(() => {
    if (! selectedEdgeType.value) {
        return null;
    }

    const labels = labelsFromPairs(allowedStartPairs.value, 'start');

    return labels.length > 0 ? labels : null;
});

const endVertexTypeLabels = computed(() => {
    if (! selectedEdgeType.value) {
        return null;
    }

    const labels = labelsFromPairs(allowedEndPairs.value, 'end');

    return labels.length > 0 ? labels : null;
});

const startVertexTypeDisplay = computed(() =>
    formatTypeLabels(verticesFromPairs(allowedStartPairs.value, 'start')),
);

const endVertexTypeDisplay = computed(() =>
    formatTypeLabels(verticesFromPairs(allowedEndPairs.value, 'end')),
);

const startCreateVertexActions = computed(() => {
    const allowed = startVertexTypeLabels.value;
    if (! allowed) {
        return [];
    }

    return (props.createVertexActions ?? []).filter(
        (action) => allowed.includes(action.vertex_type_label),
    );
});

const endCreateVertexActions = computed(() => {
    const allowed = endVertexTypeLabels.value;
    if (! allowed) {
        return [];
    }

    return (props.createVertexActions ?? []).filter(
        (action) => allowed.includes(action.vertex_type_label),
    );
});

const edgeTypeOptions = computed(() =>
    (props.edgeTypes ?? []).map((et) => ({
        value: et.age_label_name,
        label: `${et.name} (${formatPairSummary(et)})`,
    })),
);

function resolveRefTypeLabel(refOrder) {
    if (refOrder === null || refOrder === undefined) {
        return null;
    }

    const action = (props.createVertexActions ?? []).find((item) => item.order === refOrder);

    return action?.vertex_type_label ?? null;
}

function isPairAllowed(startLabel, endLabel) {
    if (! startLabel || ! endLabel || ! selectedEdgeType.value) {
        return true;
    }

    return pairList(selectedEdgeType.value).some(
        (pair) => pair.start_vertex?.age_label_name === startLabel
            && pair.end_vertex?.age_label_name === endLabel,
    );
}

function clearEndSelection(base) {
    return {
        ...base,
        end_vertex_age_id: null,
        end_vertex_ref_order: null,
    };
}

function clearStartSelection(base) {
    return {
        ...base,
        start_vertex_age_id: null,
        start_vertex_ref_order: null,
    };
}

watch(
    () => props.modelValue?.edge_type_label,
    () => {
        selectedStartTypeLabel.value = resolveRefTypeLabel(props.modelValue?.start_vertex_ref_order);
        selectedEndTypeLabel.value = resolveRefTypeLabel(props.modelValue?.end_vertex_ref_order);
    },
    { immediate: true },
);

watch(
    () => props.modelValue?.start_vertex_ref_order,
    (refOrder) => {
        if (refOrder !== null && refOrder !== undefined) {
            selectedStartTypeLabel.value = resolveRefTypeLabel(refOrder);
        } else if (props.modelValue?.start_vertex_age_id === null || props.modelValue?.start_vertex_age_id === undefined) {
            selectedStartTypeLabel.value = null;
        }
    },
);

watch(
    () => props.modelValue?.end_vertex_ref_order,
    (refOrder) => {
        if (refOrder !== null && refOrder !== undefined) {
            selectedEndTypeLabel.value = resolveRefTypeLabel(refOrder);
        } else if (props.modelValue?.end_vertex_age_id === null || props.modelValue?.end_vertex_age_id === undefined) {
            selectedEndTypeLabel.value = null;
        }
    },
);

function onEdgeTypeChange(value) {
    selectedStartTypeLabel.value = null;
    selectedEndTypeLabel.value = null;
    emit('update:modelValue', {
        ...props.modelValue,
        edge_type_label: value || null,
        start_vertex_age_id: null,
        end_vertex_age_id: null,
        start_vertex_ref_order: null,
        end_vertex_ref_order: null,
    });
}

function onStartVertexIdUpdate(value) {
    const next = { ...props.modelValue, start_vertex_age_id: value };
    if (value !== null && value !== undefined) {
        next.start_vertex_ref_order = null;
    } else {
        selectedStartTypeLabel.value = null;
    }
    emit('update:modelValue', next);
}

function onEndVertexIdUpdate(value) {
    const next = { ...props.modelValue, end_vertex_age_id: value };
    if (value !== null && value !== undefined) {
        next.end_vertex_ref_order = null;
    } else {
        selectedEndTypeLabel.value = null;
    }
    emit('update:modelValue', next);
}

function onStartVertexSelected(item) {
    const typeLabel = item?.type_label ?? null;
    selectedStartTypeLabel.value = typeLabel;

    let next = {
        ...props.modelValue,
        start_vertex_age_id: item?.id ?? null,
        start_vertex_ref_order: null,
    };

    if (selectedEndTypeLabel.value && ! isPairAllowed(typeLabel, selectedEndTypeLabel.value)) {
        selectedEndTypeLabel.value = null;
        next = clearEndSelection(next);
    }

    emit('update:modelValue', next);
}

function onEndVertexSelected(item) {
    const typeLabel = item?.type_label ?? null;
    selectedEndTypeLabel.value = typeLabel;

    let next = {
        ...props.modelValue,
        end_vertex_age_id: item?.id ?? null,
        end_vertex_ref_order: null,
    };

    if (selectedStartTypeLabel.value && ! isPairAllowed(selectedStartTypeLabel.value, typeLabel)) {
        selectedStartTypeLabel.value = null;
        next = clearStartSelection(next);
    }

    emit('update:modelValue', next);
}

function onStartVertexCleared() {
    selectedStartTypeLabel.value = null;
}

function onEndVertexCleared() {
    selectedEndTypeLabel.value = null;
}

function onStartRefOrderChange(value) {
    const refOrder = value !== '' ? parseInt(value, 10) : null;
    const typeLabel = resolveRefTypeLabel(refOrder);
    selectedStartTypeLabel.value = typeLabel;

    let next = {
        ...props.modelValue,
        start_vertex_ref_order: refOrder,
    };
    if (value !== '') {
        next.start_vertex_age_id = null;
    }

    if (selectedEndTypeLabel.value && ! isPairAllowed(typeLabel, selectedEndTypeLabel.value)) {
        selectedEndTypeLabel.value = null;
        next = clearEndSelection(next);
    }

    emit('update:modelValue', next);
}

function onEndRefOrderChange(value) {
    const refOrder = value !== '' ? parseInt(value, 10) : null;
    const typeLabel = resolveRefTypeLabel(refOrder);
    selectedEndTypeLabel.value = typeLabel;

    let next = {
        ...props.modelValue,
        end_vertex_ref_order: refOrder,
    };
    if (value !== '') {
        next.end_vertex_age_id = null;
    }

    if (selectedStartTypeLabel.value && ! isPairAllowed(selectedStartTypeLabel.value, typeLabel)) {
        selectedStartTypeLabel.value = null;
        next = clearStartSelection(next);
    }

    emit('update:modelValue', next);
}
</script>

<template>
    <!-- create_edge -->
    <template v-if="actionType === 'create_edge'">
        <!-- Edge type -->
        <div class="mb-3">
            <label class="col-form-label fw-semibold">Edge 類型</label>
            <select
                class="form-select"
                :value="modelValue.edge_type_label"
                required
                @change="onEdgeTypeChange($event.target.value)"
            >
                <option value="">— 請選擇 —</option>
                <option
                    v-for="et in edgeTypes"
                    :key="et.id"
                    :value="et.age_label_name"
                >
                    {{ et.name }} ({{ formatPairSummary(et) }})
                </option>
            </select>
            <div class="form-text text-secondary">請先選擇 Edge 類型，再搜尋起迄 Vertex（僅允許已定義的起迄組合）</div>
        </div>

        <!-- Start vertex -->
        <div class="mb-3">
            <label class="col-form-label fw-semibold">起始 Vertex</label>
            <template v-if="startCreateVertexActions.length > 0">
                <div class="mb-2">
                    <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                    <select
                        class="form-select"
                        :value="modelValue.start_vertex_ref_order !== null && modelValue.start_vertex_ref_order !== undefined ? String(modelValue.start_vertex_ref_order) : ''"
                        @change="onStartRefOrderChange($event.target.value)"
                    >
                        <option value="">— 不選擇 —</option>
                        <option
                            v-for="a in startCreateVertexActions"
                            :key="a.order"
                            :value="String(a.order)"
                        >
                            #{{ a.order + 1 }}：新增 {{ a.vertex_type_label }} Vertex
                        </option>
                    </select>
                </div>
            </template>
            <div class="form-text mb-1">或搜尋既有 Vertex：</div>
            <AgeEntitySearch
                :model-value="modelValue.start_vertex_age_id"
                :search-url="routeSearchVertices"
                entity-kind="vertex"
                :type-labels="startVertexTypeLabels"
                :locked-type-display="startVertexTypeDisplay"
                show-locked-type
                require-type
                locked-type-placeholder="— 請先選擇 Edge 類型 —"
                locked-type-pending-hint="請先選擇 Edge 類型，起始 Vertex 類型才會確定"
                locked-type-hint="搜尋僅限目前允許的起點類型（依已選終點／Edge 類型 pair）"
                placeholder="搜尋起始 Vertex 名稱或 ID…"
                @update:model-value="onStartVertexIdUpdate"
                @select="onStartVertexSelected"
                @clear="onStartVertexCleared"
            />
        </div>

        <!-- End vertex -->
        <div class="mb-3">
            <label class="col-form-label fw-semibold">終止 Vertex</label>
            <template v-if="endCreateVertexActions.length > 0">
                <div class="mb-2">
                    <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                    <select
                        class="form-select"
                        :value="modelValue.end_vertex_ref_order !== null && modelValue.end_vertex_ref_order !== undefined ? String(modelValue.end_vertex_ref_order) : ''"
                        @change="onEndRefOrderChange($event.target.value)"
                    >
                        <option value="">— 不選擇 —</option>
                        <option
                            v-for="a in endCreateVertexActions"
                            :key="a.order"
                            :value="String(a.order)"
                        >
                            #{{ a.order + 1 }}：新增 {{ a.vertex_type_label }} Vertex
                        </option>
                    </select>
                </div>
            </template>
            <div class="form-text mb-1">或搜尋既有 Vertex：</div>
            <AgeEntitySearch
                :model-value="modelValue.end_vertex_age_id"
                :search-url="routeSearchVertices"
                entity-kind="vertex"
                :type-labels="endVertexTypeLabels"
                :locked-type-display="endVertexTypeDisplay"
                show-locked-type
                require-type
                locked-type-placeholder="— 請先選擇 Edge 類型 —"
                locked-type-pending-hint="請先選擇 Edge 類型，終止 Vertex 類型才會確定"
                locked-type-hint="搜尋僅限目前允許的終點類型（依已選起點／Edge 類型 pair）"
                placeholder="搜尋終止 Vertex 名稱或 ID…"
                @update:model-value="onEndVertexIdUpdate"
                @select="onEndVertexSelected"
                @clear="onEndVertexCleared"
            />
        </div>
    </template>

    <!-- delete_edge -->
    <template v-if="actionType === 'delete_edge'">
        <div class="mb-3">
            <label class="col-form-label fw-semibold">目標 Edge</label>
            <div class="form-text mb-1">搜尋既有 Edge（先選類型）：</div>
            <AgeEntitySearch
                :model-value="modelValue.target_age_id"
                :search-url="routeSearchEdges"
                entity-kind="edge"
                :type-options="edgeTypeOptions"
                require-type
                type-placeholder="— 請先選擇 Edge 類型 —"
                placeholder="搜尋起點／終點名稱或 ID…"
                required
                @update:model-value="update('target_age_id', $event)"
            />
        </div>
    </template>
</template>
