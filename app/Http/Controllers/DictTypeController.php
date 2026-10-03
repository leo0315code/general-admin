<?php

namespace App\Http\Controllers;

use App\Models\DictItem;
use App\Models\DictType;
use App\Support\Dict;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 数据字典 - 字典类型控制器（CRUD）
 */
class DictTypeController extends Controller
{
    public function index(Request $request): View
    {
        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'name', 'type', 'created_at']
        );

        $dictTypes = DictType::query()
            ->withCount('items')
            ->when(
                $sort,
                fn ($query) => $query->orderBy($sort, $dir),
                fn ($query) => $query->orderBy('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        return view('dict-types.index', compact('dictTypes', 'sort', 'dir'));
    }

    public function create(): View
    {
        Gate::authorize('dict.create');

        return view('dict-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('dict.create');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:dict_types,type'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ], [
            'name.required' => '请输入字典类型名称。',
            'type.required' => '请输入类型标识。',
            'type.regex' => '类型标识只能包含小写字母、数字与下划线，且须以小写字母开头。',
            'type.unique' => '该类型标识已存在。',
        ]);

        // 新建页可同页批量添加字典项（空行丢弃，非法行直接回表单报错）
        $items = $this->normalizeItems($request);
        $this->validateItems($items);

        $dictType = DB::transaction(function () use ($validated, $request, $items) {
            $dictType = DictType::query()->create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'status' => $request->boolean('status'),
            ]);

            foreach ($items as $index => $row) {
                DictItem::query()->create([
                    'dict_type_id' => $dictType->id,
                    'label' => $row['label'],
                    'value' => $row['value'],
                    'sort' => $row['sort'] ?? $index,
                    'status' => true,
                ]);
            }

            return $dictType;
        });

        Dict::flush();

        // 创建后直达编辑页：该页内嵌「字典项」区块，可继续维护
        $message = '字典类型「'.$dictType->name.'」创建成功';
        $message .= $items === [] ? '，可在下方新增字典项。' : '，已同时创建 '.count($items).' 个字典项。';

        return redirect()
            ->route('dict-types.edit', $dictType)
            ->with('success', $message);
    }

    public function edit(DictType $dictType): View
    {
        Gate::authorize('dict.update');

        // 编辑页内嵌「字典项」区块：类型与子项同屏管理
        $items = $dictType->items()->orderBy('sort')->orderBy('id')->get();

        return view('dict-types.edit', compact('dictType', 'items'));
    }

    public function update(Request $request, DictType $dictType): RedirectResponse
    {
        Gate::authorize('dict.update');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => [
                'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                'unique:dict_types,type,'.$dictType->id,
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ], [
            'name.required' => '请输入字典类型名称。',
            'type.regex' => '类型标识只能包含小写字母、数字与下划线，且须以小写字母开头。',
            'type.unique' => '该类型标识已存在。',
        ]);

        $dictType->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'status' => $request->boolean('status'),
        ]);

        Dict::flush();

        return redirect()
            ->route('dict-types.index')
            ->with('success', '字典类型「'.$dictType->name.'」更新成功。');
    }

    public function destroy(DictType $dictType): RedirectResponse
    {
        Gate::authorize('dict.destroy');
        // 删除类型会级联删除其字典项
        $name = $dictType->name;
        $dictType->delete();

        Dict::flush();

        return redirect()
            ->route('dict-types.index')
            ->with('success', '字典类型「'.$name.'」已删除（含其字典项）。');
    }

    /**
     * 归一化新建页提交的字典项行：丢弃整行为空的行，只保留合法结构
     *
     * @return list<array{label: string, value: string, sort: int|null}>
     */
    private function normalizeItems(Request $request): array
    {
        $rows = $request->input('items');

        if (! is_array($rows)) {
            return [];
        }

        $filled = array_values(array_filter(
            $rows,
            fn ($row) => is_array($row)
                && (trim((string) ($row['label'] ?? '')) !== '' || trim((string) ($row['value'] ?? '')) !== '')
        ));

        return array_map(fn ($row) => [
            'label' => trim((string) ($row['label'] ?? '')),
            'value' => trim((string) ($row['value'] ?? '')),
            'sort' => is_numeric($row['sort'] ?? null) ? (int) $row['sort'] : null,
        ], $filled);
    }

    /**
     * 校验批量字典项：必填/长度/排序，以及同批次内 value 唯一
     * （DB 上有 (dict_type_id, value) 唯一约束，重复会在插入时才炸，必须在校验阶段拦住）
     *
     * @param  list<array{label: string, value: string, sort: int|null}>  $items
     *
     * @throws ValidationException
     */
    private function validateItems(array $items): void
    {
        if ($items === []) {
            return;
        }

        if (count($items) > 50) {
            throw ValidationException::withMessages(['items' => '一次最多添加 50 个字典项。']);
        }

        $validator = Validator::make(['items' => $items], [
            'items.*.label' => ['required', 'string', 'max:100'],
            'items.*.value' => ['required', 'string', 'max:100'],
            'items.*.sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'items.*.label.required' => '请填写字典项名称。',
            'items.*.label.max' => '字典项名称不能超过 100 个字符。',
            'items.*.value.required' => '请填写字典项值。',
            'items.*.value.max' => '字典项值不能超过 100 个字符。',
            'items.*.sort.min' => '排序须为 0-9999 的整数。',
            'items.*.sort.max' => '排序须为 0-9999 的整数。',
        ]);

        $counts = array_count_values(array_map(fn ($row) => $row['value'], $items));

        $validator->after(function ($validator) use ($items, $counts): void {
            foreach ($items as $index => $row) {
                if (($counts[$row['value']] ?? 0) > 1) {
                    $validator->errors()->add("items.$index.value", '同一类型下字典项值不能重复。');
                }
            }
        });

        $validator->validate();
    }
}
