<script setup>
/**
 * Type-aware property value input for revision create/update property actions.
 * Emits storage-format strings expected by PropertyValueCaster / AGE.
 */
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: null,
    },
    propertyType: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const COMMON_OFFSETS = [
    '+00:00',
    '+01:00',
    '+02:00',
    '+03:00',
    '+04:00',
    '+05:00',
    '+05:30',
    '+06:00',
    '+07:00',
    '+08:00',
    '+09:00',
    '+09:30',
    '+10:00',
    '+11:00',
    '+12:00',
    '-05:00',
    '-06:00',
    '-07:00',
    '-08:00',
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
        default:
            return '請先選擇屬性';
    }
});
</script>

<template>
    <div>
        <!-- BOOLEAN -->
        <select
            v-if="propertyType === 'BOOLEAN'"
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
