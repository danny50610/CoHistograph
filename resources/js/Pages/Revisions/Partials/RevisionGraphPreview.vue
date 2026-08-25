<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import * as d3 from 'd3';

const props = defineProps({
    vertices: {
        type: Array,
        default: () => [],
    },
    edges: {
        type: Array,
        default: () => [],
    },
});

const graphContainer = ref(null);
const selectedItem = ref(null);
let simulation = null;
let svgNode = null;

const statusMeta = {
    created: { label: '新增', color: '#198754' },
    deleted: { label: '刪除', color: '#dc3545' },
    updated: { label: '修改', color: '#fd7e14' },
    unchanged: { label: '既有', color: '#6c757d' },
};

function statusColor(status) {
    return statusMeta[status]?.color ?? statusMeta.unchanged.color;
}

function statusLabel(status) {
    return statusMeta[status]?.label ?? status;
}

function closeModal() {
    selectedItem.value = null;
}

function teardown() {
    if (simulation) {
        simulation.stop();
        simulation = null;
    }

    if (svgNode?.parentNode) {
        svgNode.parentNode.removeChild(svgNode);
    }

    svgNode = null;
}

function renderGraph() {
    teardown();

    if (!graphContainer.value) {
        return;
    }

    const nodes = props.vertices.map((vertex) => ({ ...vertex }));
    const nodeIds = new Set(nodes.map((node) => node.id));
    const links = props.edges
        .filter((edge) => nodeIds.has(edge.start_id) && nodeIds.has(edge.end_id))
        .map((edge) => ({
            ...edge,
            source: edge.start_id,
            target: edge.end_id,
        }));

    const nodeRadius = 36;
    const defaultWidth = 800;
    const container = graphContainer.value;
    const width = container.clientWidth || defaultWidth;
    const height = 520;

    const svg = d3.create('svg')
        .attr('viewBox', [-width / 2, -height / 2, width, height])
        .attr('width', width)
        .attr('height', height)
        .attr('style', 'max-width: 100%; height: auto;');

    svg.append('defs')
        .append('marker')
        .attr('id', 'revision-arrowhead')
        .attr('viewBox', '0 0 10 7')
        .attr('refX', 10)
        .attr('refY', 3.5)
        .attr('markerWidth', 8)
        .attr('markerHeight', 6)
        .attr('orient', 'auto')
        .append('polygon')
        .attr('points', '0 0, 10 3.5, 0 7')
        .attr('fill', '#888');

    const g = svg.append('g');

    svg.call(
        d3.zoom()
            .scaleExtent([0.1, 10])
            .on('zoom', (event) => g.attr('transform', event.transform)),
    );

    simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(links).id((d) => d.id).distance(200))
        .force('charge', d3.forceManyBody().strength(-700))
        .force('x', d3.forceX())
        .force('y', d3.forceY())
        .force('collision', d3.forceCollide(nodeRadius + 18));

    const link = g.append('g')
        .selectAll('path')
        .data(links)
        .join('path')
        .attr('fill', 'none')
        .attr('stroke', (d) => statusColor(d.status))
        .attr('stroke-width', 2)
        .attr('stroke-dasharray', (d) => (d.status === 'deleted' ? '6 4' : null))
        .attr('marker-end', 'url(#revision-arrowhead)')
        .attr('opacity', (d) => (d.status === 'deleted' ? 0.65 : 1));

    const linkLabels = g.append('g')
        .selectAll('text')
        .data(links)
        .join('text')
        .attr('font-size', 11)
        .attr('fill', '#555')
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'middle')
        .attr('cursor', 'pointer')
        .text((d) => d.label)
        .on('click', (_event, d) => {
            selectedItem.value = { kind: 'edge', data: d };
        });

    let dragMoved = false;

    const dragBehavior = d3.drag()
        .on('start', (event, d) => {
            dragMoved = false;
            if (!event.active) {
                simulation.alphaTarget(0.3).restart();
            }
            d.fx = d.x;
            d.fy = d.y;
        })
        .on('drag', (event, d) => {
            dragMoved = true;
            d.fx = event.x;
            d.fy = event.y;
        })
        .on('end', (event, d) => {
            if (!event.active) {
                simulation.alphaTarget(0);
            }
            d.fx = null;
            d.fy = null;
        });

    const nodeGroup = g.append('g')
        .selectAll('g')
        .data(nodes)
        .join('g')
        .attr('cursor', 'pointer')
        .call(dragBehavior)
        .on('click', (_event, d) => {
            if (!dragMoved) {
                selectedItem.value = { kind: 'vertex', data: d };
            }
        });

    nodeGroup.append('circle')
        .attr('r', nodeRadius)
        .attr('fill', (d) => statusColor(d.status))
        .attr('fill-opacity', (d) => (d.status === 'deleted' ? 0.35 : 0.9))
        .attr('stroke', 'white')
        .attr('stroke-width', 2)
        .attr('stroke-dasharray', (d) => (d.status === 'deleted' ? '4 3' : null));

    nodeGroup.append('text')
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'middle')
        .attr('font-size', 11)
        .attr('font-weight', 'bold')
        .attr('fill', 'white')
        .attr('pointer-events', 'none')
        .each(function wrapLabel(d) {
            const text = d3.select(this);
            const maxChars = 6;

            if (String(d.label).length <= maxChars) {
                text.text(d.label);
                return;
            }

            text.text(`${String(d.label).slice(0, maxChars)}…`);
        });

    const linkPath = (d) => {
        if (d.source.id === d.target.id) {
            const x = d.source.x;
            const y = d.source.y;
            const r = nodeRadius * 1.5;

            return `M${x - nodeRadius},${y} A${r},${r} 0 1,0 ${x},${y - nodeRadius}`;
        }

        const dx = d.target.x - d.source.x;
        const dy = d.target.y - d.source.y;
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (dist === 0) {
            return `M${d.source.x},${d.source.y}L${d.source.x},${d.source.y}`;
        }

        const nx = dx / dist;
        const ny = dy / dist;

        return `M${d.source.x + nx * nodeRadius},${d.source.y + ny * nodeRadius}`
            + `L${d.target.x - nx * nodeRadius},${d.target.y - ny * nodeRadius}`;
    };

    simulation.on('tick', () => {
        link.attr('d', linkPath);

        linkLabels
            .attr('x', (d) => {
                if (d.source.id === d.target.id) {
                    return d.source.x;
                }

                return (d.source.x + d.target.x) / 2;
            })
            .attr('y', (d) => {
                if (d.source.id === d.target.id) {
                    return d.source.y - nodeRadius * 2.8;
                }

                return (d.source.y + d.target.y) / 2;
            });

        nodeGroup.attr('transform', (d) => `translate(${d.x},${d.y})`);
    });

    svgNode = svg.node();
    container.appendChild(svgNode);
}

