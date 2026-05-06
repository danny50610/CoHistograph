@extends('layouts.app')

@section('title', '視覺化 - Graph Schema 管理')

@section('content')
    <div class="container-fluid">
        @include('graph-schema.buttons', ['type' => 'visualization'])

        <h1>Graph Schema - 視覺化</h1>

        <p class="text-body-secondary small">
            <i class="fa-solid fa-circle-info"></i>
            可拖曳節點移動位置、滾輪縮放、點擊節點或邊的名稱以查看詳細資料。
        </p>

        <div id="graph-visualization" style="border: 1px solid #dee2e6; border-radius: 8px; background: #f8f9fa;"></div>
    </div>

    {{-- Detail Modal --}}
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailModalBody"></div>
                <div class="modal-footer">
                    <a id="detailModalLink" href="#" class="btn btn-primary">
                        <i class="fa-solid fa-receipt"></i> 前往詳細頁面
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js"
    integrity="sha512-vc58qvvBdrDR4etbxMdlTt4GBQk1qjvyORR2nrsPsFPyrs+/u5c3+1Ct6upOgdZoIl7eq6k3a1UPDSNAQi/32A=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script type="module">
    const vertexTypeList = @json($vertexTypeList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    const edgeTypeList = @json($edgeTypeList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

    const nodes = vertexTypeList.map(v => ({
        id: v.id,
        name: v.name,
        age_label_name: v.age_label_name,
        description: v.description,
        properties: v.properties,
        url: v.url,
    }));
    const links = edgeTypeList.map(e => ({
        id: e.id,
        source: e.start_vertex_id,
        target: e.end_vertex_id,
        name: e.name,
        reverse_name: e.reverse_name,
        age_label_name: e.age_label_name,
        description: e.description,
        start_vertex_name: e.start_vertex_name,
        end_vertex_name: e.end_vertex_name,
        properties: e.properties,
        url: e.url,
    }));

    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

    function buildPropertiesHtml(properties) {
        if (!properties || properties.length === 0) {
            return '<p class="text-body-secondary mb-0">目前沒有任何 Property</p>';
        }
        let html = '<dl class="row mb-0">';
        for (const p of properties) {
            html += `<dt class="col-md-5">${p.name} <span class="text-body-secondary">(${p.age_property_name})</span></dt>`;
            html += `<dd class="col-md-7 mb-0"><span class="badge text-bg-info">${p.age_property_type}</span></dd>`;
        }
        html += '</dl>';
        return html;
    }

    function showVertexDetail(d) {
        document.getElementById('detailModalLabel').textContent = `Vertex - ${d.name}`;
        const body = `
            <dl class="row mb-2">
                <dt class="col-md-5">名稱</dt>
                <dd class="col-md-7">${d.name}</dd>
                <dt class="col-md-5">Label 名稱</dt>
                <dd class="col-md-7">${d.age_label_name}</dd>
                <dt class="col-md-5">描述</dt>
                <dd class="col-md-7">${d.description || ''}</dd>
            </dl>
            <strong>Properties</strong>
            <div class="mt-1">${buildPropertiesHtml(d.properties)}</div>
        `;
        document.getElementById('detailModalBody').innerHTML = body;
        document.getElementById('detailModalLink').href = d.url;
        detailModal.show();
    }

    function showEdgeDetail(d) {
        document.getElementById('detailModalLabel').textContent = `Edge - ${d.name}`;
        const body = `
            <dl class="row mb-2">
                <dt class="col-md-5">名稱</dt>
                <dd class="col-md-7">${d.name}</dd>
                <dt class="col-md-5">反向名稱</dt>
                <dd class="col-md-7">${d.reverse_name || ''}</dd>
                <dt class="col-md-5">Label 名稱</dt>
                <dd class="col-md-7">${d.age_label_name}</dd>
                <dt class="col-md-5">描述</dt>
                <dd class="col-md-7">${d.description || ''}</dd>
                <dt class="col-md-5">起點 Vertex</dt>
                <dd class="col-md-7">${d.start_vertex_name}</dd>
                <dt class="col-md-5">終點 Vertex</dt>
                <dd class="col-md-7">${d.end_vertex_name}</dd>
            </dl>
            <strong>Properties</strong>
            <div class="mt-1">${buildPropertiesHtml(d.properties)}</div>
        `;
        document.getElementById('detailModalBody').innerHTML = body;
        document.getElementById('detailModalLink').href = d.url;
        detailModal.show();
    }

    const nodeRadius = 32;
    const defaultWidth = 800;
    const color = d3.scaleOrdinal(d3.schemeTableau10);

    const graphVisualization = document.getElementById('graph-visualization');
    const width = graphVisualization.clientWidth || defaultWidth;
    const height = 600;

    const svg = d3.create("svg")
        .attr("viewBox", [-width / 2, -height / 2, width, height])
        .attr("width", width)
        .attr("height", height)
        .attr("style", "max-width: 100%; height: auto;");

    svg.append("defs")
        .append("marker")
        .attr("id", "arrowhead")
        .attr("viewBox", "0 0 10 7")
        .attr("refX", 10)
        .attr("refY", 3.5)
        .attr("markerWidth", 8)
        .attr("markerHeight", 6)
        .attr("orient", "auto")
        .append("polygon")
        .attr("points", "0 0, 10 3.5, 0 7")
        .attr("fill", "#888");

    const g = svg.append("g");

    svg.call(
        d3.zoom()
            .scaleExtent([0.1, 10])
            .on("zoom", (event) => g.attr("transform", event.transform))
    );

    const simulation = d3.forceSimulation(nodes)
        .force("link", d3.forceLink(links).id(d => d.id).distance(180))
        .force("charge", d3.forceManyBody().strength(-800))
        .force("x", d3.forceX())
        .force("y", d3.forceY())
        .force("collision", d3.forceCollide(nodeRadius + 15));

    const link = g.append("g")
        .selectAll("path")
        .data(links)
        .join("path")
        .attr("fill", "none")
        .attr("stroke", "#aaa")
        .attr("stroke-width", 1.5)
        .attr("marker-end", "url(#arrowhead)");

    const linkLabels = g.append("g")
        .selectAll("text")
        .data(links)
        .join("text")
        .attr("font-size", 11)
        .attr("fill", "#555")
        .attr("text-anchor", "middle")
        .attr("dominant-baseline", "middle")
        .attr("cursor", "pointer")
        .text(d => d.name)
        .on("click", (event, d) => { showEdgeDetail(d); });

    let dragMoved = false;

    const dragBehavior = d3.drag()
        .on("start", (event, d) => {
            dragMoved = false;
            if (!event.active) simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        })
        .on("drag", (event, d) => {
            dragMoved = true;
            d.fx = event.x;
            d.fy = event.y;
        })
        .on("end", (event, d) => {
            if (!event.active) simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        });

    const nodeGroup = g.append("g")
        .selectAll("g")
        .data(nodes)
        .join("g")
        .attr("cursor", "pointer")
        .call(dragBehavior)
        .on("click", (event, d) => {
            if (!dragMoved) {
                showVertexDetail(d);
            }
        });

    nodeGroup.append("circle")
        .attr("r", nodeRadius)
        .attr("fill", (d, i) => color(i))
        .attr("stroke", "white")
        .attr("stroke-width", 2);

    nodeGroup.append("text")
        .attr("text-anchor", "middle")
        .attr("dominant-baseline", "middle")
        .attr("font-size", 12)
        .attr("font-weight", "bold")
        .attr("fill", "white")
        .attr("pointer-events", "none")
        .text(d => d.name);

    const linkPath = d => {
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

    simulation.on("tick", () => {
        link.attr("d", linkPath);

        linkLabels
            .attr("x", d => {
                if (d.source.id === d.target.id) {
                    return d.source.x;
                }

                return (d.source.x + d.target.x) / 2;
            })
            .attr("y", d => {
                if (d.source.id === d.target.id) {
                    return d.source.y - nodeRadius * 2.8;
                }

                return (d.source.y + d.target.y) / 2;
            });

        nodeGroup.attr("transform", d => `translate(${d.x},${d.y})`);
    });

    graphVisualization.append(svg.node());
</script>
@endpush
