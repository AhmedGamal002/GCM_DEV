# User Guide — System Admin

> Arabic version: [`system-admin.ar.md`](system-admin.ar.md) · Last updated: end of Week 3

The System Admin has full control **within a single company** (Tenant). They cannot manage the product itself or other companies (that is the separate Super Admin role).

**Hard rule:** there is **exactly one System Admin per company**. The system refuses to create a second one, and always refuses to deactivate a System Admin account (even by themselves).

---

## 1. Sign in

- URL: `/login`
- Email + password.
- There is no password change from the Profile for GCM staff and drivers (per the FRD) — if you forget yours use **Forgot Password** on the login page. The email is **immutable** after creation.

## 2. Dashboard

- Shown right after login; displays your name, role, and company name clearly ("System Admin — GCM").
- **Currently a shell only** — detailed stats arrive in Week 8.

## 3. Profile

- Account menu (top bar) → **My Profile**.
- **Change your profile photo and your password** (the "Security" tab — you enter your current password first). Name and email are read-only.

## 4. User management — `/app/user/list`

The only screen exclusive to the System Admin among tenant roles.

### Users list
- 7-column table: code / user / affiliation / entity / role / status / actions.
- **Pagination, sorting, and search are all server-side** — performs well even with thousands of users.
- Search matches by name, email, or code.
- Filters: affiliation (GCM / Client) + company + role (data_entry / auditor / client project manager / client project auditor / driver) + status (active / on vacation / deactivated). Client accounts show their company in the "Entity" column.
- **Export** button (Excel / PDF).

### "Add User" button — dropdown
- **GCM Staff** → standard user-creation form (Data Entry / Auditor roles).
- **Client Account (Project Manager / Auditor)** → the dedicated client-account form (`/app/client-user/add`) — see "Client accounts" below.
- **Driver** → sends you to the dedicated driver-creation page (`/app/driver/add`) — a different form.
- *(The Contractor-user option arrives with the Contractors module.)*

### Create GCM user
- Fields: name, email, phone, photo (optional), password, status, role (data_entry or auditor only — not system_admin or driver), additional data (optional).
- **You cannot create a second System Admin** — the option isn't offered, and a direct request is rejected.

### View / edit user
- Separate details and edit pages.
- **New password (optional):** on the edit page you can set a new password + confirmation for any user or driver — leave it blank to keep the current one. This is how you set someone else's password (every user can also change their own from the profile page).
- If the user is a driver, the edit page shows a notice pointing you to the driver-edit page (residence/license/insurance details are edited there).
- **Empty "Additional Data" does not render** as an empty heading on the details page.

