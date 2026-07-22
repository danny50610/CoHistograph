<script setup>
/**
 * Handles: create_vertex_property, update_vertex_property, delete_vertex_property
 *
 * Props:
 *   modelValue          — local form object (v-model)
 *   actionType          — 'create_vertex_property' | 'update_vertex_property' | 'delete_vertex_property'
 *   vertexTypes         — Array of VertexType (with properties loaded)
 *   createVertexActions — Array of actions with action === 'create_vertex'
 *   routeSearchVertices — Vertex search endpoint URL
 */
import { computed, ref, watch } from 'vue';
import AgeEntitySearch from './AgeEntitySearch.vue';

const props = defineProps({
    modelValue: Object,
    actionType: String,
    vertexTypes: Array,
    graphLocales: Object,
    createVertexActions: Array,
    routeSearchVertices: String,
});

const emit = defineEmits(['update:modelValue']);

const selectedTypeLabel = ref(null);

const vertexTypeOptions = computed(() =>
    (props.vertexTypes ?? []).map((vt) => ({
        value: vt.age_label_name,
        label: `${vt.name} (${vt.age_label_name})`,
    })),
);

function update(field, value) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

watch(
    () => props.modelValue.target_ref_order,
    (refOrder) => {
        if (refOrder !== null && refOrder !== undefined) {
            selectedTypeLabel.value = null;
        }
    },
);

/**
 * Determine the vertex label for the currently selected target.
 * Prefer same-revision create_vertex ref, otherwise the chosen / searched vertex type.
 */
const resolvedVertexLabel = computed(() => {
    if (props.modelValue.target_ref_order !== null && props.modelValue.target_ref_order !== undefined) {
        const refAction = props.createVertexActions.find(
            (a) => a.order === props.modelValue.target_ref_order,
        );
        return refAction?.vertex_type_label ?? null;
    }

    return selectedTypeLabel.value;
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

function clearPropertyIfInvalid(nextState) {
    if (
        nextState.age_property_name &&
        !filteredProperties.value.some((p) => p.age_property_name === nextState.age_property_name)
    ) {
        nextState.age_property_name = null;
    }

    return nextState;
}

function onSearchTypeChange(typeLabel) {
    selectedTypeLabel.value = typeLabel;
    const next = clearPropertyIfInvalid({
        ...props.modelValue,
        target_age_id: null,
        target_ref_order: null,
    });
    emit('update:modelValue', next);
}

function onExistingVertexIdUpdate(value) {
    const next = { ...props.modelValue, target_age_id: value };
    if (value !== null && value !== undefined) {
        next.target_ref_order = null;
    } else if (!selectedTypeLabel.value) {
        // keep type filter from type selector
    }
    emit('update:modelValue', next);
}

function onExistingVertexSelected(item) {
    selectedTypeLabel.value = item?.type_label ?? selectedTypeLabel.value;
    const next = clearPropertyIfInvalid({
        ...props.modelValue,
        target_age_id: item.id,
        target_ref_order: null,
    });
    emit('update:modelValue', next);
}

function onTargetRefOrderChange(value) {
    const next = {
        ...props.modelValue,
        target_ref_order: value !== '' ? parseInt(value, 10) : null,
    };
    if (value !== '') {
        next.target_age_id = null;
        selectedTypeLabel.value = null;
    }
    emit('update:modelValue', clearPropertyIfInvalid(next));
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
                    @change="onTargetRefOrderChange($event.target.value)"
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

        <div class="form-text mb-1">或搜尋既有 Vertex（先選類型）：</div>
        <AgeEntitySearch
            :model-value="modelValue.target_age_id"
            :search-url="routeSearchVertices"
            entity-kind="vertex"
            :type-options="vertexTypeOptions"
            require-type
            type-placeholder="— 請先選擇 Vertex 類型 —"
            placeholder="搜尋 Vertex 名稱或 ID…"
            @update:model-value="onExistingVertexIdUpdate"
            @select="onExistingVertexSelected"
            @type-change="onSearchTypeChange"
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
