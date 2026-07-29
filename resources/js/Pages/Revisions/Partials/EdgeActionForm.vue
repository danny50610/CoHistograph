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
import { computed } from 'vue';
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

function formatTypeLabels(vertices) {
    const unique = [];
    const seen = new Set();

    for (const vertex of vertices) {
        if (!vertex?.age_label_name || seen.has(vertex.age_label_name)) {
            continue;
        }
        seen.add(vertex.age_label_name);
        unique.push(vertex);
    }

    if (unique.length === 0) {
        return null;
    }

    return unique.map((vertex) => `${vertex.name} (${vertex.age_label_name})`).join('、');
}

const selectedEdgeType = computed(() =>
    props.edgeTypes.find((et) => et.age_label_name === props.modelValue.edge_type_label) ?? null,
);

const startVertexTypeLabels = computed(() => {
    const labels = (selectedEdgeType.value?.vertex_pairs ?? [])
        .map((pair) => pair.start_vertex?.age_label_name)
        .filter((label) => typeof label === 'string' && label !== '');

    return labels.length > 0 ? [...new Set(labels)] : null;
});

const endVertexTypeLabels = computed(() => {
    const labels = (selectedEdgeType.value?.vertex_pairs ?? [])
        .map((pair) => pair.end_vertex?.age_label_name)
        .filter((label) => typeof label === 'string' && label !== '');

    return labels.length > 0 ? [...new Set(labels)] : null;
});

const startVertexTypeDisplay = computed(() => {
    const vertices = (selectedEdgeType.value?.vertex_pairs ?? []).map((pair) => pair.start_vertex);

    return formatTypeLabels(vertices);
});

const endVertexTypeDisplay = computed(() => {
    const vertices = (selectedEdgeType.value?.vertex_pairs ?? []).map((pair) => pair.end_vertex);

    return formatTypeLabels(vertices);
});

const edgeTypeOptions = computed(() =>
    (props.edgeTypes ?? []).map((et) => ({
        value: et.age_label_name,
        label: `${et.name} (${formatPairSummary(et)})`,
    })),
);

function onEdgeTypeChange(value) {
    emit('update:modelValue', {
        ...props.modelValue,
        edge_type_label: value || null,
        start_vertex_age_id: null,
        end_vertex_age_id: null,
    });
}

function onStartVertexIdUpdate(value) {
    const next = { ...props.modelValue, start_vertex_age_id: value };
    if (value !== null && value !== undefined) {
        next.start_vertex_ref_order = null;
    }
    emit('update:modelValue', next);
}

function onEndVertexIdUpdate(value) {
    const next = { ...props.modelValue, end_vertex_age_id: value };
    if (value !== null && value !== undefined) {
        next.end_vertex_ref_order = null;
    }
    emit('update:modelValue', next);
}

function onStartRefOrderChange(value) {
    const next = {
        ...props.modelValue,
        start_vertex_ref_order: value !== '' ? parseInt(value, 10) : null,
    };
    if (value !== '') {
        next.start_vertex_age_id = null;
    }
    emit('update:modelValue', next);
}

function onEndRefOrderChange(value) {
    const next = {
        ...props.modelValue,
        end_vertex_ref_order: value !== '' ? parseInt(value, 10) : null,
    };
    if (value !== '') {
        next.end_vertex_age_id = null;
    }
    emit('update:modelValue', next);
}
</script>

<template>
    <!-- create_edge -->
    <template v-if="actionType === 'create_edge'">
        <!-- Edge type -->
        <div class="mb-3">
            <label class="col-form-label fw-semibold">Edge Type</label>
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
            <div class="form-text text-secondary">請先選擇 Edge Type，再搜尋起迄 Vertex</div>
        </div>

        <!-- Start vertex -->
        <div class="mb-3">
            <label class="col-form-label fw-semibold">起始 Vertex</label>
            <template v-if="createVertexActions.length > 0">
                <div class="mb-2">
                    <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                    <select
                        class="form-select"
                        :value="modelValue.start_vertex_ref_order !== null && modelValue.start_vertex_ref_order !== undefined ? String(modelValue.start_vertex_ref_order) : ''"
                        @change="onStartRefOrderChange($event.target.value)"
                    >
                        <option value="">— 不選擇 —</option>
                        <option
                            v-for="a in createVertexActions"
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
                locked-type-placeholder="— 請先選擇 Edge Type —"
                locked-type-pending-hint="請先選擇 Edge Type，起始 Vertex 類型才會確定"
                locked-type-hint="搜尋僅限允許的 Vertex 類型"
                placeholder="搜尋起始 Vertex 名稱或 ID…"
                @update:model-value="onStartVertexIdUpdate"
            />
        </div>

        <!-- End vertex -->
        <div class="mb-3">
            <label class="col-form-label fw-semibold">終止 Vertex</label>
            <template v-if="createVertexActions.length > 0">
                <div class="mb-2">
                    <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                    <select
                        class="form-select"
                        :value="modelValue.end_vertex_ref_order !== null && modelValue.end_vertex_ref_order !== undefined ? String(modelValue.end_vertex_ref_order) : ''"
                        @change="onEndRefOrderChange($event.target.value)"
                    >
                        <option value="">— 不選擇 —</option>
                        <option
                            v-for="a in createVertexActions"
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
                locked-type-placeholder="— 請先選擇 Edge Type —"
                locked-type-pending-hint="請先選擇 Edge Type，終止 Vertex 類型才會確定"
                locked-type-hint="搜尋僅限允許的 Vertex 類型"
                placeholder="搜尋終止 Vertex 名稱或 ID…"
                @update:model-value="onEndVertexIdUpdate"
            />
        </div>
    </template>

    <!-- delete_edge -->
    <template v-if="actionType === 'delete_edge'">
        <div class="mb-3">
            <label class="col-form-label fw-semibold">目標 Edge</label>
            <div class="form-text mb-1">搜尋並刪除圖資料庫中的既有邊（不會刪除 Edge Type／schema）。先選類型：</div>
            <AgeEntitySearch
                :model-value="modelValue.target_age_id"
                :search-url="routeSearchEdges"
                entity-kind="edge"
                :type-options="edgeTypeOptions"
                require-type
                type-placeholder="— 請先選擇 Edge Type —"
                placeholder="搜尋起點／終點名稱或 ID…"
                required
                @update:model-value="update('target_age_id', $event)"
            />
        </div>
    </template>
</template>
