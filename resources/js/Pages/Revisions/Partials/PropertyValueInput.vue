<script setup>
/**
 * Type-aware property value input for revision create/update property actions.
 * Emits storage-format strings (or string[] for ENUM) expected by PropertyValueCaster / AGE.
 */
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Array],
        default: null,
    },
    propertyType: {
        type: String,
        default: null,
    },
    enumOptions: {
        type: Array,
        default: () => [],
    },
    minSelections: {
        type: Number,
        default: 1,
    },
    maxSelections: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

/**
 * Standard UTC offsets used worldwide (whole hours plus known :30 / :45 zones).
 * Existing values not in this list are still appended dynamically when editing.
 */
const COMMON_OFFSETS = [
    '-12:00',
    '-11:00',
    '-10:00',
    '-09:30',
    '-09:00',
    '-08:00',
    '-07:00',
    '-06:00',
    '-05:00',
    '-04:00',
    '-03:30',
    '-03:00',
    '-02:30',
    '-02:00',
    '-01:00',
    '+00:00',
    '+01:00',
    '+02:00',
    '+03:00',
    '+03:30',
    '+04:00',
    '+04:30',
    '+05:00',
    '+05:30',
    '+05:45',
    '+06:00',
    '+06:30',
    '+07:00',
    '+08:00',
    '+08:45',
    '+09:00',
    '+09:30',
    '+10:00',
    '+10:30',
    '+11:00',
    '+12:00',
    '+12:45',
    '+13:00',
    '+13:45',
    '+14:00',
];

const MONTHS = Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'));

function emitValue(value) {
    if (value === '' || value === null || value === undefined) {
        emit('update:modelValue', null);

        return;
    }

    emit('update:modelValue', String(value));
}

const stringValue = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)));

const selectedEnumValues = computed(() => (Array.isArray(props.modelValue) ? props.modelValue.map(String) : []));

/**
 * B′: inactive options that were selected when the control opened stay eligible to toggle.
 * Orphan values (on the current selection but not in enum_options) appear as extra rows.
 */
const eligibleInactive = ref([]);

watch(
    () => [props.propertyType, props.enumOptions, props.modelValue],
    ([type], [prevType]) => {
        if (type !== 'ENUM') {
            eligibleInactive.value = [];

            return;
        }

        // Reset eligibility when switching into ENUM or changing property options identity.
        if (prevType !== 'ENUM') {
            const inactiveValues = new Set(
                (props.enumOptions ?? []).filter((o) => !o.active).map((o) => o.value),
            );
            const defined = new Set((props.enumOptions ?? []).map((o) => o.value));
            const current = Array.isArray(props.modelValue) ? props.modelValue.map(String) : [];
            eligibleInactive.value = current.filter(
                (v) => inactiveValues.has(v) || !defined.has(v),
            );
        }
    },
    { immediate: true },
);

const enumRows = computed(() => {
    const options = props.enumOptions ?? [];
    const defined = new Set(options.map((o) => o.value));
    const rows = options.map((option) => {
        const eligible =
            option.active || eligibleInactive.value.includes(option.value);

        return {
            value: option.value,
            label: option.label,
            active: Boolean(option.active),
            eligible,
            orphan: false,
        };
    });

    for (const orphan of eligibleInactive.value) {
        if (!defined.has(orphan)) {
            rows.push({
                value: orphan,
                label: orphan,
                active: false,
                eligible: true,
                orphan: true,
            });
        }
    }

    return rows;
});

function toggleEnumValue(value, checked) {
    const next = new Set(selectedEnumValues.value);

    if (checked) {
        if (props.maxSelections !== null && next.size >= props.maxSelections) {
            return;
        }

        next.add(value);
    } else {
        next.delete(value);
    }

    const ordered = enumRows.value
        .map((row) => row.value)
        .filter((v) => next.has(v));

    emit('update:modelValue', ordered.length ? ordered : null);
}