onMounted(() => {
    renderGraph();
});

watch(
    () => [props.vertices, props.edges],
    () => {
        renderGraph();
    },
    { deep: true },
);

onUnmounted(() => {
    teardown();
});
</script>

<template>
    <div>
        <p class="text-body-secondary small mb-2">
            <i class="fa-solid fa-circle-info"></i>
            此圖僅顯示本修訂涉及的節點與關係，可拖曳、縮放，點擊節點或邊名稱查看屬性變更。
        </p>

        <div class="d-flex flex-wrap gap-2 mb-2 small">
            <span
                v-for="(meta, status) in statusMeta"
                :key="status"
                class="d-inline-flex align-items-center gap-1"
            >
                <span
                    class="rounded-circle d-inline-block"
                    :style="{ width: '12px', height: '12px', background: meta.color }"
                ></span>
                {{ meta.label }}
            </span>
        </div>

        <div
            v-if="vertices.length === 0 && edges.length === 0"
            class="text-secondary text-center py-5 border rounded bg-light"
        >
            尚無可視覺化的 Vertex 或 Edge
        </div>
        <div
            v-else
            ref="graphContainer"
            class="border rounded bg-light"
        ></div>
    </div>

    <Teleport to="body">
        <template v-if="selectedItem">
            <div class="modal-backdrop fade show"></div>
            <div
                class="modal fade show d-block"
                tabindex="-1"
                @click.self="closeModal"
            >
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                {{ selectedItem.kind === 'vertex' ? 'Vertex' : 'Edge' }}
                                · {{ selectedItem.data.label }}
                            </h5>
                            <button
                                type="button"
                                class="btn-close"
                                @click="closeModal"
                            ></button>
                        </div>
                        <div class="modal-body">
                            <dl class="row mb-2">
                                <dt class="col-md-4">狀態</dt>
                                <dd class="col-md-8">
                                    <span
                                        class="badge"
                                        :style="{ background: statusColor(selectedItem.data.status) }"
                                    >
                                        {{ statusLabel(selectedItem.data.status) }}
                                    </span>
                                </dd>
                                <template v-if="selectedItem.kind === 'vertex'">
                                    <dt class="col-md-4">類型</dt>
                                    <dd class="col-md-8">
                                        {{ selectedItem.data.type_name }}
                                        <span class="text-body-secondary">({{ selectedItem.data.type_label }})</span>
                                    </dd>
                                    <dt v-if="selectedItem.data.age_id" class="col-md-4">AGE ID</dt>
                                    <dd v-if="selectedItem.data.age_id" class="col-md-8">{{ selectedItem.data.age_id }}</dd>
                                    <dt v-if="selectedItem.data.ref_order !== null" class="col-md-4">操作</dt>
                                    <dd v-if="selectedItem.data.ref_order !== null" class="col-md-8">
                                        #{{ selectedItem.data.ref_order + 1 }}
                                    </dd>
                                </template>
                                <template v-else>
                                    <dt class="col-md-4">類型</dt>
                                    <dd class="col-md-8">
                                        {{ selectedItem.data.label }}
                                        <span class="text-body-secondary">({{ selectedItem.data.type_label }})</span>
                                    </dd>
                                </template>
                            </dl>

                            <strong>屬性變更</strong>
                            <p
                                v-if="!selectedItem.data.properties?.length"
                                class="text-body-secondary mb-0 mt-1"
                            >
                                此修訂未變更屬性
                            </p>
                            <dl v-else class="row mb-0 mt-1">
                                <template
                                    v-for="property in selectedItem.data.properties"
                                    :key="property.age_property_name"
                                >
                                    <dt class="col-md-5">
                                        {{ property.name }}
                                        <span class="text-body-secondary">({{ property.age_property_name }})</span>
                                    </dt>
                                    <dd class="col-md-7 mb-1">
                                        <span
                                            class="badge me-1"
                                            :style="{ background: statusColor(property.status) }"
                                        >
                                            {{ statusLabel(property.status) }}
                                        </span>
                                        {{ property.value }}
                                    </dd>
                                </template>
                            </dl>
                        </div>
                        <div class="modal-footer">
                            <a
                                v-if="selectedItem.data.url"
                                :href="selectedItem.data.url"
                                class="btn btn-primary"
                            >
                                前往現有節點
                            </a>
                            <button
                                type="button"
                                class="btn btn-secondary"
                                @click="closeModal"
                            >
                                關閉
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </Teleport>
</template>
