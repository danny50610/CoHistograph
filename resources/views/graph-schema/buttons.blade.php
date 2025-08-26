<div class="btn-group">
    <a class="btn btn-outline-primary @if($type == 'vertex') active @endif" href="{{ route('graph-schema.vertex-type.index')}}" role="button">Vertex</a>
    <a class="btn btn-outline-primary @if($type == 'edge') active @endif" href="{{ route('graph-schema.edge-type.index')}}" role="button">Edge</a>
</div>
