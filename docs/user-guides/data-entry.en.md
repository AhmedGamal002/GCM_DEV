# User Guide — Data Entry

> Arabic version: [`data-entry.ar.md`](data-entry.ar.md) · Last updated: end of Week 3

Data Entry is responsible for **creating and editing vehicle and asset data**. They do not see users or drivers at all.

---

## What you see in the sidebar

Only 3 items: **Dashboard** · **Vehicles** · **Assets** (with its Categories sub-item). Any other link (Users, Drivers, and the demo template scaffold) is **hidden** — and if you open it manually it returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile** from the top bar: edit name + change password. Email is immutable.

## 2. Vehicles — `/app/vehicle/list`

- **View** the full list + stat cards + filters + **export** (Excel / PDF).
- **Create** a new vehicle (the full form: basics + photos + documents + entry permits).
- **Edit** any existing vehicle.
- **Status:** you can move a vehicle to **"on maintenance"** only.
  - **You cannot deactivate a vehicle** or reactivate a deactivated one — that is System Admin only.
- **You cannot create a vehicle directly in "deactivated" status** — creation is always active.

## 3. Assets — `/app/asset/list`

- **View** + stat cards (containers and tanks separately) + filters + **export**.
- **Create** a new asset (name, type, capacity category, compatible vehicle categories, purchase date, ...).
- **Edit** an asset: **name only** — everything else is locked after creation.
- **Status:** "on maintenance" only (deactivate/reactivate is System Admin only).

### Asset capacity categories — `/app/asset-category/list`
- **Create** a new category.
- **Edit:** name only (capacity and kind locked).
- No delete, no deactivate.

## 4. What you cannot do (returns 403)

| | |
|---|---|
| User management | ✖ everything |
| Driver management | ✖ everything |
| Deactivate / reactivate a vehicle or asset | ✖ (System Admin only) |
| Create a vehicle/asset in "deactivated" status | ✖ |
| Roles & permissions / Companies management | ✖ |

## 5. Features available now (end of Week 3)

Vehicles and Assets: full CRUD (except deactivation) + "on maintenance" status + export. The embedded container in the vehicle form works.
Deferred: "Add asset to a project" (Week 4), "on a trip" column for vehicles (Week 7).