const atMaxSelections = computed(
    () =>
        props.maxSelections !== null &&
        selectedEnumValues.value.length >= props.maxSelections,
);

function isEnumCheckboxDisabled(row) {
    if (!row.eligible) {
        return true;
    }

    if (atMaxSelections.value && !selectedEnumValues.value.includes(row.value)) {
        return true;
    }

    return false;
}

const monthDayParts = computed(() => {
    const match = stringValue.value.match(/^(\d{2})-(\d{2})$/);

    return {
        month: match?.[1] ?? '',
        day: match?.[2] ?? '',
    };
});

const daysForSelectedMonth = computed(() => {
    const month = Number(monthDayParts.value.month);

    if (!month) {
        return Array.from({ length: 31 }, (_, i) => String(i + 1).padStart(2, '0'));
    }

    const count = new Date(2000, month, 0).getDate();

    return Array.from({ length: count }, (_, i) => String(i + 1).padStart(2, '0'));
});

function updateMonthDay(month, day) {
    if (!month || !day) {
        emitValue(null);

        return;
    }

    const maxDay = new Date(2000, Number(month), 0).getDate();
    const clampedDay = String(Math.min(Number(day), maxDay)).padStart(2, '0');

    emitValue(`${month}-${clampedDay}`);
}

const timestamptzParts = computed(() => {
    const match = stringValue.value.match(
        /^(\d{4}-\d{2}-\d{2})[Tt ](\d{2}:\d{2})(?::\d{2})?(?:\.\d+)?([Zz]|[+-]\d{2}:?\d{2})$/,
    );

    if (!match) {
        return { local: '', offset: '+08:00' };
    }

    let offset = match[3];

    if (offset === 'Z' || offset === 'z') {
        offset = '+00:00';
    } else if (/^[+-]\d{4}$/.test(offset)) {
        offset = `${offset.slice(0, 3)}:${offset.slice(3)}`;
    }

    return {
        local: `${match[1]}T${match[2]}`,
        offset,
    };
});

const offsetOptions = computed(() => {
    const offset = timestamptzParts.value.offset;

    if (offset && !COMMON_OFFSETS.includes(offset)) {
        return [offset, ...COMMON_OFFSETS];
    }

    return COMMON_OFFSETS;
});

function updateTimestamptz(local, offset) {
    if (!local) {
        emitValue(null);

        return;
    }

    // datetime-local is YYYY-MM-DDTHH:mm — append :00 seconds for storage format
    const withSeconds = /T\d{2}:\d{2}:\d{2}/.test(local) ? local : `${local}:00`;

    emitValue(`${withSeconds}${offset}`);
}

const hint = computed(() => {
    switch (props.propertyType) {
        case 'INTEGER':
            return '整數，例如 42';
        case 'FLOAT':
            return '浮點數，例如 3.14';
        case 'BOOLEAN':
            return '選擇 true 或 false';
        case 'DATE':
            return '完整日期（年-月-日）';
        case 'MONTH_DAY':
            return '僅月份與日期，儲存為 MM-DD';
        case 'TIMESTAMPTZ':
            return '日期時間需指定時區偏移，儲存為 ISO-8601';
        case 'STRING':
            return '文字';
        case 'ENUM': {
            const min = props.minSelections ?? 1;
            const maxPart =
                props.maxSelections === null || props.maxSelections === undefined
                    ? '不限'
                    : String(props.maxSelections);

            return `可複選（最少 ${min}、最多 ${maxPart}）；清空請改用刪除屬性操作。已停用選項僅能保留／移除既有值。`;
        }
        default:
            return '請先選擇屬性';
    }
});

const noActiveEnumOptions = computed(
    () =>
        props.propertyType === 'ENUM' &&
        enumRows.value.every((row) => !row.eligible),
);
</script>

