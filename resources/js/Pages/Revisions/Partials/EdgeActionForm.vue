<script setup>
/**
 * Handles: create_edge, delete_edge
 *
 * Props:
 *   modelValue          — local form object (v-model)
 *   actionType          — 'create_edge' | 'delete_edge'
 *   edgeTypes           — Array of EdgeType (with startVertex, endVertex)
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

const selectedEdgeType = computed(() =>
    props.edgeTypes.find((et) => et.age_label_name === props.modelValue.edge_type_label) ?? null,
);

const startVertexTypeLabels = computed(() => {
    const label = selectedEdgeType.value?.start_vertex?.age_label_name
        ?? selectedEdgeType.value?.startVertex?.age_label_name
        ?? null;

    return label ? [label] : null;
});

const endVertexTypeLabels = computed(() => {
    const label = selectedEdgeType.value?.end_vertex?.age_label_name
        ?? selectedEdgeType.value?.endVertex?.age_label_name
        ?? null;

    return label ? [label] : null;
});

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
                @change="update('edge_type_label', $event.target.value || null)"
            >
                <option value="">— 請選擇 —</option>
                <option
                    v-for="et in edgeTypes"
                    :key="et.id"
                    :value="et.age_label_name"
                >
                    {{ et.name }} ({{ (et.start_vertex ?? et.startVertex).name }} → {{ (et.end_vertex ?? et.endVertex).name }})
                </option>
            </select>
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
                        @change="update('start_vertex_ref_order', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
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
                        @change="update('end_vertex_ref_order', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
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
                placeholder="搜尋終止 Vertex 名稱或 ID…"
                @update:model-value="onEndVertexIdUpdate"
            />
        </div>
    </template>

    <!-- delete_edge -->
    <template v-if="actionType === 'delete_edge'">
        <div class="mb-3">
            <label class="col-form-label fw-semibold">目標 Edge</label>
            <div class="form-text mb-1">搜尋既有 Edge（可依起點／終點名稱或 ID）：</div>
            <AgeEntitySearch
                :model-value="modelValue.target_age_id"
                :search-url="routeSearchEdges"
                entity-kind="edge"
                placeholder="搜尋 Edge…"
                required
                @update:model-value="update('target_age_id', $event)"
            />
        </div>
    </template>
</template>
