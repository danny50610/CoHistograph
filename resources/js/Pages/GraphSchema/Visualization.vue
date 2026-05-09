<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import * as d3 from 'd3';

const props = defineProps({
    vertexTypeList: {
        type: Array,
        default: () => [],
    },
    edgeTypeList: {
        type: Array,
        default: () => [],
    },
    routeVertexTypeIndex: String,
    routeEdgeTypeIndex: String,
    routeVisualization: String,
});

const graphContainer = ref(null);
const selectedItem = ref(null);
let simulation = null;

function closeModal() {
    selectedItem.value = null;
}

function showVertexDetail(d) {
    selectedItem.value = { type: 'vertex', data: d };
}

function showEdgeDetail(d) {
    selectedItem.value = { type: 'edge', data: d };
}

onMounted(() => {
    if (!graphContainer.value) {
        return;
    }

    const nodes = props.vertexTypeList.map(v => ({ ...v }));
    const links = props.edgeTypeList.map(e => ({
        ...e,
        source: e.start_vertex_id,
        target: e.end_vertex_id,
    }));

    const nodeRadius = 32;
    const defaultWidth = 800;
    const color = d3.scaleOrdinal(d3.schemeTableau10);

    const container = graphContainer.value;
    const width = container.clientWidth || defaultWidth;
    const height = 600;

    const svg = d3.create('svg')
        .attr('viewBox', [-width / 2, -height / 2, width, height])
        .attr('width', width)
        .attr('height', height)
        .attr('style', 'max-width: 100%; height: auto;');

    svg.append('defs')
        .append('marker')
        .attr('id', 'arrowhead')
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
        .force('link', d3.forceLink(links).id(d => d.id).distance(180))
        .force('charge', d3.forceManyBody().strength(-800))
        .force('x', d3.forceX())
        .force('y', d3.forceY())
        .force('collision', d3.forceCollide(nodeRadius + 15));

    const link = g.append('g')
        .selectAll('path')
        .data(links)
        .join('path')
        .attr('fill', 'none')
        .attr('stroke', '#aaa')
        .attr('stroke-width', 1.5)
        .attr('marker-end', 'url(#arrowhead)');

    const linkLabels = g.append('g')
        .selectAll('text')
        .data(links)
        .join('text')
        .attr('font-size', 11)
        .attr('fill', '#555')
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'middle')
        .attr('cursor', 'pointer')
        .text(d => d.name)
        .on('click', (_event, d) => { showEdgeDetail(d); });

    let dragMoved = false;

    const dragBehavior = d3.drag()
        .on('start', (event, d) => {
            dragMoved = false;
            if (!event.active) { simulation.alphaTarget(0.3).restart(); }
            d.fx = d.x;
            d.fy = d.y;
        })
        .on('drag', (event, d) => {
            dragMoved = true;
            d.fx = event.x;
            d.fy = event.y;
        })
        .on('end', (event, d) => {
            if (!event.active) { simulation.alphaTarget(0); }
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
                showVertexDetail(d);
            }
        });

    nodeGroup.append('circle')
        .attr('r', nodeRadius)
        .attr('fill', (d, i) => color(i))
        .attr('stroke', 'white')
        .attr('stroke-width', 2);

    nodeGroup.append('text')
        .attr('text-anchor', 'middle')
        .attr('dominant-baseline', 'middle')
        .attr('font-size', 12)
        .attr('font-weight', 'bold')
        .attr('fill', 'white')
        .attr('pointer-events', 'none')
        .text(d => d.name);

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
            .attr('x', d => {
                if (d.source.id === d.target.id) {
                    return d.source.x;
                }

                return (d.source.x + d.target.x) / 2;
            })
            .attr('y', d => {
                if (d.source.id === d.target.id) {
                    return d.source.y - nodeRadius * 2.8;
                }

                return (d.source.y + d.target.y) / 2;
            });

        nodeGroup.attr('transform', d => `translate(${d.x},${d.y})`);
    });

    container.appendChild(svg.node());
});

