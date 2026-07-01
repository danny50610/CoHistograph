<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import VertexActionForm from './VertexActionForm.vue';
import EdgeActionForm from './EdgeActionForm.vue';
import VertexPropertyActionForm from './VertexPropertyActionForm.vue';
import EdgePropertyActionForm from './EdgePropertyActionForm.vue';

const props = defineProps({
    show: Boolean,
    editingAction: Object, // null = add mode
    vertexTypes: Array,
    edgeTypes: Array,
    graphLocales: Object,
    createVertexActions: Array,
    createEdgeActions: Array,
});

const emit = defineEmits(['confirm', 'close']);

// ── state ─────────────────────────────────────────────────────────────

/** Which step we are on: 'type-select' or 'form' */
const step = ref('type-select');
const selectedType = ref(null);

/** The local form data being edited in the modal */
const localForm = ref(emptyForm());

function emptyForm() {
    return {
        action: null,
        target_age_id: null,
        target_ref_order: null,
        vertex_type_label: null,
        edge_type_label: null,
        start_vertex_age_id: null,
        start_vertex_ref_order: null,
        end_vertex_age_id: null,
        end_vertex_ref_order: null,
        age_property_name: null,
        value: null,
    };
}

// ── sync with show / editingAction ───────────────────────────────────

watch(
    () => props.show,
    (val) => {
        if (val) {
            if (props.editingAction) {
                // Edit mode — jump straight to form step
                selectedType.value = props.editingAction.action;
                localForm.value = { ...props.editingAction };
                step.value = 'form';
            } else {
                // Add mode — start at type select
                step.value = 'type-select';
                selectedType.value = null;
                localForm.value = emptyForm();
            }
        }
    },
);

// ── type groups ───────────────────────────────────────────────────────

const typeGroups = [
    {
        label: 'Vertex 操作',
        types: [
            { value: 'create_vertex', label: '新增 Vertex', desc: '在指定 VertexType 下建立一個新 Vertex' },
            { value: 'delete_vertex', label: '刪除 Vertex', desc: '刪除一個既有 Vertex' },
        ],
    },
    {
        label: 'Edge 操作',
        types: [
            { value: 'create_edge', label: '新增 Edge', desc: '在指定 EdgeType 下新增一條 Edge' },
            { value: 'delete_edge', label: '刪除 Edge', desc: '刪除一條既有 Edge' },
        ],
    },
    {
        label: 'Vertex 屬性操作',
        types: [
            { value: 'create_vertex_property', label: '新增 Vertex 屬性', desc: '在既有 Vertex 上設定某個 property 值' },
            { value: 'update_vertex_property', label: '修改 Vertex 屬性', desc: '修改既有 Vertex 上的 property 值' },
            { value: 'delete_vertex_property', label: '刪除 Vertex 屬性', desc: '移除既有 Vertex 上的 property 值' },
        ],
    },
    {
        label: 'Edge 屬性操作',
        types: [
            { value: 'create_edge_property', label: '新增 Edge 屬性', desc: '在既有 Edge 上設定某個 property 值' },
            { value: 'update_edge_property', label: '修改 Edge 屬性', desc: '修改既有 Edge 上的 property 值' },
            { value: 'delete_edge_property', label: '刪除 Edge 屬性', desc: '移除既有 Edge 上的 property 值' },
        ],
    },
];

// ── form component mapping ────────────────────────────────────────────

const formComponent = computed(() => {
    if (!selectedType.value) {
        return null;
    }
    if (selectedType.value.includes('vertex_property')) {
        return VertexPropertyActionForm;
    }
    if (selectedType.value.includes('edge_property')) {
        return EdgePropertyActionForm;
    }
    if (selectedType.value.includes('vertex')) {
        return VertexActionForm;
    }
    if (selectedType.value.includes('edge')) {
        return EdgeActionForm;
    }
    return null;
});

const modalTitle = computed(() => {
    if (step.value === 'type-select') {
        return props.editingAction ? '編輯操作 — 選擇類型' : '新增操作 — 選擇類型';
    }
    const prefix = props.editingAction ? '編輯操作：' : '新增操作：';
    const label = typeGroups
        .flatMap((g) => g.types)
        .find((t) => t.value === selectedType.value)?.label ?? selectedType.value;
    return prefix + label;
});

// ── actions ───────────────────────────────────────────────────────────

function selectType(type) {
    selectedType.value = type;
    localForm.value = { ...emptyForm(), action: type };
    step.value = 'form';
}

function confirm() {
    emit('confirm', { ...localForm.value, action: selectedType.value });
}

function close() {
    emit('close');
}

function backToTypeSelect() {
    step.value = 'type-select';
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0,0,0,0.5)"
            @click.self="close"
        >
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ modalTitle }}</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Step 1: type selection -->
                        <template v-if="step === 'type-select'">
                            <div class="row g-3">
                                <div v-for="group in typeGroups" :key="group.label" class="col-12">
                                    <div class="fw-semibold small mb-2">{{ group.label }}</div>
                                    <div class="d-flex flex-column gap-2">
                                        <button
                                            v-for="t in group.types"
                                            :key="t.value"
                                            type="button"
                                            class="btn btn-outline-dark text-start"
                                            @click="selectType(t.value)"
                                        >
                                            <div class="fw-semibold">{{ t.label }}</div>
                                            <div class="small">{{ t.desc }}</div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Step 2: action form -->
                        <template v-else>
                            <component
                                :is="formComponent"
                                v-model="localForm"
                                :action-type="selectedType"
                                :vertex-types="vertexTypes"
                                :edge-types="edgeTypes"
                                :graph-locales="graphLocales"
                                :create-vertex-actions="createVertexActions"
                                :create-edge-actions="createEdgeActions"
                            />
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="close">取消</button>
                        <button
                            v-if="step === 'form'"
                            type="button"
                            class="btn btn-outline-secondary"
                            @click="backToTypeSelect"
                        >
                            變更類型
                        </button>
                        <button v-if="step === 'form'" type="button" class="btn btn-primary" @click="confirm">確認</button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
