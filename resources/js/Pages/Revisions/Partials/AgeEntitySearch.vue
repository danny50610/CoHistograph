<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: null,
    },
    searchUrl: {
        type: String,
        required: true,
    },
    entityKind: {
        type: String,
        required: true, // 'vertex' | 'edge'
    },
    /**
     * Optional list of types the user can pick before searching.
     * Shape: [{ value: age_label_name, label: display text }]
     */
    typeOptions: {
        type: Array,
        default: null,
    },
    /**
     * Pre-locked type filter (e.g. create_edge start/end from selected EdgeType).
     * When set without typeOptions, search is limited to these labels.
     */
    typeLabels: {
        type: Array,
        default: null,
    },
    /**
     * Human-readable locked type shown when typeLabels is provided without typeOptions.
     * Example: "人物 (Person)"
     */
    lockedTypeDisplay: {
        type: String,
        default: null,
    },
    /**
     * When true, show a read-only type indicator for pre-locked typeFilters.
     */
    showLockedType: {
        type: Boolean,
        default: false,
    },
    lockedTypePlaceholder: {
        type: String,
        default: '— 請先選擇類型 —',
    },
    lockedTypeHint: {
        type: String,
        default: '搜尋僅限此類型',
    },
    lockedTypePendingHint: {
        type: String,
        default: '請先選擇類型以啟用搜尋',
    },
    /**
     * When true, searching requires an effective type filter.
     */
    requireType: {
        type: Boolean,
        default: false,
    },
    placeholder: {
        type: String,
        default: '搜尋名稱或 ID…',
    },
    typePlaceholder: {
        type: String,
        default: '— 請先選擇類型 —',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'select', 'clear', 'type-change']);

const query = ref('');
const results = ref([]);
const selected = ref(null);
const selectedType = ref('');
const isOpen = ref(false);
const isLoading = ref(false);
const highlightedIndex = ref(-1);
const rootEl = ref(null);
const inputEl = ref(null);

let debounceTimer = null;
let abortController = null;
let requestSeq = 0;

const hasTypeOptions = computed(() => Array.isArray(props.typeOptions) && props.typeOptions.length > 0);

const hasLockedTypeDisplay = computed(() =>
    typeof props.lockedTypeDisplay === 'string' && props.lockedTypeDisplay !== '',
);

const effectiveTypeLabels = computed(() => {
    if (hasTypeOptions.value) {
        return selectedType.value ? [selectedType.value] : null;
    }

    if (Array.isArray(props.typeLabels) && props.typeLabels.length > 0) {
        return props.typeLabels.filter((label) => typeof label === 'string' && label !== '');
    }

    return null;
});

const hasTypeFilter = computed(() => Array.isArray(effectiveTypeLabels.value) && effectiveTypeLabels.value.length > 0);

const canSearch = computed(() => {
    if (props.disabled) {
        return false;
    }

    if (props.requireType || hasTypeOptions.value) {
        return hasTypeFilter.value;
    }

    return true;
});

const hasSelection = computed(() => selected.value !== null);

const emptyHint = computed(() => {
    if (! canSearch.value) {
        return hasTypeOptions.value ? '請先選擇類型' : '請先選擇類型後再搜尋';
    }

    if (props.entityKind === 'edge') {
        return '輸入起點／終點名稱或 ID';
    }

    return '輸入顯示名稱或 ID';
});

watch(
    () => props.modelValue,
    async (value) => {
        if (value === null || value === undefined || value === '') {
            selected.value = null;
            return;
        }

        const id = Number(value);
        if (! Number.isFinite(id)) {
            return;
        }

        if (selected.value?.id === id) {
            return;
        }

        await resolveById(id);
    },
    { immediate: true },
);

watch(
    () => [props.typeLabels, selectedType.value],
    () => {
        results.value = [];
        highlightedIndex.value = -1;

        if (! hasSelection.value && canSearch.value && query.value !== '') {
            scheduleSearch();
        }
    },
    { deep: true },
);

