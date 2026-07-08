<script setup>
/**
 * Handles: create_vertex_property, update_vertex_property, delete_vertex_property
 *
 * Props:
 *   modelValue          — local form object (v-model)
 *   actionType          — 'create_vertex_property' | 'update_vertex_property' | 'delete_vertex_property'
 *   vertexTypes         — Array of VertexType (with properties loaded)
 *   createVertexActions — Array of actions with action === 'create_vertex'
 */
import { computed } from 'vue';

const props = defineProps({
    modelValue: Object,
    actionType: String,
    vertexTypes: Array,
    graphLocales: Object,
    createVertexActions: Array,
});

const emit = defineEmits(['update:modelValue']);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

/**
 * Determine the vertex label for the currently selected target.
 * If a ref_order is selected, find the matching create_vertex action's vertex_type_label.
 * Otherwise fall back to null (show all properties).
 */
const resolvedVertexLabel = computed(() => {
    if (props.modelValue.target_ref_order !== null && props.modelValue.target_ref_order !== undefined) {
        const refAction = props.createVertexActions.find(
            (a) => a.order === props.modelValue.target_ref_order,
        );
        return refAction?.vertex_type_label ?? null;
    }
    return null;
});

/** Properties filtered to the selected vertex type, or all if no type resolved */
const filteredProperties = computed(() => {
    if (!resolvedVertexLabel.value) {
        return props.vertexTypes.flatMap((vt) =>
            (vt.properties ?? []).map((p) => ({ ...p, vertexName: vt.name })),
        );
    }
    const vt = props.vertexTypes.find((v) => v.age_label_name === resolvedVertexLabel.value);
    return (vt?.properties ?? []).map((p) => ({ ...p, vertexName: vt.name }));
});

const isCreate = computed(() => props.actionType === 'create_vertex_property');
const isUpdate = computed(() => props.actionType === 'update_vertex_property');

function propertyOptionLabel(prop) {
    if (!prop.locale) {
        return `${prop.vertexName} / ${prop.name} (${prop.age_property_name})`;
    }

    const localeLabel = props.graphLocales?.[prop.locale] ?? prop.locale;

    return `${prop.vertexName} / ${prop.name}（${localeLabel}） [${prop.locale}] (${prop.age_property_name})`;
}
</script>

<template>
    <!-- Target vertex -->
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

    <!-- Property name (filtered by resolved vertex type) -->
    <div class="mb-3">
        <label class="col-form-label fw-semibold">屬性名稱 (AGE property name)</label>
        <select
            class="form-select"
            :value="modelValue.age_property_name"
            required
            @change="update('age_property_name', $event.target.value || null)"
        >
            <option value="">— 請選擇 —</option>
            <option
                v-for="prop in filteredProperties"
                :key="prop.id"
                :value="prop.age_property_name"
            >
                {{ propertyOptionLabel(prop) }}
            </option>
        </select>
    </div>

    <!-- Value: only for create / update -->
    <div v-if="isCreate || isUpdate" class="mb-3">
        <label class="col-form-label fw-semibold">屬性值</label>
        <input
            type="text"
            class="form-control"
            :value="modelValue.value"
            placeholder="屬性值"
            required
            @input="update('value', $event.target.value || null)"
        />
    </div>
</template>
