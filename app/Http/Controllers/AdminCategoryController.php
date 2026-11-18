<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryStatusRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\JobCategory;
use App\Services\CategoryManagementService;
use App\Support\AdminCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminCategoryController extends Controller
{
    public function __construct(private CategoryManagementService $categories) {}

    public function index(Request $request)
    {
        Gate::authorize('manage-categories');
        $type = $request->route('categoryType');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:order,name,newest'],
            'parent' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $this->categories->query($type);
        if ($filters['q'] ?? null) {
            $search = addcslashes(AdminCategory::cleanName($filters['q']), '%_\\');
            $query->where('name', 'like', '%'.$search.'%');
        }
        if ($filters['status'] ?? null) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if ($type === 'specialties' && ($filters['parent'] ?? null)) {
            $query->where('job_category_id', $filters['parent']);
        }
        match ($filters['sort'] ?? 'order') {
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderBy('sort_order'),
        };

        return view('Admin.ad_pages.categories.index', $this->viewData($type) + [
            'records' => $query->orderBy('id')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'parents' => $type === 'specialties' ? JobCategory::orderBy('sort_order')->orderBy('name')->get() : collect(),
        ]);
    }

    public function form(Request $request)
    {
        Gate::authorize('manage-categories');
        $type = $request->route('categoryType');
        $record = $request->route('item') ? $this->categories->query($type)->findOrFail($request->route('item')) : null;
        $parents = JobCategory::where('is_active', true)->orWhere('id', $record?->job_category_id ?? 0)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('Admin.ad_pages.categories.form', $this->viewData($type) + compact('record', 'parents'));
    }

    public function store(StoreCategoryRequest $request)
    {
        $type = $request->route('categoryType');
        $this->categories->save($request->user(), $type, null, $request->validated());

        return redirect()->route('admin.'.$type.'.index')->with('category_notice', 'Đã tạo danh mục.');
    }

    public function update(UpdateCategoryRequest $request)
    {
        $type = $request->route('categoryType');
        $this->categories->save($request->user(), $type, (int) $request->route('item'), $request->validated());

        return redirect()->route('admin.'.$type.'.index')->with('category_notice', 'Đã cập nhật danh mục.');
    }

    public function status(CategoryStatusRequest $request)
    {
        $type = $request->route('categoryType');
        $this->categories->save($request->user(), $type, (int) $request->route('item'), $request->validated(), true);

        return redirect()->route('admin.'.$type.'.index')->with('category_notice', 'Đã cập nhật trạng thái ẩn/hiện.');
    }

    private function viewData(string $type): array
    {
        [, $title] = AdminCategory::definition($type);

        return ['categoryType' => $type, 'title' => $title, 'section' => $type, 'page' => $type.'.index', 'realCategories' => true, 'seed' => []];
    }
}
