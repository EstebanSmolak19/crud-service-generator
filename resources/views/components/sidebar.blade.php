<div class="w-64 bg-gray-950 p-4">
    @foreach($menu as $key => $item)
        <button @click="activeTab = '{{ $item['title'] }}'">
            {{ $item['title'] }}
        </button>
    @endforeach
</div>