onUnmounted(() => {
    if (simulation) {
        simulation.stop();
    }
});
</script>

<template>
    <div class="container-fluid">
        <div class="d-flex mb-3">
            <div class="btn-group me-2">
                <a class="btn btn-outline-primary" :href="routeVertexTypeIndex">Vertex</a>
                <a class="btn btn-outline-primary" :href="routeEdgeTypeIndex">Edge</a>
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-primary active" :href="routeVisualization">視覺化</a>
            </div>
        </div>

        <h1>Graph Schema - 視覺化</h1>

        <p class="text-body-secondary small">
            <i class="fa-solid fa-circle-info"></i>
            可拖曳節點移動位置、滾輪縮放、點擊節點或邊的名稱以查看詳細資料。
        </p>

        <div
            ref="graphContainer"
            style="border: 1px solid #dee2e6; border-radius: 8px; background: #f8f9fa;"
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
                                {{ selectedItem.type === 'vertex' ? 'Vertex' : 'Edge' }} - {{ selectedItem.data.name }}
                            </h5>
                            <button
                                type="button"
                                class="btn-close"
                                @click="closeModal"
                            ></button>
                        </div>
                        <div class="modal-body">
                            <template v-if="selectedItem.type === 'vertex'">
                                <dl class="row mb-2">
                                    <dt class="col-md-5">名稱</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.name }}</dd>
                                    <dt class="col-md-5">Label 名稱</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.age_label_name }}</dd>
                                    <dt class="col-md-5">描述</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.description || '' }}</dd>
                                </dl>
                                <strong>Properties</strong>
                                <div class="mt-1">
                                    <p
                                        v-if="!selectedItem.data.properties?.length"
                                        class="text-body-secondary mb-0"
                                    >
                                        目前沒有任何 Property
                                    </p>
                                    <dl
                                        v-else
                                        class="row mb-0"
                                    >
                                        <template
                                            v-for="p in selectedItem.data.properties"
                                            :key="p.name"
                                        >
                                            <dt class="col-md-5">
                                                {{ p.name }}
                                                <span class="text-body-secondary">({{ p.age_property_name }})</span>
                                            </dt>
                                            <dd class="col-md-7 mb-0">
                                                <span class="badge text-bg-info">{{ p.age_property_type }}</span>
                                            </dd>
                                        </template>
                                    </dl>
                                </div>
                            </template>
                            <template v-else>
                                <dl class="row mb-2">
                                    <dt class="col-md-5">名稱</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.name }}</dd>
                                    <dt class="col-md-5">反向名稱</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.reverse_name || '' }}</dd>
                                    <dt class="col-md-5">Label 名稱</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.age_label_name }}</dd>
                                    <dt class="col-md-5">描述</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.description || '' }}</dd>
                                    <dt class="col-md-5">起點 Vertex</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.start_vertex_name }}</dd>
                                    <dt class="col-md-5">終點 Vertex</dt>
                                    <dd class="col-md-7">{{ selectedItem.data.end_vertex_name }}</dd>
                                </dl>
                                <strong>Properties</strong>
                                <div class="mt-1">
                                    <p
                                        v-if="!selectedItem.data.properties?.length"
                                        class="text-body-secondary mb-0"
                                    >
                                        目前沒有任何 Property
                                    </p>
                                    <dl
                                        v-else
                                        class="row mb-0"
                                    >
                                        <template
                                            v-for="p in selectedItem.data.properties"
                                            :key="p.name"
                                        >
                                            <dt class="col-md-5">
                                                {{ p.name }}
                                                <span class="text-body-secondary">({{ p.age_property_name }})</span>
                                            </dt>
                                            <dd class="col-md-7 mb-0">
                                                <span class="badge text-bg-info">{{ p.age_property_type }}</span>
                                            </dd>
                                        </template>
                                    </dl>
                                </div>
                            </template>
                        </div>
                        <div class="modal-footer">
                            <a
                                :href="selectedItem.data.url"
                                class="btn btn-primary"
                            >
                                <i class="fa-solid fa-receipt"></i> 前往詳細頁面
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
