# User Guide — Auditor

> Arabic version: [`auditor.ar.md`](auditor.ar.md) · Last updated: end of Week 3

The Auditor is a **read-only** role over vehicles and assets: view and export, no editing. They do not see users or drivers.

---

## What you see in the sidebar

3 items: **Dashboard** · **Vehicles** · **Assets** (with Categories). Any other link is hidden — manual access returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile**: change your profile photo only. Name, password and everything else are managed by the administration (System Admin / Data Entry).

## 2. Vehicles — `/app/vehicle/list`

- **View** the full list + stat cards + all filters (category / status / affiliation) + search.
- **View details** of any vehicle (its data, photos, documents, permits).
- **Export** the list (Excel / PDF) — with the applied filters.
- **Not allowed:** the "Add Vehicle" button isn't present, and there are no edit or status-change buttons.

## 3. Assets — `/app/asset/list`

- **View** the list + stat cards (containers and tanks) + filters + **export**.
- **View details** of any asset.
- **Asset capacity categories** (`/app/asset-category/list`): view and export only — no create, no edit.
- **Not allowed:** any create, edit, or status change.

## 4. What you cannot do (returns 403)

- Create, edit, or deactivate any vehicle / asset / category.
- Manage users or drivers (everything).
- Manage roles & permissions / companies.

## 5. Features available now (end of Week 3)

Full view + export for vehicles, assets, and categories. Anything beyond view/export is entirely outside this role's scope.
