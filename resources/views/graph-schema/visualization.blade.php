@extends('layouts.app')

@section('title', '視覺化 - Graph Schema 管理')

@section('content')
    <div class="container">
        @include('graph-schema.buttons', ['type' => 'visualization'])

        <h1>Graph Schema - 視覺化</h1>

        <div id="graph-visualization" style="border: 1px solid #ccc;"></div>
    </div>
@endsection

@push('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.9.0/d3.min.js"
    integrity="sha512-vc58qvvBdrDR4etbxMdlTt4GBQk1qjvyORR2nrsPsFPyrs+/u5c3+1Ct6upOgdZoIl7eq6k3a1UPDSNAQi/32A=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script type="module">
    const vertexTypeList = @json($vertexTypeList);
    const edgeTypeList = @json($edgeTypeList);
    const nodes = vertexTypeList.map(vertexType => ({
        id: vertexType.id,
        name: vertexType.name,
    }));
    const links = edgeTypeList.map(edgeType => ({
        id: edgeType.id,
        source: edgeType.start_vertex_id,
        target: edgeType.end_vertex_id,
        name: edgeType.name,
    }));

    console.log(nodes);
    console.log(links);

    const drag = simulation => {
        function dragstarted(event, d) {
            if (!event.active) simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        }

        function dragged(event, d) {
            d.fx = event.x;
            d.fy = event.y;
        }

        function dragended(event, d) {
            if (!event.active) simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        }

        return d3.drag()
            .on("start", dragstarted)
            .on("drag", dragged)
            .on("end", dragended);
    }

    const linkArc = d =>`M${d.source.x},${d.source.y}A0,0 0 0,1 ${d.target.x},${d.target.y}`;

    const graphVisualization = document.getElementById('graph-visualization');
    const width = graphVisualization.clientWidth;
    const height = 600;
    const svg = d3.create("svg")
        .attr("viewBox", [-width / 2, -height / 2, width, height])
        .attr("width", width)
        .attr("height", height)
        .attr("style", "max-width: 100%; height: auto; font: 12px sans-serif;");

    svg.append("defs")
        .append("marker")
        .attr("id", "arrowhead")
        .attr("viewBox", "0 0 10 7")
        .attr("refX", 10)
        .attr("refY", 3.5)
        .attr("markerWidth", 10)
        .attr("markerHeight", 7)
        .attr("orient", "auto")
        .append("polygon")
        .attr("points", "0 0, 10 3.5, 0 7")
        .attr("fill", "black");


    const simulation = d3.forceSimulation(nodes)
        .force("link", d3.forceLink(links).id(d => d.id))
        .force("charge", d3.forceManyBody().strength(-2000)) // Increase the repulsion strength
        .force("x", d3.forceX())
        .force("y", d3.forceY());

    const link = svg.append("g")
        .attr("fill", "none")
        .attr("stroke-width", 1.5)
        .selectAll("path")
        .data(links)
        .join("path")
        .attr("stroke", 'black')
        .attr("marker-end", 'url(#arrowhead)');

    // Add labels for links
    const linkLabels = svg.append("g")
        .selectAll("text")
        .data(links)
        .join("text")
        .attr("font-size", 10)
        .attr("fill", "black")
        .attr("text-anchor", "middle")
        .text(d => d.name);

    const node = svg.append("g")
        .attr("fill", "currentColor")
        .attr("stroke-linecap", "round")
        .attr("stroke-linejoin", "round")
        .selectAll("g")
        .data(nodes)
        .join("g")
        .call(drag(simulation));

    node.append("circle")
      .attr("stroke", "white")
      .attr("stroke-width", 1.5)
      .attr("r", 4);

    node.append("text")
        .attr("x", 8)
        .attr("y", "0.31em")
        .text(d => d.name)
        .clone(true).lower()
        .attr("fill", "none")
        .attr("stroke", "white")
        .attr("stroke-width", 3);

    simulation.on("tick", () => {
        link.attr("d", linkArc);
        node.attr("transform", d => `translate(${d.x},${d.y})`);

        // Position link labels at the midpoint of each link
        linkLabels.attr("x", d => (d.source.x + d.target.x) / 2)
                  .attr("y", d => (d.source.y + d.target.y) / 2);
    });

    graphVisualization.append(svg.node());

</script>
@endpush
