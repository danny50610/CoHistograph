<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import ActionModal from './Partials/ActionModal.vue';

const props = defineProps({
    revision: Object,
    vertexTypes: Array,
    edgeTypes: Array,
    routeShow: String,
    routeUpdate: String,
    routeSubmit: String,
    routeDestroy: String,
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

function submitForReview() {
    if (!confirm('確認提交此修訂進行審核？提交後將無法再編輯。')) {
        return;
    }
    router.post(props.routeSubmit);
}

function destroyRevision() {
    if (!confirm('確認刪除此修訂草稿？此操作無法復原。')) {
        return;
    }
    router.delete(props.routeDestroy);
}

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
            <button type="button" class="btn btn-success" :disabled="form.processing" @click="submitForReview">
                <i class="fa-solid fa-paper-plane"></i> 提交審核
            </button>
            <button type="button" class="btn btn-danger" :disabled="form.processing" @click="destroyRevision">
                <i class="fa-solid fa-trash"></i> 刪除草稿
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
        :create-vertex-actions="createVertexActions"
        :create-edge-actions="createEdgeActions"
        @confirm="onModalConfirm"
        @close="closeModal"
    />
</template>
