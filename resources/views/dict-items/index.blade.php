<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">字典项：{{ $dictType->name }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">类型标识：<span class="font-mono">{{ $dictType->type }}</span></p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('dict-types.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回类型
                </a>
                <a href="{{ route('dict-items.create', ['dict_type_id' => $dictType->id]) }}" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    新建字典项
                </a>
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">名称</th>
                        <th class="th">值</th>
                        <th class="th">排序</th>
                        <th class="th">状态</th>
                        <th class="th">备注</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($items as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $item->id }}</td>
                            <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $item->label }}</td>
                            <td class="td">
                                <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-600 dark:text-gray-300">{{ $item->value }}</span>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $item->sort }}</td>
                            <td class="td">
                                @if ($item->status)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">启用</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">停用</span>
                                @endif
                            </td>
                            <td class="td text-gray-500 dark:text-gray-400 max-w-[180px] truncate">{{ $item->remark ?? '—' }}</td>
                            <td class="td text-right whitespace-nowrap">
                                <a href="{{ route('dict-items.edit', $item) }}" class="btn-ghost">
                                    <x-icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                    编辑
                                </a>
                                <form method="POST" action="{{ route('dict-items.destroy', $item) }}" class="inline" onsubmit="return confirm('确定要删除字典项「{{ $item->label }}」吗？');">
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
                                <x-icon name="heroicon-o-list-bullet" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">该类型下暂无字典项</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $items->links() }}
        </div>
    </div>
</x-app-layout>
