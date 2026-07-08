<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionModal from './Partials/ActionModal.vue';

const props = defineProps({
    revision: Object,
    vertexTypes: Array,
    edgeTypes: Array,
    graphLocales: Object,
    routeShow: String,
    routeUpdate: String,
    routeValidate: String,
});

const form = useForm({
    title: props.revision.title,
    description: props.revision.description ?? '',
    actions: props.revision.actions.map((a, i) => ({
        order: i,
        action: a.action,
        target_age_id: a.target_age_id ?? null,
        target_ref_order: a.target_ref_order ?? null,
        vertex_type_label: a.vertex_type_label ?? null,
        edge_type_label: a.edge_type_label ?? null,
        start_vertex_age_id: a.start_vertex_age_id ?? null,
        start_vertex_ref_order: a.start_vertex_ref_order ?? null,
        end_vertex_age_id: a.end_vertex_age_id ?? null,
        end_vertex_ref_order: a.end_vertex_ref_order ?? null,
        age_property_name: a.age_property_name ?? null,
        value: a.value ?? null,
    })),
});

function save() {
    form.put(props.routeUpdate);
}

const isCheckingRules = ref(false);
const hasCheckedRules = ref(false);
const isRuleValid = ref(null);
const ruleSummary = ref('');
const ruleGeneralErrors = ref([]);
const ruleActionMessages = ref({});
const ruleFieldErrors = ref([]);

const RULE_CHECK_DEBOUNCE_MS = 500;
let ruleCheckTimer = null;
let ruleCheckController = null;

function collectFieldErrors(validationErrors) {
    return Object.values(validationErrors).flatMap((messages) =>
        Array.isArray(messages) ? messages : [messages],
    );
}

function scheduleRuleCheck() {
    if (ruleCheckTimer !== null) {
        clearTimeout(ruleCheckTimer);
    }

    ruleCheckTimer = setTimeout(() => {
        void runRuleCheck();
    }, RULE_CHECK_DEBOUNCE_MS);
}

function getCsrfToken() {
    return document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
}

async function runRuleCheck() {
    const csrfToken = getCsrfToken();
    if (!csrfToken) {
        return;
    }

    if (ruleCheckController !== null) {
        ruleCheckController.abort();
    }

    const controller = new AbortController();
    ruleCheckController = controller;
    isCheckingRules.value = true;

    try {
        const response = await fetch(props.routeValidate, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            signal: controller.signal,
            body: JSON.stringify({
                title: form.title,
                description: form.description,
                actions: form.actions.map((action, index) => ({
                    ...action,
                    order: index,
                })),
            }),
        });

        if (response.status === 422) {
            const data = await response.json();
            hasCheckedRules.value = true;
            isRuleValid.value = false;
            ruleSummary.value = '欄位格式未通過，請先修正';
            ruleFieldErrors.value = collectFieldErrors(data.errors ?? {});
            ruleGeneralErrors.value = [];
            ruleActionMessages.value = {};

            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        hasCheckedRules.value = true;
        isRuleValid.value = Boolean(data.is_valid);
        ruleSummary.value = data.summary ?? '';
        ruleGeneralErrors.value = data.general_errors ?? [];
        ruleActionMessages.value = data.action_messages ?? {};
        ruleFieldErrors.value = [];
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        hasCheckedRules.value = true;
        isRuleValid.value = null;
        ruleSummary.value = '目前無法完成規則檢查，請稍後再試';
        ruleGeneralErrors.value = [];
        ruleActionMessages.value = {};
        ruleFieldErrors.value = [];
    } finally {
        if (ruleCheckController === controller) {
            ruleCheckController = null;
            isCheckingRules.value = false;
        }
    }
}

function getActionRuleMessages(index) {
    return ruleActionMessages.value[index] ?? [];
}

watch(
    () => ({
        title: form.title,
        description: form.description,
        actions: form.actions,
    }),
    () => {
        scheduleRuleCheck();
    },
    { deep: true },
);

onMounted(() => {
    scheduleRuleCheck();
});

onBeforeUnmount(() => {
    if (ruleCheckTimer !== null) {
        clearTimeout(ruleCheckTimer);
    }

    if (ruleCheckController !== null) {
        ruleCheckController.abort();
    }
});

// ── action list helpers ──────────────────────────────────────────────

