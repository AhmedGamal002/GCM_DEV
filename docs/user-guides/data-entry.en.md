# User Guide — Data Entry

> Arabic version: [`data-entry.ar.md`](data-entry.ar.md) · Last updated: during Week 3 (FRD gap review)

Data Entry is responsible for **creating and editing users, drivers, vehicles, and asset data**. Final deactivation and reactivation from deactivated is System Admin only, across every module.

---

## What you see in the sidebar

5 items: **Dashboard** · **Users** · **Drivers** · **Vehicles** · **Assets** (with its Categories sub-item). Any other link (Roles & Permissions, and the demo template scaffold) is **hidden** — and if you open it manually it returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile** from the top bar: change your profile photo only. Name and password are managed by the administration — if you forget your password use "Forgot Password" on the login page.

## 2. Users — `/app/user/list`

- **View** the list of GCM staff accounts (not drivers — they have their own page) + **export** (Excel / PDF).
- **Create** a new account (data_entry or auditor — there is no "system_admin" option here at all).
- **Edit** any account (except drivers) — and set a **new password** (optional) from the edit page. **You cannot edit the System Admin's account** or change its status (returns 403).
- **Status:** you can move an account to **"on vacation"** only.
  - **You cannot deactivate an account** or reactivate a deactivated one — that is System Admin only.

## 3. Drivers — `/app/driver/list`

- **View** the full list + stat cards + filters (including GCM/contractor affiliation) + **export**.
- **Create** a new driver (the full form: account basics + default vehicle + residence/license/operational license/insurance + optional entry permits).
- **Edit** any driver's data — including a **new password** (optional) from the edit page.
- **Status:** same as Users — "on vacation" only, deactivation is System Admin only.

## 4. Vehicles — `/app/vehicle/list`

- **View** the full list + stat cards + filters + **export** (Excel / PDF).
- **Create** a new vehicle (the full form: basics + photos + documents + entry permits).
- **Edit** any existing vehicle.
- **Status:** you can move a vehicle to **"on maintenance"** only.
  - **You cannot deactivate a vehicle** or reactivate a deactivated one — that is System Admin only.
- **You cannot create a vehicle directly in "deactivated" status** — creation is always active.

## 5. Assets — `/app/asset/list`

- **View** + stat cards (containers and tanks separately) + filters + **export**.
- **Create** a new asset (name, type, capacity category, compatible vehicle categories, purchase date, ...).
- **Edit** an asset: **name only** — everything else is locked after creation.
- **Status:** "on maintenance" only (deactivate/reactivate is System Admin only).

### Asset capacity categories — `/app/asset-category/list`
- **Create** a new category.
- **Edit:** name only (capacity and kind locked).
- No delete, no deactivate.

## 6. What you cannot do (returns 403 or 422)

| | |
|---|---|
| Create a "system_admin" account | ✖ — that option doesn't exist in the form |
| Deactivate / reactivate a user or driver | ✖ (System Admin only) |
| Deactivate / reactivate a vehicle or asset | ✖ (System Admin only) |
| Create a vehicle/asset in "deactivated" status | ✖ |
| Roles & permissions / Companies management | ✖ |

## 7. Features available now

Users and Drivers: view/create/edit + "on vacation" status (deactivation is System Admin only).
Vehicles and Assets: full CRUD (except deactivation) + "on maintenance" status + export. The embedded container in the vehicle form works.
Deferred: "Add asset to a project" (Week 4), "on a trip" column for vehicles (Week 7).
