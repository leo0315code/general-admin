<?php

namespace App\Http\Controllers;

use App\Models\DictItem;
use App\Models\DictType;
use App\Support\Dict;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * 数据字典 - 字典项控制器（CRUD，按字典类型管理）
 */
class DictItemController extends Controller
{
    /** 字典项列表（按 dict_type_id 过滤 + 每页条数/排序） */
    public function index(Request $request): View
    {
        $dictType = DictType::query()->findOrFail($request->integer('dict_type_id'));

        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'value', 'sort', 'created_at']
        );

        $items = $dictType->items()
            ->when(
                $sort,
                fn ($query) => $query->reorder()->orderBy($sort, $dir),
                fn ($query) => $query->orderBy('sort')->orderBy('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        return view('dict-items.index', compact('dictType', 'items', 'sort', 'dir'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('dict.create');
        $dictType = DictType::query()->findOrFail($request->integer('dict_type_id'));

        return view('dict-items.create', compact('dictType'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('dict.create');
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

        Dict::flush();

        return redirect()
            ->to($this->resolveRedirect($request, route('dict-items.index', ['dict_type_id' => $dictType->id])))
            ->with('success', '字典项「'.$validated['label'].'」创建成功。');
    }

    public function edit(DictItem $dictItem): View
    {
        Gate::authorize('dict.update');

        return view('dict-items.edit', compact('dictItem'));
    }

    public function update(Request $request, DictItem $dictItem): RedirectResponse
    {
        Gate::authorize('dict.update');
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

        Dict::flush();

        return redirect()
            ->to($this->resolveRedirect($request, route('dict-items.index', ['dict_type_id' => $dictItem->dict_type_id])))
            ->with('success', '字典项「'.$dictItem->label.'」更新成功。');
    }

    public function destroy(Request $request, DictItem $dictItem): RedirectResponse
    {
        Gate::authorize('dict.destroy');
        $dictTypeId = $dictItem->dict_type_id;
        $label = $dictItem->label;
        $dictItem->delete();

        Dict::flush();

        return redirect()
            ->to($this->resolveRedirect($request, route('dict-items.index', ['dict_type_id' => $dictTypeId])))
            ->with('success', '字典项「'.$label.'」已删除。');
    }

    /**
     * 解析回跳地址：仅接受 /console/ 开头的站内相对路径，防开放重定向。
     * 来自「字典类型编辑页」内嵌字典项区块时，保存/删除后回到该编辑页。
     */
    private function resolveRedirect(Request $request, string $fallback): string
    {
        $to = $request->input('redirect_to');
        // 后台前缀可配置（config/app.php 的 admin_prefix），这里只放行同一前缀下的站内路径
        $prefix = '/'.trim((string) config('app.admin_prefix', 'console'), '/').'/';

        if (! is_string($to) || ! str_starts_with($to, $prefix)) {
            return $fallback;
        }

        return str_contains($to, '..') || str_contains($to, '\\') ? $fallback : $to;
    }
}
