# User Guide — Auditor

> Arabic version: [`auditor.ar.md`](auditor.ar.md) · Last updated: FRD V01.14 review

The Auditor is a **read-only** role over every page in the system: view and export, no creating, editing, or status changes. The one exception: changing their **own password** from the profile page.

---

## What you see in the sidebar

Dashboard, then an **Accounts** section (Users · Drivers), a **Fleet & Assets** section (Vehicles · Assets, with Categories), an **Operations** section (Facilities) and a **Clients & Projects** section (Client Companies). Any other link is hidden — manual access returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile**: change your profile photo, **and your password too** (the "Security" tab — you enter your current password first). Name and email stay read-only.

## 2. Users — `/app/user/list`

- **View** the list of GCM staff accounts + **export** (Excel / PDF).
- **View details** of any account.
- **Not allowed:** the "Add User" button won't open a working form, and there are no edit or status-change controls.

## 3. Drivers — `/app/driver/list`

- **View** the full list + stat cards + filters + **export**.
- **View details** of any driver (their data, documents, permits).
- **Not allowed:** any create or edit.

## 4. Vehicles — `/app/vehicle/list`

- **View** the full list + stat cards + all filters (category / status / affiliation) + search.
- **View details** of any vehicle (its data, photos, documents, permits).
- **Export** the list (Excel / PDF) — with the applied filters.
- **Not allowed:** the "Add Vehicle" button isn't present, and there are no edit or status-change buttons.

## 5. Assets — `/app/asset/list`

- **View** the list + stat cards (containers and tanks) + filters + **export**.
- **View details** of any asset.
- **Asset capacity categories** (`/app/asset-category/list`): view and export only — no create, no edit.
- **Not allowed:** any create, edit, or status change.

## 6. Intermediate facilities — `/app/facility/list`

- **View** the list + stat cards + search + filters + **export**, and the **details** of any facility (including the contract attachment download).
- **Not allowed:** the "Create new facility" button, the edit icon and the status card aren't shown, and any create / edit / status change returns 403.

## 7. Client companies — `/app/company/list`

- **View** the list + filter + **export**, and the **details** of any company (and download its attachments).
- **Not allowed:** creating, editing or deactivating (the "Add" link is not shown in the sidebar).

## 8. What you cannot do (returns 403)

- Create, edit, or deactivate any user / driver / vehicle / asset / facility / company / category.
- Change your name or email on the profile page.
- Manage roles & permissions / tenants.

## 9. Features available now

Full view + export across every built module (Users, Drivers, Vehicles, Assets, Intermediate facilities, Client companies, and categories) — was limited to vehicles and assets only before the FRD V01.14 upgrade. Self-service password change works from the profile page.
