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
<<<<<<< HEAD
import { computed, ref, watch } from 'vue';
import AgeEntitySearch from './AgeEntitySearch.vue';
=======
import { computed } from 'vue';
import PropertyValueInput from './PropertyValueInput.vue';
>>>>>>> 8315815 (feat: 修訂編輯依屬性型別切換 value 輸入元件)

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

const selectedProperty = computed(() =>
    filteredProperties.value.find((p) => p.age_property_name === props.modelValue.age_property_name) ?? null,
);

const selectedPropertyType = computed(() => selectedProperty.value?.age_property_type ?? null);

const isCreate = computed(() => props.actionType === 'create_vertex_property');
const isUpdate = computed(() => props.actionType === 'update_vertex_property');

function propertyOptionLabel(prop) {
    const typeSuffix = prop.age_property_type ? ` [${prop.age_property_type}]` : '';

    if (!prop.locale) {
        return `${prop.vertexName} / ${prop.name} (${prop.age_property_name})${typeSuffix}`;
    }

    const localeLabel = props.graphLocales?.[prop.locale] ?? prop.locale;

    return `${prop.vertexName} / ${prop.name}（${localeLabel}） [${prop.locale}] (${prop.age_property_name})${typeSuffix}`;
}

function onPropertyChange(name) {
    emit('update:modelValue', {
        ...props.modelValue,
        age_property_name: name || null,
        value: null,
    });
}

function clearPropertyIfInvalid(nextState) {
    if (
        nextState.age_property_name &&
        !filteredProperties.value.some((p) => p.age_property_name === nextState.age_property_name)
    ) {
        nextState.age_property_name = null;
        nextState.value = null;
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
            @change="onPropertyChange($event.target.value)"
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
        <label class="col-form-label fw-semibold">
            屬性值
            <span v-if="selectedPropertyType" class="badge text-bg-secondary ms-1">{{ selectedPropertyType }}</span>
        </label>
        <PropertyValueInput
            :model-value="modelValue.value"
            :property-type="selectedPropertyType"
            @update:model-value="update('value', $event)"
        />
    </div>
</template>