const actionLabels = {
    create_vertex: '新增 Vertex',
    delete_vertex: '刪除 Vertex',
    create_edge: '新增 Edge',
    delete_edge: '刪除 Edge',
    create_vertex_property: '新增 Vertex 屬性',
    update_vertex_property: '修改 Vertex 屬性',
    delete_vertex_property: '刪除 Vertex 屬性',
    create_edge_property: '新增 Edge 屬性',
    update_edge_property: '修改 Edge 屬性',
    delete_edge_property: '刪除 Edge 屬性',
};

function actionSummary(a) {
    const t = (v) => v ?? '—';
    const refOrId = (ref, id) =>
        ref !== null && ref !== undefined
            ? `#${ref + 1} 建立的項目`
            : id
              ? `ID:${id}`
              : '—';
    const vertexRefOrId = (ref, id) =>
        ref !== null && ref !== undefined
            ? `#${ref + 1} 建立的 Vertex`
            : id
              ? `ID:${id}`
              : '—';

    switch (a.action) {
        case 'create_vertex':
            return `新增 Vertex：${t(a.vertex_type_label)}`;
        case 'delete_vertex':
            return `刪除 Vertex：${refOrId(a.target_ref_order, a.target_age_id)}`;
        case 'create_edge':
            return `新增 Edge：${vertexRefOrId(a.start_vertex_ref_order, a.start_vertex_age_id)} - ${t(a.edge_type_label)} - ${vertexRefOrId(a.end_vertex_ref_order, a.end_vertex_age_id)}`;
        case 'delete_edge':
            return `刪除 Edge：${refOrId(a.target_ref_order, a.target_age_id)}`;
        case 'create_vertex_property':
            return `新增 Vertex 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
        case 'update_vertex_property':
            return `修改 Vertex 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
        case 'delete_vertex_property':
            return `刪除 Vertex 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)}`;
        case 'create_edge_property':
            return `新增 Edge 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
        case 'update_edge_property':
            return `修改 Edge 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
        case 'delete_edge_property':
            return `刪除 Edge 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)}`;
        default:
            return a.action;
    }
}

const dragSrcIndex = ref(null);
const dragOverIndex = ref(null);

function onDragStart(event, index) {
    dragSrcIndex.value = index;
    event.dataTransfer.effectAllowed = 'move';
}

function onDragOver(event, index) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dragOverIndex.value = index;
}

function onDragLeave() {
    dragOverIndex.value = null;
}

function onDrop(index) {
    if (dragSrcIndex.value === null || dragSrcIndex.value === index) {
        dragSrcIndex.value = null;
        dragOverIndex.value = null;
        return;
    }
    const arr = form.actions;
    const [moved] = arr.splice(dragSrcIndex.value, 1);
    arr.splice(index, 0, moved);
    renumber();
    dragSrcIndex.value = null;
    dragOverIndex.value = null;
}

function onDragEnd() {
    dragSrcIndex.value = null;
    dragOverIndex.value = null;
}

function deleteAction(index) {
    if (!confirm('確認刪除此操作？')) {
        return;
    }
    form.actions.splice(index, 1);
    renumber();
}

function renumber() {
    form.actions.forEach((a, i) => {
        a.order = i;
    });
}

// ── modal ────────────────────────────────────────────────────────────

const showModal = ref(false);
const modalEditIndex = ref(null); // null = add mode

function openAddModal() {
    modalEditIndex.value = null;
    showModal.value = true;
}

