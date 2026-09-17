<?php

namespace App\Http\Controllers;

use App\Models\DictType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 数据字典 - 字典类型控制器（CRUD）
 */
class DictTypeController extends Controller
{
    public function index(): View
    {
        $dictTypes = DictType::query()
            ->withCount('items')
            ->orderBy('id')
            ->paginate(config('app.pagination', 15));

        return view('dict-types.index', compact('dictTypes'));
    }

    public function create(): View
    {
        return view('dict-types.create');
    }

    public function store(Request $request): RedirectResponse
    {
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

        DictType::query()->create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'status' => $request->boolean('status'),
        ]);

        return redirect()
            ->route('dict-types.index')
            ->with('success', '字典类型「'.$validated['name'].'」创建成功。');
    }

    public function edit(DictType $dictType): View
    {
        return view('dict-types.edit', compact('dictType'));
    }

    public function update(Request $request, DictType $dictType): RedirectResponse
    {
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

        return redirect()
            ->route('dict-types.index')
            ->with('success', '字典类型「'.$dictType->name.'」更新成功。');
    }

    public function destroy(DictType $dictType): RedirectResponse
    {
        // 删除类型会级联删除其字典项
        $name = $dictType->name;
        $dictType->delete();

        return redirect()
            ->route('dict-types.index')
            ->with('success', '字典类型「'.$name.'」已删除（含其字典项）。');
    }
}
