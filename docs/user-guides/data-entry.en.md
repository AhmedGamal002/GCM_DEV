# User Guide — Data Entry

> Arabic version: [`data-entry.ar.md`](data-entry.ar.md) · Last updated: FRD V01.14 review

Data Entry is responsible for **creating, editing, and deactivating users, drivers, vehicles, and asset data** — near admin-parity across all of it (see the one exception in section 6).

---

## What you see in the sidebar

Dashboard, then an **Accounts** section (Users · Drivers), a **Fleet & Assets** section (Vehicles · Assets — with a Capacities sub-item under Assets), an **Operations** section (Facilities · Services · Contractors) and a **Clients & Projects** section (Client Companies). Any other link (Roles & Permissions, and the demo template scaffold) is **hidden** — and if you open it manually it returns "You don't have permission to view this data."

## 1. Sign in and profile

- `/login` with email + password.
- **My Profile** from the top bar: change your profile photo **and your password** (the "Security" tab — you enter your current password first). You can also upload your own **signature image and operational stamp image** (both optional, PNG/JPG, max 2 MB — private files). Name and email are read-only. If you forget your password use "Forgot Password" on the login page.

## 2. Users — `/app/user/list`

- **You do not see the System Admin account** — not in the list, search, export, or by direct link (403).
- **View** the list of GCM staff accounts (not drivers — they have their own page) + **export** (Excel / PDF).
- **Create** a new account (data_entry or auditor — there is no "system_admin" option here at all; optional signature + stamp images like the other accounts), or a **client account** (Project Manager / Auditor of a client company, with all or specific projects + signature/stamp images), or a **contractor user** (of an active contractor) from the "Add User" menu.
- **Edit** any account (except drivers; client accounts are edited on their own client-account form, which the Edit button opens) — and set a **new password** (optional) from the edit page. **You cannot edit the System Admin's account** or change its status (returns 403).
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
- **Edit** an asset: **name and additional data** — everything else is locked after creation.
- **Status:** "on maintenance", or **deactivate / reactivate**.
- **Insert an asset into a project** (`/app/asset/insert-into-project`): company → active project → type → available asset. The asset then shows as "In a project" + the project name.

### Assets capacities — `/app/asset-category/list`
- **Create** a new capacity.
- **Edit:** name and additional data (capacity and kind locked).
- No delete, no deactivate.
- The list's eye icon opens a **details page** (client add-on): the capacity's data plus a table of the assets carrying it.

## 6. Intermediate facilities — `/app/facility/list`

- **View** + stat cards (one per environmental service) + search (name / prefix) + filters + **export**.
- **Create** a facility: name, prefix (3 English letters, unique, locked after creation), logo, environmental service (recycling also needs the recycling efficiency %), status, address, map link, contract (number, dates, attachment), additional data.
- Saving generates the facility's **ID** (prefix + running number, `ALF-0001`...) — same as Client Companies.
- **Edit:** the name and the optional data only (logo, address, contract number — **digits only** — dates, attachment, additional data). The prefix, the environmental service and the recycling efficiency are locked after creation.
- **Status:** deactivate / reactivate from the details page. Facilities are never deleted.

## 7. Services — `/app/main-service/list` and `/app/sub-service/list`

- Exactly the System Admin's permissions (see the System Admin guide — Services).
- **Main services:** view + search (which also covers sub-service names) + filters + **export**; create (name, type of service, supported vehicle categories); edit (the name and the additional data only; the type and the categories are locked); deactivate / reactivate from the edit page — **deactivating also deactivates every sub-service under it, and reactivating brings those back** (ones that were off on their own before stay off).
- **Sub-services:** the accordion page; create (main service, type, capacities, facilities — optional only for supplying, recycle receipt, skip colour); edit (the name, the skip colour and the additional data only; the rest is locked); deactivate / reactivate (not while its main service is deactivated).

## 8. Contractors — `/app/contractor/list`

