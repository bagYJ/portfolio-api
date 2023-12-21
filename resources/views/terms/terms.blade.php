{!! $terms['ds_content'] !!}
@if(count($versions) > 1)
    <select onchange="changeVersion(this.value)">
        @foreach($versions as $key => $value)
            <option value="{{ $key }}"
                    @if($key == $terms['no_version']) selected @endif
            >{{ substr($value,0, 10) }}</option>
        @endforeach
    </select>
    <script>
        function changeVersion(version) {
            location.href = window.location.pathname + '?no_version=' + version;
        }
    </script>
@endif
