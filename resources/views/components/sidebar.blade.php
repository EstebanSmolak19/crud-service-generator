<div class="w-64 bg-gray-950 p-4 border-r border-gray-800 flex flex-col h-full">
    <div class="mb-6 px-3">
        <h2 class="text-sm font-bold text-white tracking-wider uppercase">CRUD Studio <span class="text-indigo-500 text-xs font-mono">v2</span></h2>
    </div>

    <nav class="flex-1 flex flex-col space-y-1.5">
        @foreach($menu as $item)
            <button
                @click="activeTab = '{{ $item['route'] }}'"
                :class="activeTab === '{{ $item['route'] }}' ? 'bg-indigo-600 text-white font-semibold' : 'text-gray-400 hover:bg-gray-900 hover:text-gray-200'"
                class="w-full text-left px-3 py-2.5 text-xs rounded-lg transition-colors flex items-center space-x-2">

                <span>{{ $item['title'] }}</span>

            </button>
        @endforeach
    </nav>
</div>