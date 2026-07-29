<div class="d-flex">
    <div class="btn-group me-2">
        <a class="btn btn-outline-primary @if($type == 'vertex') active @endif" href="{{ route('graph-schema.vertex-type.index')}}" role="button">Vertex</a>
        <a class="btn btn-outline-primary @if($type == 'edge') active @endif" href="{{ route('graph-schema.edge-type.index')}}" role="button">Edge</a>
    </div>
    <div class="btn-group">
        <a class="btn btn-outline-primary @if($type == 'visualization') active @endif" href="{{ route('graph-schema.visualization')}}" role="button">視覺化</a>
    </div>
</div>
