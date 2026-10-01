<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Projects\Actions\CreateProjectAction;
use App\Domain\Projects\Actions\UpdateProjectAction;
use App\Domain\Projects\Actions\UpdateProjectStatusAction;
use App\Domain\Projects\Exports\ProjectsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Requests\Projects\UpdateProjectStatusRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Client projects — FRD V01.14 §1.12.
 *
 * Same "no implicit route-model binding" reasoning as CompanyController:
 * SubstituteBindings runs before the 'tenant' middleware, so an implicit
 * Project $project param would throw TenantContextMissingException. The
 * model is looked up explicitly inside each method, after middleware.
 *
 * No hard-delete route — deactivation goes through status().
 */
class ProjectController extends Controller
{
    /**
     * Explicit allowlist for `sort_by` — never the raw request value.
     */
    private const SORTABLE_COLUMNS = ['code', 'name', 'operational_status', 'created_at'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Project::class);

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = $request->string('sort_dir', 'asc')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortBy = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';

        $projects = $this->filteredQuery($request)
            ->orderBy($sortBy, $sortDir)
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request, CreateProjectAction $action)
    {
        $project = $action->execute($request->validated(), $request->user());

        return $this->present($project->id)->response()->setStatusCode(201);
    }

    public function show(int $project)
    {
        $resource = $this->present($project);

        Gate::authorize('view', $resource->resource);

        return $resource;
    }

    public function update(UpdateProjectRequest $request, int $project, UpdateProjectAction $action)
    {
        $project = Project::findOrFail($project);

        $project = $action->execute($project, $request->validated(), $request->user());

        return $this->present($project->id);
    }

    public function status(UpdateProjectStatusRequest $request, int $project, UpdateProjectStatusAction $action)
    {
        $project = Project::findOrFail($project);
        $project = $action->execute($project, $request->validated('status'), $request->user());

        return $this->present($project->id);
    }

    /** The project with everything the resource shows (representative, user count) loaded. */
    private function present(int $id): ProjectResource
    {
        return ProjectResource::make(
            Project::withUsersCount()->with(['company', 'representative', 'updatedBy'])->findOrFail($id)
        );
    }

    public function export(Request $request)
    {
        Gate::authorize('export', Project::class);

        $projects = $this->filteredQuery($request)
            ->orderBy('name')
            ->get();

        if ($request->query('format', 'xlsx') === 'pdf') {
            // DomPDF is CPU-heavy on big lists; the default 30s cap on shared
            // hosting turns a slow export into a 500. Excel is unaffected.
            set_time_limit(180);

            $pdf = app('dompdf.wrapper')->loadView('tenant.projects.export-pdf', ['projects' => $projects]);

            return $pdf->download('projects.pdf');
        }

        return Excel::download(new ProjectsExport($projects), 'projects.xlsx');
    }

    /**
     * The list's filters (company + status dropdowns and the search box),
     * shared by index() and export() so an export always contains exactly
     * the rows the user sees on screen. The search box covers every column
     * the table shows that the database holds (ID, company, project name).
     * `company_id` also feeds the projects section of a company's page.
     */
    private function filteredQuery(Request $request): Builder
    {
        return Project::query()
            ->withUsersCount()
            ->with(['company', 'representative'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            });
    }
}