### Client accounts — `/app/client-user/add` and `/app/client-user/edit/{id}`
A client account is a **Project Manager** or **Project Auditor** of one client company.
- **Create:** name, email, mobile, password (+ confirm), photo (optional), **company** (live-search list — **active** companies only), **role** (Project Manager / Project Auditor), **projects** — either **All projects** (every project of the company, including ones created later) or **Specific projects** (a checklist of the company's active projects; at least one), status, signature image and operational stamp image (both optional, PNG/JPG, max 2 MB), additional data.
- **Details** (`/app/user/view/{id}`): the company (link), the projects (links, or "All projects") and the signature/stamp images. The images are private — they load through a permission-checked URL, never a public link.
- **Edit:** every field except the email. A blank password keeps the current one; a file input left empty keeps the stored image. Changing the company clears the ticked projects (they belong to the old company). Status is changed from the edit form or the details page like any other account.
- The generic "Edit" form for GCM staff refuses a client account (it shows a link to the client form instead) — it would otherwise re-role the account.
- **Representatives:** an account can be picked as the **company representative** (Client Companies → Edit; project managers of that company only) or the **project representative** (Projects → Add/Edit; accounts that can see the project). If you later demote it, move it to another company, or remove the project from it, the representative field is cleared automatically.
- Client accounts currently sign in and reach **My Profile only** (photo, password, signature/stamp) — their trips/contracts/reports pages come with those modules.

### Change user status
- Active / on vacation / deactivated — from the details page.
- **Deactivating a System Admin is always blocked.**

## 5. Vehicle management — `/app/vehicle/list`

The System Admin has **full access**: view, create, edit, export, and all status changes (including deactivate and reactivate-from-deactivated).

### List
- Stat cards: available / on a trip (0 for now — arrives with the Trip module) / on maintenance / deactivated, plus per-category cards (**one card for every category your company actually has** — not a fixed number).
- Full server-side table: a vehicle's identity is its **plate** (no `code`). Search finds it even if you type the full plate with a space (`AAA 1234`).
- Filters: category / operational status / affiliation. Export button (Excel / PDF).
- **Add Vehicle** button.

### Create / edit vehicle
- Basics: plate (letters + digits, digits only), category, affiliation, whether it has an embedded container (if yes: its type + capacity — the list filters by what is available in the Asset Pool).
- Photos (front/back) + documents (registration card / fitness / inspection certificate / insurance) with numbers and expiry dates.
- Repeatable entry permits (area / number / expiry / attachment).
- **All document and permit numbers are digits only.**

### Vehicle categories — Vehicles ← Categories (`/app/vehicle-category/list`) — System Admin only
- Your company starts with the 6 default categories, and you can **add new ones** and rename any of them (an English name + an Arabic name, each unique within your company).
- **Deleting is blocked while a category is in use** by vehicles, by drivers (qualified types) or by assets (compatible category) — the delete icon is disabled and the message says what uses it (e.g. "3 vehicles, 1 asset"). The six **primary** categories (the five FRD ones plus Tractor truck) can be renamed but **never deleted**, even when unused (their delete icon is always disabled). Only a category you added yourself, and only while unused, can be deleted.
- A new category automatically becomes a card on the Vehicles page, an option in the category filter, and an option in the vehicle, driver and asset forms. Renaming changes it everywhere (vehicles linked to it stay linked).
- **Export** (Excel / PDF) button on the list — the file holds the rows matching the search box.
- A "Manage categories" link above the category cards on the Vehicles page takes you to the same screen.

### Vehicle status
- **System Admin**: "on maintenance" + **deactivate** + **reactivate from deactivated**.

## 6. Driver management — `/app/driver/list`

System Admin only. **Drivers are created and edited exclusively here** — not from the user form.

### List
- 4 stat cards: available / on trips (0 for now) / on vacation / deactivated.
- 5-column table: code / driver name / affiliation / driver availability / details.
- Filters: affiliation (GCM / contractor) + driver availability. Export button.
- **Add new driver user** button.

### Create driver (single page, creates everything at once)
1. **Account details:** name, email, phone, photo, password, status, additional data.
2. **Default vehicle:** pick the vehicle type(s) the driver is qualified to drive (from the vehicle categories you have) + the default vehicle itself (the list filters by the selected types; the vehicle must belong to one of them).
   - **One vehicle per one driver** as a default — vehicles already taken don't appear in the list at all.
3. **Residence / driving license / operational license / insurance:** number + expiry + attachment for each.
4. **Truck entry permits:** repeatable (area / number / expiry / attachment) — via "Add Permit".

> **Driver documents (residence/licenses/insurance/permits) are stored privately** — served only through a protected link, no public URL.

### View / edit driver
- Details page with a card per document + "Default Vehicle".
- Edit page with the same sections.

## 7. Asset management — `/app/asset/list`

System Admin: view, create, edit (full data), export, and all status changes.

### List
- **Separate stat cards for containers and tanks**: available / in projects / on maintenance / deactivated.
- Server-side table. Filters: type (container / tank) + status (active = available, **in a project**, on maintenance, deactivated) + affiliation. The "Availability" column shows "In a project" + the project name. Export button + an **Insert asset into project** button.
- Two items under "Assets" in the sidebar: **List** + **Categories**.

### Create asset
- Name, type (container / tank), capacity category (filtered by type), compatible vehicle categories (checkboxes, per-asset), status, affiliation, purchase date, additional data.

### Edit asset
- **Name only** is editable — capacity and category are locked after creation (per FRD: they affect contracts and trips).

### Insert an asset into a project — `/app/asset/insert-into-project`
- From the "Insert asset into project" button on the assets list (or from a project's details page — the company and project are then filled in for you).
- Pick the **company** → the **project** (that company's active projects only) → the **type** (container / tank) → the **asset** (**available** assets only: active and not already in a project). All lists are live-search.
- After saving, the asset leaves the available pool and shows as "In a project" + the project name on the list, details and stat cards, and in the project's assets table. An asset is in one project only. **Taking an asset back out of a project is not defined by the FRD yet** (it comes with trips).

### Asset capacity categories — `/app/asset-category/list`
- Create and edit. After creation, **name only** is editable (capacity and kind locked). No delete, no deactivate.
- The list's eye icon opens a **details page** (a client add-on, not in the FRD): the category's own data plus a table of every asset carrying it, linking to each asset's own details page.

## 8. Change asset status
- **System Admin**: "on maintenance" + **deactivate** + **reactivate from deactivated**.

## 9. Intermediate facilities — `/app/facility/list`

System Admin: view, create, edit, deactivate / reactivate, export. Facilities are never deleted.

### List
- One stat card per environmental service (safe disposal / sewage treatment / recycling): how many facilities offer it, and how many of those are active.
- Server-side table: name (with its prefix), environmental service (recycling shows its efficiency %), status, details. Search by name or prefix; filters: service + status; Export (Excel / PDF) — the file holds exactly the rows on screen.
- In the sidebar: **Operations ← Facilities**.

### Create facility
- Name, **prefix** (3 English letters, unique within your company — locked after creation), logo (up to 2 MB), **environmental service** (exactly one: safe disposal / sewage treatment / recycling), **recycling efficiency %** (appears — and is required — for recycling only), status, address, map link, contract (number, start / end dates, attachment up to 4 MB), additional data.
- Saving generates the facility's **ID** — the prefix plus a running number per company (`ALF-0001`, then `ALF-0002`...), same as Client Companies — shown in the list and the details page. Nothing can change it.

### Details, edit and status
- Details show everything, the contract attachment download, and "Supported sub-services" (empty until the Services module exists). A **Facility Status** card deactivates / reactivates the facility (confirm checkbox).
- Edit: the name and all optional data (the logo and contract attachment can be replaced). The **prefix is locked**. The environmental service and recycling efficiency can be changed **only while nothing uses the facility** — they lock once a sub-service or trip uses it.

## 10. Client company management — `/app/company/list`

Sidebar: heading **Clients & Projects** → **Client Companies** (List + Add).

### List
- Server-side table: ID, Company, Company representative (the name, or `—`), Projects and Users (the real counts), Status, Actions. Search by name, short name or ID + a status filter + Excel/PDF export honouring the same filters.

### Create a client company
- **Required:** name, short name (3 unique English letters — shown in upper case), status (active by default).
- **The ID is generated automatically = the short name + a running number** (`ALN-0001`, then `GPC-0002`…). That is why **the short name is locked after creation**, and the numbers of the company's projects, contracts and trips (once built) will be made from it.
- **Optional:** business sector, logo, phone, email, address, map link (`http`/`https` only), contract number + start/end dates + contract copy, commercial registration (number + copy), tax registration (number + copy), additional data. Numbers are digits only; attachments are PDF or images up to 4 MB (logo 2 MB). The **client representative account** is chosen on the **edit** page (one of the company's active project managers) — on the create page the field is shown but locked — a company being created has no accounts yet.

### Details and edit
- Details page: the data + download links for the attachments + statistics (projects — the real count; contracts, trips and waste moved are `0` for now) + a **company projects** table (search + status filter + export + an "Add Project" button that opens the form with the company selected).
- Edit page: **every field** is editable except the short name (locked; the ID is shown above the form). A file input left empty keeps the stored file; a new file replaces it.
- **Deactivate / reactivate** from the "Company Status" card on the edit page (with a confirmation). There is no delete. Deactivating a company does **not** deactivate anything else.

## 11. Client project management — `/app/project/list`

Sidebar: heading **Clients & Projects** → **Client Projects** (List + Add). A project is a work site of a client company (the place trips leave from).

### List
- Server-side table: ID, Company, Project, Contracts (`0` until that module exists) and Users (client accounts that can see the project), Status, Actions. Search by name, ID or company name + a company filter + a status filter + Excel/PDF export honouring the same filters.

### Create a project
- **Required:** name, **client company** (live search — **active** companies only), status (active by default).
- **The ID is generated automatically = the company's short name + `P` + a running number per company** (`ALN-P0001`, `ALN-P0002`…). That is why **the company is locked after creation**.
- **Optional:** operational region, phone, email, address, map link (`http`/`https` only), additional data, and the **project representative account** (an active client account that can see the project; on create only the chosen company's "all projects" accounts are listed).

### Details and edit
- Details page: the data + statistics (contracts, trips, waste — `0` for now) + the contracts section (not built yet) + a table of the **assets in the project** (type filter + export + an insert-asset button).
- Edit page: every field except the company (locked; the ID is shown above the form).
- **Deactivate / reactivate** from the "Project Status" card on the edit page (with a confirmation). There is no delete. Deactivating a project does **not** deactivate the company, and deactivating a company does **not** deactivate its projects; a deactivated project is not offered when inserting an asset.

## 12. Features available now

| Module | Available | Deferred |
|---|---|---|
| Users | Full CRUD + status + export + **client accounts** (company, all/specific projects, signature & stamp) | "Contractor user" option in the Add button (with the Contractors module) |
| Vehicles | Full CRUD + status + export + embedded container | "on a trip" column/counter + vehicle trip log (Week 7) |
| Drivers | Full CRUD + default vehicle + documents + permits | "on a trip" status (Week 7), mobile app |
| Assets | Full CRUD + categories + status + export + **insert an asset into a project** + "in a project" availability | Taking an asset out of a project (with trips) |
| Intermediate facilities | Create / edit / deactivate + prefix + recycling efficiency + contract + export | Sub-service list on the details page and the "in use" lock (with the Services module) |
| Client companies | Full CRUD + deactivate/reactivate + attachments + export + projects count + company projects table + **representative + users count** | The contracts section (with Contracts) |
| Client projects | Full CRUD + deactivate/reactivate + ID from the company short name + project assets + export + **representative + users count** | Contracts section and trip statistics |
| Dashboard | Shell screen | Real stats (Week 8) |
