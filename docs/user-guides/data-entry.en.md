# User Guide — Data Entry

> Arabic version: [`data-entry.ar.md`](data-entry.ar.md) · Last updated: FRD V01.14 review

Data Entry is responsible for **creating, editing, and deactivating users, drivers, vehicles, and asset data** — near admin-parity across all of it (see the one exception in section 6).

---

## What you see in the sidebar

Dashboard, then an **Accounts** section (Users · Drivers), a **Fleet & Assets** section (Vehicles · Assets — with a Categories sub-item under Assets), an **Operations** section (Facilities) and a **Clients & Projects** section (Client Companies). Any other link (Roles & Permissions, and the demo template scaffold) is **hidden** — and if you open it manually it returns "You don't have permission to view this data."

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

## 6. Intermediate facilities — `/app/facility/list`

- **View** + stat cards (one per environmental service) + search (name / prefix) + filters + **export**.
- **Create** a facility: name, prefix (3 English letters, unique, locked after creation), logo, environmental service (recycling also needs the recycling efficiency %), status, address, map link, contract (number, dates, attachment), additional data.
- **Edit:** the name and all optional data. The environmental service and recycling efficiency change only while no sub-service or trip uses the facility.
- **Status:** deactivate / reactivate from the details page. Facilities are never deleted.

## 7. Client companies — `/app/company/list`

- **View** the list + filter + **export**, and the **details** of any company (and download its attachments).
- **Create** a client company (name + short name are required) — including creating it deactivated.
- **Edit** any company data (every field except the short name — it is locked after creation because the company ID is built from it).
- **Status:** **deactivate and reactivate** from the edit page. There is no delete.

## 8. What you cannot do (returns 403 or 422)

| | |
|---|---|
| Create a "system_admin" account | ✖ — that option doesn't exist in the form |
| Edit / deactivate / reactivate the **System Admin's own account** | ✖ — nobody can touch it |
| Anything related to Contracts (PO) | ✖ — you don't see that section at all once it's built |
| Roles & permissions / Companies management | ✖ |

## 9. Features available now

Users and Drivers: view/create/edit + every account status (on vacation/deactivate/reactivate) except the System Admin's own account.
Vehicles, Assets and Intermediate facilities: full CRUD including deactivate/reactivate + export. The embedded container in the vehicle form works.
Client companies: full CRUD including deactivate/reactivate + export.
Deferred: "Add asset to a project" (Week 4), "on a trip" column for vehicles (Week 7).