onMounted(() => {
    document.addEventListener('mousedown', onDocumentMouseDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentMouseDown);
    clearDebounce();
    abortInFlight();
});

function onDocumentMouseDown(event) {
    if (! rootEl.value?.contains(event.target)) {
        isOpen.value = false;
    }
}

function clearDebounce() {
    if (debounceTimer !== null) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }
}

function abortInFlight() {
    if (abortController !== null) {
        abortController.abort();
        abortController = null;
    }
}

function scheduleSearch() {
    if (! canSearch.value) {
        return;
    }

    clearDebounce();
    debounceTimer = setTimeout(() => {
        void runSearch();
    }, 250);
}

function buildSearchParams(extra = {}) {
    const params = new URLSearchParams(extra);

    if (Array.isArray(effectiveTypeLabels.value)) {
        effectiveTypeLabels.value.forEach((label) => params.append('types[]', label));
    }

    return params;
}

async function resolveById(id) {
    abortInFlight();
    const controller = new AbortController();
    abortController = controller;
    const seq = ++requestSeq;
    isLoading.value = true;

    try {
        // Resolve by ID without type filter so edit mode can restore selection.
        const params = new URLSearchParams({ id: String(id) });
        const response = await fetch(`${props.searchUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (! response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        const item = Array.isArray(payload.data) ? payload.data[0] : null;

        if (seq !== requestSeq) {
            return;
        }

        if (item) {
            selected.value = item;
            query.value = '';
            results.value = [];
            if (hasTypeOptions.value && item.type_label) {
                selectedType.value = item.type_label;
            }
            emit('select', item);
        } else {
            selected.value = {
                id,
                display_name: `(ID: ${id})`,
                type_label: null,
                type_name: null,
            };
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        if (seq === requestSeq) {
            selected.value = {
                id,
                display_name: `(ID: ${id})`,
                type_label: null,
                type_name: null,
            };
        }
    } finally {
        if (abortController === controller) {
            abortController = null;
            isLoading.value = false;
        }
    }
}

async function runSearch() {
    if (! canSearch.value) {
        results.value = [];
        return;
    }

    abortInFlight();
    const controller = new AbortController();
    abortController = controller;
    const seq = ++requestSeq;
    isLoading.value = true;
    isOpen.value = true;

    try {
        const params = buildSearchParams({
            q: query.value.trim(),
            limit: '20',
        });
        const response = await fetch(`${props.searchUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (! response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();

        if (seq !== requestSeq) {
            return;
        }

        results.value = Array.isArray(payload.data) ? payload.data : [];
        highlightedIndex.value = results.value.length > 0 ? 0 : -1;
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        if (seq === requestSeq) {
            results.value = [];
            highlightedIndex.value = -1;
        }
    } finally {
        if (abortController === controller) {
            abortController = null;
            isLoading.value = false;
        }
    }
}

function onTypeChange() {
    query.value = '';
    results.value = [];
    highlightedIndex.value = -1;
    isOpen.value = false;

    if (hasSelection.value) {
        selected.value = null;
        emit('update:modelValue', null);
        emit('clear');
    }

    emit('type-change', selectedType.value || null);

    if (canSearch.value) {
        nextTick(() => inputEl.value?.focus());
    }
}

function onInput() {
    if (hasSelection.value) {
        selected.value = null;
        emit('update:modelValue', null);
        emit('clear');
    }

    scheduleSearch();
}

function onFocus() {
    if (! canSearch.value || hasSelection.value) {
        return;
    }

    isOpen.value = true;

    if (results.value.length === 0) {
        scheduleSearch();
    }
}

function choose(item) {
    selected.value = item;
    query.value = '';
    results.value = [];
    isOpen.value = false;
    highlightedIndex.value = -1;
    if (hasTypeOptions.value && item.type_label) {
        selectedType.value = item.type_label;
    }
    emit('update:modelValue', item.id);
    emit('select', item);
}

function clearSelection() {
    selected.value = null;
    query.value = '';
    results.value = [];
    isOpen.value = false;
    emit('update:modelValue', null);
    emit('clear');
    nextTick(() => {
        if (canSearch.value) {
            inputEl.value?.focus();
        }
    });
}

function onKeydown(event) {
    if (! canSearch.value) {
        return;
    }

    if (! isOpen.value && ['ArrowDown', 'Enter'].includes(event.key)) {
        isOpen.value = true;
        scheduleSearch();
        return;
    }

    if (event.key === 'Escape') {
        isOpen.value = false;
        return;
    }

    if (! isOpen.value || results.value.length === 0) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedIndex.value = (highlightedIndex.value + 1) % results.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value =
            highlightedIndex.value <= 0
                ? results.value.length - 1
                : highlightedIndex.value - 1;
    } else if (event.key === 'Enter' && highlightedIndex.value >= 0) {
        event.preventDefault();
        choose(results.value[highlightedIndex.value]);
    }
}

function resultPrimary(item) {
    return item.display_name;
}

function resultSecondary(item) {
    const typeName = item.type_name || item.type_label || '';
    return typeName ? `${typeName} · ID:${item.id}` : `ID:${item.id}`;
}
</script>

<template>
    <div ref="rootEl" class="position-relative">
        <div v-if="hasTypeOptions" class="mb-2">
            <select
                v-model="selectedType"
                class="form-select"
                :disabled="disabled || hasSelection"
                @change="onTypeChange"
            >
                <option value="">{{ typePlaceholder }}</option>
                <option
                    v-for="option in typeOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <div v-else-if="showLockedType" class="mb-2">
            <div class="form-text mb-1">Vertex 類型限制</div>
            <input
                type="text"
                class="form-control"
                :value="hasLockedTypeDisplay ? lockedTypeDisplay : lockedTypePlaceholder"
                readonly
                disabled
            >
            <div class="form-text">
                {{ hasLockedTypeDisplay ? lockedTypeHint : lockedTypePendingHint }}
            </div>
        </div>

        <div v-if="hasSelection" class="input-group">
            <span class="form-control text-start bg-white">
                <span class="fw-semibold">{{ selected.display_name }}</span>
                <span class="text-secondary small ms-2">{{ resultSecondary(selected) }}</span>
            </span>
            <button
                type="button"
                class="btn btn-outline-secondary"
                :disabled="disabled"
                @click="clearSelection"
            >
                清除
            </button>
        </div>

        <template v-else>
            <input
                ref="inputEl"
                v-model="query"
                type="search"
                class="form-control"
                :placeholder="canSearch ? placeholder : emptyHint"
                :required="required && canSearch"
                :disabled="disabled || !canSearch"
                autocomplete="off"
                @input="onInput"
                @focus="onFocus"
                @keydown="onKeydown"
            />

            <div
                v-if="isOpen && canSearch"
                class="dropdown-menu show w-100 mt-1 shadow-sm"
                style="max-height: 240px; overflow-y: auto"
            >
                <div v-if="isLoading" class="dropdown-item-text text-secondary small">搜尋中…</div>
                <template v-else-if="results.length > 0">
                    <button
                        v-for="(item, index) in results"
                        :key="item.id"
                        type="button"
                        class="dropdown-item"
                        :class="{ active: index === highlightedIndex }"
                        @mousedown.prevent="choose(item)"
                    >
                        <div class="fw-semibold">{{ resultPrimary(item) }}</div>
                        <div class="small opacity-75">{{ resultSecondary(item) }}</div>
                    </button>
                </template>
                <div v-else class="dropdown-item-text text-secondary small">
                    {{ query.trim() === '' ? emptyHint : '找不到符合的結果' }}
                </div>
            </div>
        </template>
    </div>
</template>
