{{ $name }}
@foreach($roles as $role)
    <span class="badge badge-primary">{{ $role['display_name'] }}</span>
@endforeach
