# User Guide — Data Entry

> Arabic version: [`data-entry.ar.md`](data-entry.ar.md) · Last updated: FRD V01.14 review

Data Entry is responsible for **creating, editing, and deactivating users, drivers, vehicles, and asset data** — near admin-parity across all of it (see the one exception in section 6).

---

## What you see in the sidebar

Dashboard, then an **Accounts** section (Users · Drivers) and a **Fleet & Assets** section (Vehicles · Assets — with a Categories sub-item under Assets). Any other link (Roles & Permissions, and the demo template scaffold) is **hidden** — and if you open it manually it returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile** from the top bar: change your profile photo **and your password** (the "Security" tab — you enter your current password first). Name and email are read-only. If you forget your password use "Forgot Password" on the login page.

## 2. Users — `/app/user/list`

- **View** the list of GCM staff accounts (not drivers — they have their own page) + **export** (Excel / PDF).
- **Create** a new account (data_entry or auditor — there is no "system_admin" option here at all).
- **Edit** any account (except drivers) — and set a **new password** (optional) from the edit page. **You cannot edit the System Admin's account** or change its status (returns 403).
- **Status:** you can move an account to **"on vacation"**, or **deactivate / reactivate it**.
  - **The one exception:** the **System Admin's own account** — nobody can touch it, System Admin included.

## 3. Drivers — `/app/driver/list`

- **View** the full list + stat cards + filters (including GCM/contractor affiliation) + **export**.
- **Create** a new driver (the full form: account basics + default vehicle + residence/license/operational license/insurance + optional entry permits).
- **Edit** any driver's data — including a **new password** (optional) from the edit page.
- **Status:** same as Users — "on vacation", or deactivate/reactivate.

## 4. Vehicles — `/app/vehicle/list`

> Vehicle categories are managed by the **System Admin only** (Vehicles ← Categories is not shown to you), but categories appear normally in the filter and the vehicle form.

- **View** the full list + stat cards + filters + **export** (Excel / PDF).
- **Create** a new vehicle (the full form: basics + photos + documents + entry permits) — including directly in "deactivated" status if needed.
- **Edit** any existing vehicle.
- **Status:** you can move a vehicle to **"on maintenance"**, or **deactivate / reactivate it**.

## 5. Assets — `/app/asset/list`

- **View** + stat cards (containers and tanks separately) + filters + **export**.
- **Create** a new asset (name, type, capacity category, compatible vehicle categories, purchase date, ...) — including directly in "deactivated" status if needed.
- **Edit** an asset: **name only** — everything else is locked after creation.
- **Status:** "on maintenance", or **deactivate / reactivate**.

### Asset capacity categories — `/app/asset-category/list`
- **Create** a new category.
- **Edit:** name only (capacity and kind locked).
- No delete, no deactivate.

## 6. What you cannot do (returns 403 or 422)

| | |
|---|---|
| Create a "system_admin" account | ✖ — that option doesn't exist in the form |
| Edit / deactivate / reactivate the **System Admin's own account** | ✖ — nobody can touch it |
| Anything related to Contracts (PO) | ✖ — you don't see that section at all once it's built |
| Roles & permissions / Companies management | ✖ |

## 7. Features available now

Users and Drivers: view/create/edit + every account status (on vacation/deactivate/reactivate) except the System Admin's own account.
Vehicles and Assets: full CRUD including deactivate/reactivate + export. The embedded container in the vehicle form works.
Deferred: "Add asset to a project" (Week 4), "on a trip" column for vehicles (Week 7).
