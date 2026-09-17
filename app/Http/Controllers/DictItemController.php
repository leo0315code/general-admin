<?php

namespace App\Http\Controllers;

use App\Models\DictItem;
use App\Models\DictType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 数据字典 - 字典项控制器（CRUD，按字典类型管理）
 */
class DictItemController extends Controller
{
    /** 字典项列表（按 dict_type_id 过滤） */
    public function index(Request $request): View
    {
        $dictType = DictType::query()->findOrFail($request->integer('dict_type_id'));

        $items = $dictType->items()
            ->paginate(config('app.pagination', 15))
            ->withQueryString();

        return view('dict-items.index', compact('dictType', 'items'));
    }

    public function create(Request $request): View
    {
        $dictType = DictType::query()->findOrFail($request->integer('dict_type_id'));

        return view('dict-items.create', compact('dictType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dictType = DictType::query()->findOrFail($request->integer('dict_type_id'));

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string', 'max:100', 'unique:dict_items,value,NULL,id,dict_type_id,'.$dictType->id],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remark' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ], [
            'label.required' => '请输入字典项名称。',
            'value.required' => '请输入字典项值。',
            'value.unique' => '该类型下已存在相同的字典项值。',
        ]);

        DictItem::query()->create([
            'dict_type_id' => $dictType->id,
            'label' => $validated['label'],
            'value' => $validated['value'],
            'sort' => $validated['sort'] ?? 0,
            'status' => $request->boolean('status'),
            'remark' => $validated['remark'] ?? null,
        ]);

        return redirect()
            ->route('dict-items.index', ['dict_type_id' => $dictType->id])
            ->with('success', '字典项「'.$validated['label'].'」创建成功。');
    }

    public function edit(DictItem $dictItem): View
    {
        return view('dict-items.edit', compact('dictItem'));
    }

    public function update(Request $request, DictItem $dictItem): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'value' => [
                'required', 'string', 'max:100',
                'unique:dict_items,value,'.$dictItem->id.',id,dict_type_id,'.$dictItem->dict_type_id,
            ],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remark' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ], [
            'label.required' => '请输入字典项名称。',
            'value.unique' => '该类型下已存在相同的字典项值。',
        ]);

        $dictItem->update([
            'label' => $validated['label'],
            'value' => $validated['value'],
            'sort' => $validated['sort'] ?? 0,
            'status' => $request->boolean('status'),
            'remark' => $validated['remark'] ?? null,
        ]);

        return redirect()
            ->route('dict-items.index', ['dict_type_id' => $dictItem->dict_type_id])
            ->with('success', '字典项「'.$dictItem->label.'」更新成功。');
    }

    public function destroy(DictItem $dictItem): RedirectResponse
    {
        $dictTypeId = $dictItem->dict_type_id;
        $label = $dictItem->label;
        $dictItem->delete();

        return redirect()
            ->route('dict-items.index', ['dict_type_id' => $dictTypeId])
            ->with('success', '字典项「'.$label.'」已删除。');
    }
}