function openEditModal(index) {
    modalEditIndex.value = index;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function onModalConfirm(actionData) {
    if (modalEditIndex.value === null) {
        form.actions.push({ ...actionData, order: form.actions.length });
    } else {
        form.actions[modalEditIndex.value] = {
            ...actionData,
            order: modalEditIndex.value,
        };
    }
    showModal.value = false;
}

const editingAction = computed(() =>
    modalEditIndex.value !== null ? form.actions[modalEditIndex.value] : null,
);

// ── create_vertex actions (for ref dropdowns in forms) ───────────────
const createVertexActions = computed(() =>
    form.actions.filter((a) => a.action === 'create_vertex'),
);

const createEdgeActions = computed(() =>
    form.actions.filter((a) => a.action === 'create_edge'),
);
</script>

<template>
    <div class="container">
        <a :href="routeShow" class="btn btn-secondary mb-2">
            <i class="fa-solid fa-arrow-left"></i>
            返回修訂詳情
        </a>

        <h1 class="h3 mb-3">編輯修訂</h1>

        <div class="mb-3 d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" :disabled="form.processing" @click="save">
                <i class="fa-solid fa-floppy-disk"></i> 儲存變更
            </button>
        </div>

        <!-- Error summary -->
        <div v-if="Object.keys(form.errors).length > 0" class="alert alert-danger">
            <div class="fw-semibold mb-1">請修正以下錯誤後再提交：</div>
            <ul class="mb-0">
                <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
            </ul>
        </div>

        <!-- Basic info card -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3 row">
                    <label for="title" class="col-md-2 col-form-label">標題 <span class="text-danger">*</span></label>
                    <div class="col-md-10">
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.title }"
                            required
                        />
                        <div v-if="form.errors.title" class="invalid-feedback">{{ form.errors.title }}</div>
                    </div>
                </div>

                <div class="mb-3 row">
                    <label for="description" class="col-md-2 col-form-label">描述</label>
                    <div class="col-md-10">
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.description }"
                            rows="3"
                            placeholder="選填"
                        ></textarea>
                        <div v-if="form.errors.description" class="invalid-feedback">{{ form.errors.description }}</div>
                    </div>
                </div>
            </div>
        </div>

        <h2>操作清單</h2>

        <button type="button" class="btn btn-outline-primary mb-3" @click="openAddModal">
            <i class="fa-solid fa-plus"></i> 新增操作
        </button>

        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold">規則檢查</div>
                    <span v-if="isCheckingRules" class="badge text-bg-secondary">檢查中</span>
                    <span v-else-if="hasCheckedRules && isRuleValid === true" class="badge text-bg-success">符合規則</span>
                    <span v-else-if="hasCheckedRules && isRuleValid === false" class="badge text-bg-danger">不符合規則</span>
                    <span v-else-if="hasCheckedRules" class="badge text-bg-warning">檢查未完成</span>
                </div>

                <div v-if="isCheckingRules" class="small text-secondary mt-1">正在檢查最新編輯內容...</div>
                <div v-else-if="ruleSummary" class="small mt-1" :class="isRuleValid === false ? 'text-danger' : 'text-secondary'">
                    {{ ruleSummary }}
                </div>

                <ul v-if="ruleFieldErrors.length > 0" class="small text-danger mt-2 mb-0">
                    <li v-for="(message, idx) in ruleFieldErrors" :key="`field-${idx}`">{{ message }}</li>
                </ul>

                <ul v-if="ruleGeneralErrors.length > 0" class="small text-danger mt-2 mb-0">
                    <li v-for="(message, idx) in ruleGeneralErrors" :key="`general-${idx}`">{{ message }}</li>
                </ul>
            </div>
        </div>

        <!-- Actions list -->
        <div class="card mb-3">
            <div class="card-body">
                <div v-if="form.actions.length === 0" class="text-secondary text-center py-4">
                    尚無任何操作，點擊右上方「新增操作」開始
                </div>

                <div
                    v-for="(action, index) in form.actions"
                    :key="index"
                    class="card mb-2"
                    :class="{
                        'opacity-50': dragSrcIndex === index,
                        'border-primary': dragOverIndex === index && dragSrcIndex !== index,
                    }"
                    draggable="true"
                    @dragstart="onDragStart($event, index)"
                    @dragover="onDragOver($event, index)"
                    @dragleave="onDragLeave"
                    @drop="onDrop(index)"
                    @dragend="onDragEnd"
                >
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="text-secondary"
                                    style="cursor: grab; touch-action: none"
                                    title="拖曳排序"
                                >
                                    <i class="fa-solid fa-grip-vertical"></i>
                                </span>
                                <span class="fw-semibold small text-secondary">
                                    #{{ index + 1 }} &middot; {{ actionLabels[action.action] ?? action.action }}
                                </span>
                            </div>
                            <div class="d-flex gap-1">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary py-0 px-1"
                                    title="編輯"
                                    @click="openEditModal(index)"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger py-0 px-1"
                                    title="刪除"
                                    @click="deleteAction(index)"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="small">{{ actionSummary(action) }}</div>
                        <ul v-if="getActionRuleMessages(index).length > 0" class="small text-danger mt-2 mb-0">
                            <li v-for="(message, msgIdx) in getActionRuleMessages(index)" :key="`a-${index}-m-${msgIdx}`">
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Modal -->
    <ActionModal
        :show="showModal"
        :editing-action="editingAction"
        :vertex-types="vertexTypes"
        :edge-types="edgeTypes"
        :graph-locales="graphLocales"
        :create-vertex-actions="createVertexActions"
        :create-edge-actions="createEdgeActions"
        @confirm="onModalConfirm"
        @close="closeModal"
    />
</template>