- Exactly the System Admin's permissions (see the System Admin guide — Contractor management).
- **View** the list (with the four statistic cards) + search + status filter + **export**, and the **details** of any contractor (and download its attachments).
- **Create** a contractor (name + short name are required; the ID is built as `ABC-C0001`) — including creating it deactivated — with its **certified services** (none ticked = all), contract and registration data.
- **Edit** every field except the short name (locked after creation).
- **Status:** **deactivate and reactivate** from the edit page; nothing cascades to the contractor's drivers, vehicles or assets. There is no delete.
- The driver, vehicle and asset forms offer **Belongs to a Contractor** with a list of the active contractors.

## 9. Client companies — `/app/company/list`

- **View** the list + filter + **export**, and the **details** of any company (and download its attachments).
- **Create** a client company (name + short name are required) — including creating it deactivated. From its edit page you can also pick the **client representative** (one of its active project managers); projects have a **project representative** picker too.
- **Edit** any company data (every field except the short name — it is locked after creation because the company ID is built from it).
- **Status:** **deactivate and reactivate** from the edit page. There is no delete.

## 10. Client projects — `/app/project/list`

- **View** the list (search + company and status filters) + **export**, and the **details** of any project (and its assets table).
- **Create** a project (name + an active client company are required) — its ID is built from the company's short name (`ALN-P0001`).
- **Edit** any data except the company (locked after creation).
- **Status:** **deactivate and reactivate** from the edit page. There is no delete.

## 11. Trips — `/app/trip/list`

Sidebar: **Trips** → List + Add.

- **List:** five counters (awaiting client approval / approved / rejected / completed / cancelled), a server-side table (newest first) with the trip number, date, driver and vehicle with their affiliation, client, project, sub-service, status and PO link; filters (time range, status, client → project, driver, vehicle, affiliations, main → sub-service), search, and **Excel / PDF export** that matches the screen.
- **Create a trip (`/app/trip/add`):** pick in order driver → vehicle → client → project → main service → sub-service → facility; each list offers only what fits (active driver, vehicle within the driver's categories, service within the vehicle's category, containers that are free and fit). A vehicle with a built-in container or tank takes no external containers. The location is a map link or `lat, lng` and the photo a normal upload (no GPS or camera for staff). The PO quota is deducted when the trip is saved; with no PO the trip goes without one and the cost is typed. Choosing **No** for client approval records a completed trip directly (client and GCM signatures required, no code).
- **Edit:** changing date / project / service re-deducts the quota and recomputes the cost; an approved or completed trip keeps its status. Every change is written to the trip history.
- **Final approval, accepting / rejecting the client's objection, cancelling** (cancel gives the quota and containers back) and **reminding the client** are on the details page. Final approval, rejecting the objection and cancelling ask for a **verification code** sent to your email.
- **Trip documents:** a completed trip shows its Delivery Note, Waste Manifest (not for supply trips) and the combined PDF.

## 12. What you cannot do (returns 403 or 422)

| | |
|---|---|
| Create a "system_admin" account | ✖ — that option doesn't exist in the form |
| Edit / deactivate / reactivate the **System Admin's own account** | ✖ — nobody can touch it |
| Anything related to Contracts (PO) | ✖ — no Purchase Orders menu, the "Contracts" section and card are hidden on the company and project pages (the list column shows `—`), the export column is blank, and `/app/po/*` and `/api/v1/purchase-orders` return 403 — the FRD gives Data Entry no access to POs, not even to read them |
| Roles & permissions / Companies management | ✖ |

## 13. Features available now

Users and Drivers: view/create/edit + every account status (on vacation/deactivate/reactivate) except the System Admin's own account.
Vehicles, Assets, Intermediate facilities and Services (main and sub): full CRUD including deactivate/reactivate + export. The embedded container in the vehicle form works.
Contractors, client companies and client projects: full CRUD including deactivate/reactivate + export. Inserting an asset into a project works.
Purchase orders are not visible to Data Entry at all (the FRD keeps them with the System Admin; the Auditor may view them).
Deferred: "on a trip" column for vehicles (Week 7), taking an asset out of a project (with trips).
