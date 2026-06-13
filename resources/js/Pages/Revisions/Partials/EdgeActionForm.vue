<script setup>
/**
 * Handles: create_edge, delete_edge
 *
 * Props:
 *   modelValue          — local form object (v-model)
 *   actionType          — 'create_edge' | 'delete_edge'
 *   edgeTypes           — Array of EdgeType (with startVertex, endVertex)
 *   createVertexActions — Array of actions with action === 'create_vertex'
 */
const props = defineProps({
    modelValue: Object,
    actionType: String,
    edgeTypes: Array,
    createVertexActions: Array,
});

const emit = defineEmits(['update:modelValue']);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
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
                    {{ et.name }} ({{ et.startVertex.name }} → {{ et.endVertex.name }})
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
            <div class="form-text mb-1">或直接輸入既有 Vertex AGE ID：</div>
            <input
                type="number"
                class="form-control"
                :value="modelValue.start_vertex_age_id"
                placeholder="起始 Vertex AGE ID"
                @input="update('start_vertex_age_id', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
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
            <div class="form-text mb-1">或直接輸入既有 Vertex AGE ID：</div>
            <input
                type="number"
                class="form-control"
                :value="modelValue.end_vertex_age_id"
                placeholder="終止 Vertex AGE ID"
                @input="update('end_vertex_age_id', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
            />
        </div>
    </template>

    <!-- delete_edge -->
    <template v-if="actionType === 'delete_edge'">
        <div class="mb-3">
            <label class="col-form-label fw-semibold">目標 Edge</label>
            <div class="form-text mb-1">輸入既有 Edge AGE ID：</div>
            <input
                type="number"
                class="form-control"
                :value="modelValue.target_age_id"
                placeholder="AGE edge ID"
                required
                @input="update('target_age_id', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
            />
        </div>
    </template>
</template>
