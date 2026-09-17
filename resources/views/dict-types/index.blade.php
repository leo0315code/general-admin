<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">数据字典</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">管理字典类型与字典项，统一业务状态值</p>
            </div>
            <a href="{{ route('dict-types.create') }}" class="btn-primary">
                <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                新建类型
            </a>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">类型名称</th>
                        <th class="th">类型标识</th>
                        <th class="th">描述</th>
                        <th class="th">字典项</th>
                        <th class="th">状态</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($dictTypes as $dictType)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $dictType->id }}</td>
                            <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $dictType->name }}</td>
                            <td class="td">
                                <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-600 dark:text-gray-300">{{ $dictType->type }}</span>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ $dictType->description ?? '—' }}</td>
                            <td class="td">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-sky-100 dark:bg-sky-500/20 text-xs font-medium text-sky-700 dark:text-sky-300">{{ $dictType->items_count }}</span>
                            </td>
                            <td class="td">
                                @if ($dictType->status)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">启用</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">停用</span>
                                @endif
                            </td>
                            <td class="td text-right whitespace-nowrap">
                                <a href="{{ route('dict-items.index', ['dict_type_id' => $dictType->id]) }}" class="btn-ghost">
                                    <x-icon name="heroicon-o-list-bullet" class="h-4 w-4" />
                                    字典项
                                </a>
                                <a href="{{ route('dict-types.edit', $dictType) }}" class="btn-ghost">
                                    <x-icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                    编辑
                                </a>
                                <form method="POST" action="{{ route('dict-types.destroy', $dictType) }}" class="inline" onsubmit="return confirm('确定要删除类型「{{ $dictType->name }}」及其全部字典项吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger-ghost">
                                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                        删除
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-bookmark-square" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">暂无字典类型</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $dictTypes->links() }}
        </div>
    </div>
</x-app-layout>
