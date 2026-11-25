<?php

namespace App\Domain\Dashboard;

use App\Domain\PurchaseOrders\Quota\PurchaseOrderStatus;
use App\Domain\PurchaseOrders\Support\PurchaseOrderStatusSql;
use App\Http\Resources\NotificationResource;
use App\Models\Asset;
use App\Models\Driver;
use App\Models\MainService;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The figures of the dashboard (FRD V01.14 "لوحة التحكم", part 8.3), for ONE user and within what he may see —
 * every section is built from the same scopes the pages behind it use, so a number on the dashboard can never show
 * more than the list it summarises:
 *
 *  - trips (30-day chart, the five statuses, the share of each main service): `Trip::scopeVisibleTo` — GCM staff all,
 *    a client his projects, a driver his own, a contractor user those of his drivers and vehicles;
 *  - resources (drivers, vehicles, containers, tanks by status): GCM staff all, a contractor user his contractor's
 *    (`VisibleToContractorAccount`); the client and the driver have no such section;
 *  - purchase orders (in force / about to run out / exhausted): whoever may open the PO page (`viewAny`) — the System
 *    Admin and the Auditor all, a client's project manager those of his projects;
 *  - the latest ten notifications of the user himself.
 *
 * A section the user may not see is simply absent from the answer (the page draws what it gets). Readings of ours:
 * a cancelled trip counts in the status figures (as its own one) but not in the 30-day chart or the services share
 * (it is "as if it didn't happen", ج2); "about to run out" and "exhausted" use each item's own alert percent and the
 * decided meaning of "exhausted" (ب8) through PurchaseOrderStatusSql, not the FRD's fixed 20%; only ACTIVE POs count.
 */
class DashboardStats
{
    public const DAYS = 30;

    public function for(User $user, Request $request): array
    {
        $sections = [];

        if ($user->can('viewAny', Trip::class)) {
            $sections['trips_30_days'] = $this->tripsByDay($user);
            $sections['trip_statuses'] = $this->tripStatuses($user);
            $sections['services_share'] = $this->servicesShare($user);
        }

        if ($user->hasAnyRole(['system_admin', 'data_entry', 'auditor']) || $user->isContractorUser()) {
            $sections['resources'] = $this->resources($user);
        }

        if ($user->can('viewAny', PurchaseOrder::class)) {
            $sections['purchase_orders'] = $this->purchaseOrders($user);
        }

        $sections['notifications'] = NotificationResource::collection(
            UserNotification::query()->forUser($user)->orderByDesc('id')->limit(10)->get()
        )->resolve($request);

        // The driver creates his trips from here (FRD §1.14: "من خلال لوحة التحكم في حساب السائق").
        $sections['can_create_trip'] = $user->can('createAsDriver', Trip::class);

        return $sections;
    }

    /**
     * Trips of the last 30 days (today included) by the day of the trip, zero days kept so the chart has no gaps.
     *
     * @return list<array{date: string, count: int}>
     */
    private function tripsByDay(User $user): array
    {
        $from = CarbonImmutable::now()->startOfDay()->subDays(self::DAYS - 1);

        $counts = Trip::query()->visibleTo($user)
            ->where('trips.status', '!=', Trip::STATUS_CANCELLED)
            ->where('trips.trip_at', '>=', $from)
            ->toBase()
            ->selectRaw('DATE(trips.trip_at) AS day, COUNT(*) AS n')
            ->groupBy('day')
            ->pluck('n', 'day');

        return collect(range(0, self::DAYS - 1))->map(function (int $i) use ($from, $counts) {
            $date = $from->addDays($i)->toDateString();

            return ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        })->all();
    }

    /** @return list<array{status: string, label: string, count: int}> */
    private function tripStatuses(User $user): array
    {
        $counts = Trip::query()->visibleTo($user)->toBase()
            ->selectRaw('trips.status AS status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        return collect(Trip::STATUSES)->map(fn (string $status) => [
            'status' => $status,
            'label' => Trip::statusLabel($status),
            'count' => (int) ($counts[$status] ?? 0),
        ])->all();
    }

    /**
     * How the main services share the trips (the pie): each service's trips and its percent of all.
     *
     * @return list<array{id: int, name: string, count: int, percent: float}>
     */
    private function servicesShare(User $user): array
    {
        $counts = Trip::query()->visibleTo($user)
            ->where('trips.status', '!=', Trip::STATUS_CANCELLED)
            ->toBase()
            ->selectRaw('trips.main_service_id AS service, COUNT(*) AS n')
            ->groupBy('service')
            ->pluck('n', 'service');

        $total = (int) $counts->sum();
        $names = MainService::query()->whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts->map(fn ($n, $id) => [
            'id' => (int) $id,
            'name' => (string) ($names[$id] ?? ''),
            'count' => (int) $n,
            'percent' => $total > 0 ? round($n * 100 / $total, 1) : 0.0,
        ])->sortByDesc('count')->values()->all();
    }

    /**
     * Drivers by account status (active / on vacation), vehicles and the two kinds of asset by their status
     * (active / on maintenance / deactivated) — within the viewer's scope.
     */
    private function resources(User $user): array
    {
        $byStatus = fn (Builder $query, string $column) => $query->toBase()->selectRaw("{$column} AS status, COUNT(*) AS n")->groupBy('status')->pluck('n', 'status');
        $assets = fn (string $type) => Asset::query()->visibleTo($user)->where('assets.asset_type', $type);
        $zero = fn (array $keys, $counts) => collect($keys)->mapWithKeys(fn ($k) => [$k => (int) ($counts[$k] ?? 0)])->all();

        $driverUsers = User::query()->whereIn('users.id', Driver::query()->visibleTo($user)->select('drivers.user_id'));
        $lifecycle = ['active', 'on_maintenance', 'deactivated'];

        return [
            'drivers' => $zero(['active', 'on_vacation', 'deactivated'], $byStatus($driverUsers, 'users.status')),
            'vehicles' => $zero($lifecycle, $byStatus(Vehicle::query()->visibleTo($user), 'vehicles.operational_status')),
            'containers' => $zero($lifecycle, $byStatus($assets('container'), 'assets.operational_status')),
            'tanks' => $zero($lifecycle, $byStatus($assets('tank'), 'assets.operational_status')),
        ];
    }

    /**
     * Active POs by their items status — the SQL twin of PurchaseOrderStatus (as the PO list's own filter does).
     *
     * @return array{valid: int, near_exhaustion: int, exhausted: int}
     */
    private function purchaseOrders(User $user): array
    {
        $orders = fn () => PurchaseOrder::query()->where('purchase_orders.operational_status', 'active')
            ->when(! $user->hasAnyRole(['system_admin', 'auditor']), fn ($q) => $q->whereIn('purchase_orders.project_id', Project::query()->visibleTo($user)->select('projects.id')));

        return collect(PurchaseOrderStatus::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => PurchaseOrderStatusSql::filter($orders(), $status)->count()])
            ->all();
    }
}
