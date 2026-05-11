<script setup>
/**
 * Handles: create_vertex, delete_vertex
 *
 * Props:
 *   modelValue         — local form object (v-model)
 *   actionType         — 'create_vertex' | 'delete_vertex'
 *   vertexTypes        — Array of VertexType
 *   createVertexActions — Array of actions with action === 'create_vertex'
 */
const props = defineProps({
    modelValue: Object,
    actionType: String,
    vertexTypes: Array,
    createVertexActions: Array,
});

const emit = defineEmits(['update:modelValue']);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}
</script>

<template>
    <!-- create_vertex: choose vertex type -->
    <template v-if="actionType === 'create_vertex'">
        <div class="mb-3">
            <label class="col-form-label fw-semibold">Vertex 類型</label>
            <select
                class="form-select"
                :value="modelValue.vertex_type_label"
                required
                @change="update('vertex_type_label', $event.target.value || null)"
            >
                <option value="">— 請選擇 —</option>
                <option
                    v-for="vt in vertexTypes"
                    :key="vt.id"
                    :value="vt.age_label_name"
                >
                    {{ vt.name }} ({{ vt.age_label_name }})
                </option>
            </select>
            <div class="form-text text-secondary">新 Vertex 將依此類型建立</div>
        </div>
    </template>

    <!-- delete_vertex: ref order or direct age id -->
    <template v-if="actionType === 'delete_vertex'">
        <div class="mb-3">
            <label class="col-form-label fw-semibold">目標 Vertex</label>

            <template v-if="createVertexActions.length > 0">
                <div class="mb-2">
                    <div class="form-text mb-1">指向本修訂內的新增 Vertex 操作：</div>
                    <select
                        class="form-select"
                        :value="modelValue.target_ref_order !== null && modelValue.target_ref_order !== undefined ? String(modelValue.target_ref_order) : ''"
                        @change="update('target_ref_order', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
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
                :value="modelValue.target_age_id"
                placeholder="AGE vertex ID"
                @input="update('target_age_id', $event.target.value !== '' ? parseInt($event.target.value, 10) : null)"
            />
        </div>
    </template>
</template>
