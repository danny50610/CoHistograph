<script setup>
/**
 * Handles: create_edge_property, update_edge_property, delete_edge_property
 *
 * Props:
 *   modelValue        — local form object (v-model)
 *   actionType        — 'create_edge_property' | 'update_edge_property' | 'delete_edge_property'
 *   edgeTypes         — Array of EdgeType (with properties loaded)
 *   createEdgeActions — Array of actions with action === 'create_edge'
 *   routeSearchEdges  — Edge search endpoint URL
 */
import { computed, ref, watch } from 'vue';
import AgeEntitySearch from './AgeEntitySearch.vue';

const props = defineProps({
    modelValue: Object,
    actionType: String,
    edgeTypes: Array,
    graphLocales: Object,
    createEdgeActions: Array,
    routeSearchEdges: String,
});

const emit = defineEmits(['update:modelValue']);

const selectedTypeLabel = ref(null);

const edgeTypeOptions = computed(() =>
    (props.edgeTypes ?? []).map((et) => ({
        value: et.age_label_name,
        label: `${et.name} (${et.start_vertex?.name ?? '?'} → ${et.end_vertex?.name ?? '?'})`,
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
 * Determine the edge label for the currently selected target.
 * Prefer same-revision create_edge ref, otherwise the chosen / searched edge type.
 */
const resolvedEdgeLabel = computed(() => {
    if (props.modelValue.target_ref_order !== null && props.modelValue.target_ref_order !== undefined) {
        const refAction = props.createEdgeActions.find(
            (a) => a.order === props.modelValue.target_ref_order,
        );
        return refAction?.edge_type_label ?? null;
    }

    return selectedTypeLabel.value;
});

/** Properties filtered to the selected edge type, or all if no type resolved */
const filteredProperties = computed(() => {
    if (!resolvedEdgeLabel.value) {
        return props.edgeTypes.flatMap((et) =>
            (et.properties ?? []).map((p) => ({ ...p, edgeName: et.name })),
        );
    }
    const et = props.edgeTypes.find((e) => e.age_label_name === resolvedEdgeLabel.value);
    return (et?.properties ?? []).map((p) => ({ ...p, edgeName: et.name }));
});

const isCreate = computed(() => props.actionType === 'create_edge_property');
const isUpdate = computed(() => props.actionType === 'update_edge_property');

function propertyOptionLabel(prop) {
    if (!prop.locale) {
        return `${prop.edgeName} / ${prop.name} (${prop.age_property_name})`;
    }

    const localeLabel = props.graphLocales?.[prop.locale] ?? prop.locale;

    return `${prop.edgeName} / ${prop.name}（${localeLabel}） [${prop.locale}] (${prop.age_property_name})`;
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

function onExistingEdgeIdUpdate(value) {
    const next = { ...props.modelValue, target_age_id: value };
    if (value !== null && value !== undefined) {
        next.target_ref_order = null;
    }
    emit('update:modelValue', next);
}

function onExistingEdgeSelected(item) {
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
    <!-- Target edge -->
    <div class="mb-3">
        <label class="col-form-label fw-semibold">目標 Edge</label>

        <template v-if="createEdgeActions.length > 0">
            <div class="mb-2">
                <div class="form-text mb-1">指向本修訂內的新增 Edge 操作：</div>
                <select
                    class="form-select"
                    :value="modelValue.target_ref_order !== null && modelValue.target_ref_order !== undefined ? String(modelValue.target_ref_order) : ''"
                    @change="onTargetRefOrderChange($event.target.value)"
                >
                    <option value="">— 不選擇 —</option>
                    <option
                        v-for="a in createEdgeActions"
                        :key="a.order"
                        :value="String(a.order)"
                    >
                        #{{ a.order + 1 }}：新增 {{ a.edge_type_label }} Edge
                    </option>
                </select>
            </div>
        </template>

        <div class="form-text mb-1">或搜尋既有 Edge（先選類型）：</div>
        <AgeEntitySearch
            :model-value="modelValue.target_age_id"
            :search-url="routeSearchEdges"
            entity-kind="edge"
            :type-options="edgeTypeOptions"
            require-type
            type-placeholder="— 請先選擇 Edge 類型 —"
            placeholder="搜尋起點／終點名稱或 ID…"
            @update:model-value="onExistingEdgeIdUpdate"
            @select="onExistingEdgeSelected"
            @type-change="onSearchTypeChange"
        />
    </div>

    <!-- Property name (filtered by resolved edge type) -->
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