<template>
    <div>
        <!-- ENUM -->
        <div v-if="propertyType === 'ENUM'" class="border rounded p-2">
            <div v-if="noActiveEnumOptions" class="text-warning small mb-2">
                此 ENUM 屬性目前沒有可選的啟用選項。
            </div>
            <div
                v-for="row in enumRows"
                :key="row.value"
                class="form-check"
            >
                <input
                    :id="`enum-opt-${row.value}`"
                    class="form-check-input"
                    type="checkbox"
                    :value="row.value"
                    :checked="selectedEnumValues.includes(row.value)"
                    :disabled="isEnumCheckboxDisabled(row)"
                    @change="toggleEnumValue(row.value, $event.target.checked)"
                >
                <label class="form-check-label" :for="`enum-opt-${row.value}`">
                    {{ row.label }}
                    <span v-if="!row.active" class="text-body-secondary small">（已停用）</span>
                    <span v-if="row.orphan" class="text-body-secondary small">（未知選項）</span>
                </label>
            </div>
            <div v-if="enumRows.length === 0" class="text-body-secondary small">尚無選項</div>
        </div>

        <!-- BOOLEAN -->
        <select
            v-else-if="propertyType === 'BOOLEAN'"
            class="form-select"
            :value="stringValue"
            required
            @change="emitValue($event.target.value)"
        >
            <option value="">— 請選擇 —</option>
            <option value="true">true</option>
            <option value="false">false</option>
        </select>

        <!-- INTEGER -->
        <input
            v-else-if="propertyType === 'INTEGER'"
            type="number"
            class="form-control"
            step="1"
            :value="stringValue"
            required
            @input="emitValue($event.target.value)"
        />

        <!-- FLOAT -->
        <input
            v-else-if="propertyType === 'FLOAT'"
            type="number"
            class="form-control"
            step="any"
            :value="stringValue"
            required
            @input="emitValue($event.target.value)"
        />

        <!-- DATE -->
        <input
            v-else-if="propertyType === 'DATE'"
            type="date"
            class="form-control"
            :value="stringValue"
            required
            @input="emitValue($event.target.value)"
        />

        <!-- MONTH_DAY -->
        <div v-else-if="propertyType === 'MONTH_DAY'" class="row g-2">
            <div class="col-6">
                <select
                    class="form-select"
                    :value="monthDayParts.month"
                    required
                    @change="updateMonthDay($event.target.value, monthDayParts.day || '01')"
                >
                    <option value="">月</option>
                    <option v-for="m in MONTHS" :key="m" :value="m">{{ m }}</option>
                </select>
            </div>
            <div class="col-6">
                <select
                    class="form-select"
                    :value="monthDayParts.day"
                    required
                    @change="updateMonthDay(monthDayParts.month || '01', $event.target.value)"
                >
                    <option value="">日</option>
                    <option v-for="d in daysForSelectedMonth" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>
        </div>

        <!-- TIMESTAMPTZ -->
        <div v-else-if="propertyType === 'TIMESTAMPTZ'" class="row g-2">
            <div class="col-md-8">
                <input
                    type="datetime-local"
                    class="form-control"
                    :value="timestamptzParts.local"
                    required
                    @input="updateTimestamptz($event.target.value, timestamptzParts.offset)"
                />
            </div>
            <div class="col-md-4">
                <select
                    class="form-select"
                    :value="timestamptzParts.offset"
                    required
                    @change="updateTimestamptz(timestamptzParts.local, $event.target.value)"
                >
                    <option v-for="offset in offsetOptions" :key="offset" :value="offset">
                        UTC{{ offset }}
                    </option>
                </select>
            </div>
        </div>

        <!-- STRING / unknown / no type yet -->
        <input
            v-else
            type="text"
            class="form-control"
            :value="stringValue"
            :placeholder="propertyType ? '屬性值' : '請先選擇屬性'"
            :required="Boolean(propertyType)"
            :disabled="!propertyType"
            @input="emitValue($event.target.value)"
        />

        <div class="form-text">{{ hint }}</div>
    </div>
</template>
