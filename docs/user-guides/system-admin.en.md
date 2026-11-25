# User Guide — System Admin

> Arabic version: [`system-admin.ar.md`](system-admin.ar.md) · Last updated: end of Week 3

The System Admin has full control **within a single company** (Tenant). They cannot manage the product itself or other companies (that is the separate Super Admin role).

**Hard rule:** there is **exactly one System Admin per company**. The system refuses to create a second one, and always refuses to deactivate a System Admin account (even by themselves).

---

## 1. Sign in

- URL: `/login`
- Email + password.
- There is no password change from the Profile for GCM staff and drivers (per the FRD) — if you forget yours use **Forgot Password** on the login page. Only the System Admin / Data Entry change an account's email (from its edit page).

## 2. Dashboard

- Shown right after login; displays your name, role, and company name clearly ("System Admin — GCM").
- **Currently a shell only** — detailed stats arrive in Week 8.

## 3. Profile

- Account menu (top bar) → **My Profile**.
- **Change your profile photo and your password** (the "Security" tab — you enter your current password first). **You can edit your own name and email** in the same profile page (the email is what you sign in with, and it must not belong to another account).

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
- **Client Account** → the dedicated client-account form (`/app/client-user/add`) — see "Client accounts" below.
- **Driver** → sends you to the dedicated driver-creation page (`/app/driver/add`) — a different form.
- **Contractor User** → the dedicated contractor-user form (`/app/contractor-user/add`) — see "Contractor users" below.

### Create GCM user
- Fields: name, email, phone, photo (optional), password, status, role (data_entry or auditor only — not system_admin or driver), **signature image and operational stamp image (both optional, PNG/JPG, max 2 MB — private files, shown on the account's details page)**, additional data (optional).
- The same two images can be changed later from the account's edit page (leave one empty to keep it), and each staff member — you included — can change their own from **My Profile**.
- **You cannot create a second System Admin** — the option isn't offered, and a direct request is rejected.

### View / edit user
- **The System Admin can change every field of every account — the email included, and its own account too** (its row is not shown in the Users list — the list shows the other accounts only; your own photo and password are on "My Profile"). The admin's role is fixed and the admin account can never be deactivated. The email must be unique across the whole system.
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

### Contractor users — `/app/contractor-user/add` and `/app/contractor-user/edit/{id}`
A contractor user is an account of one contractor company (the role "Contractor User" is fixed — there is no role picker).
- **Create:** name, email, mobile, password (+ confirm), photo (optional), **contractor** (live-search list — **active** contractors only), account status, additional data.
- **Details** (`/app/user/view/{id}`): the role "Contractor User", the affiliation "Contractor" and the contractor's name as a link to its page.
- **Edit:** every field (the email and the contractor too). A blank password keeps the current one; a photo input left empty keeps the stored one. The current contractor stays shown even if it is deactivated later, but you cannot move the user to a deactivated contractor. Status is changed from the edit form or the details page like any other account.
- The generic "Edit" form for GCM staff refuses a contractor user (it shows a link to its own form) — it would otherwise re-role the account.
- **Contractor representative:** on **Contractors → Edit** pick the "Contractor representative account" (optional; **active** users of that same contractor only). On create the field is shown locked because a new contractor has no users yet. If you move the user to another contractor, the representative field of the old contractor is cleared automatically.
- Contractor users currently sign in and reach **My Profile only** (photo, password) — the pages listing their contractor's drivers, vehicles, containers and trips (view/export) come with the Trips module.

### Change user status
- **Nobody changes their own status** — not the admin, not Data Entry, not anyone: no "on vacation", no deactivate, no reactivate. The status controls are hidden on your own account's pages and the server refuses the request (403). Ask another account holder who may manage accounts.
- A **deactivated** account is signed out on its very next request (web session or mobile token) and lands on the login page with the deactivation message.
- An account **on vacation** sees a warning banner on every page, and every create/edit control (buttons, links, forms) is disabled up front; the server also refuses any write (403). It can still view, search and filter, **but it cannot export** (the Export button is locked and the server refuses exports).
- Active / on vacation / deactivated — from the details page.
- **Deactivating a System Admin is always blocked.**

## 5. Vehicle management — `/app/vehicle/list`

The System Admin has **full access**: view, create, edit, export, and all status changes (including deactivate and reactivate-from-deactivated).

### List
- Stat cards: available / on maintenance / deactivated, plus per-category cards (**one card for every category your company actually has** — not a fixed number).
- Full server-side table: a vehicle's identity is its **plate** (no `code`). Search finds it even if you type the full plate with a space (`AAA 1234`).
- Filters: category / operational status / affiliation. Export button (Excel / PDF).
- **Add Vehicle** button.

### Create / edit vehicle
- Basics: plate (letters + digits, digits only), category, affiliation, whether it has an embedded container (if yes: its type + capacity — the list shows the capacities defined for that type: "container" lists container capacities, "tank" lists tank capacities, and a capacity defined for both shows under either). Picking the category pre-fills these from the category's default embedded container (see "Vehicle categories"); you can still change them.
- Photos (front/back) + documents (registration card / fitness / inspection certificate / insurance) with numbers and expiry dates.
- Repeatable entry permits (area / number / expiry / attachment).
- **All document and permit numbers are digits only.**

### Vehicle categories — Vehicles ← Categories (`/app/vehicle-category/list`) — System Admin only
- Your company starts with the 6 default categories, and you can **add new ones** and rename any of them (an English name + an Arabic name, each unique within your company).
- **Deleting is blocked while a category is in use** by vehicles, by drivers (qualified types) or by assets (compatible category) — the delete icon is disabled and the message says what uses it (e.g. "3 vehicles, 1 asset"). The six **primary** categories (the five FRD ones plus Tractor truck) can be renamed but **never deleted**, even when unused (their delete icon is always disabled). Only a category you added yourself, and only while unused, can be deleted.
- A new category automatically becomes a card on the Vehicles page, an option in the category filter, and an option in the vehicle, driver and asset forms. Renaming changes it everywhere (vehicles linked to it stay linked).
- **Export** (Excel / PDF) button on the list — the file holds the rows matching the search box.
- A "Manage categories" link above the category cards on the Vehicles page takes you to the same screen.
- **Default embedded container** (in the create/edit form): whether vehicles of this category usually have an embedded container, its type (container / tank) and, optionally, its capacity. When you pick the category on **Create vehicle** — or change it on **Edit vehicle** — those values are filled in automatically. They stay editable per vehicle, and changing a category's defaults later never touches vehicles that already exist. A category left at "No embedded container" fills nothing.

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
- Edit page with the same sections, **entry permits included**: each permit is a row (area / number / valid to / attachment) you can edit, add with "Add Permit", or remove with the trash icon. A permit's current attachment shows an eye icon ("View") next to it that opens the file in a new tab; leaving the file input empty keeps it and a new file replaces it. A permit removed from the list is deleted with its file on save. The residence / license / insurance documents show the same eye icon for their current file, and the photo shows a link to it. On the details pages the Attachment column of every documents / permits table (drivers and vehicles alike) has the same eye icon; the file opens in the browser's own viewer, which has its download button.

## 7. Asset management — `/app/asset/list`

System Admin: view, create, edit (full data), export, and all status changes.

### List
- **Separate stat cards for containers and tanks**: available / in projects / on maintenance / deactivated.
- Server-side table. Filters: type (container / tank) + status (active = available, **in a project**, on maintenance, deactivated) + affiliation. The "Availability" column shows "In a project" + the project name. Export button + an **Insert asset into project** button.
- Two items under "Assets" in the sidebar: **List** + **Capacities**.

### Create asset
- Name, type (container / tank), capacity category (filtered by type), compatible vehicle categories (checkboxes, per-asset), status, affiliation, purchase date, additional data.

### Edit asset
- The **name and the additional data** are editable — capacity, type, affiliation and categories are locked after creation (per FRD: they affect contracts and trips; the additional data is a client add-on, plain notes).

### Insert an asset into a project — `/app/asset/insert-into-project`
- From the "Insert asset into project" button on the assets list (or from a project's details page — the company and project are then filled in for you).
- Pick the **company** → the **project** (that company's active projects only) → the **type** (container / tank) → the **asset** (**available** assets only: active and not already in a project). All lists are live-search.
- After saving, the asset leaves the available pool and shows as "In a project" + the project name on the list, details and stat cards, and in the project's assets table. An asset is in one project only. **Taking an asset back out of a project is not defined by the FRD yet** (it comes with trips).

### Assets capacities — `/app/asset-category/list`
- Create and edit. After creation, the **name and the additional data** are editable (capacity and kind locked). No delete, no deactivate.
- The list's eye icon opens a **details page** (a client add-on, not in the FRD): the capacity's own data plus a table of every asset carrying it, linking to each asset's own details page.

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
- Details show everything, the contract attachment download, and "Supported sub-services" (the sub-services that list this facility — each one links to its details). A **Facility Status** card deactivates / reactivates the facility (confirm checkbox).
- Edit: the name and all optional data (the logo and contract attachment can be replaced). The **prefix, the environmental service and the recycling efficiency are locked after creation** (edit = the name and the optional data only). The contract number takes **digits only**.

## 10. Services — `/app/main-service/list` and `/app/sub-service/list`

Sidebar: **Operations ← Services** (Main Services → List · Add, and Sub Services → List · Add). System Admin: view, create, edit, deactivate / reactivate, export. Services are never deleted.

### Main services
- A main service is a **category** that groups sub-services, and the building block of a contract (PO).
- **List:** server-side table: main service name (link to its details), type of service, its sub-services (each a link), status, details. The search covers the main service name **and its sub-services' names**; filters: type + status; Excel/PDF export honouring the same filters.
- **Create:** name (unique), **type of service** (non-hazardous waste / hazardous waste / supplying), **supported vehicle categories** (at least one — from your company's vehicle categories), status (active by default), additional data.
- **Details:** the data + statistics (trips, projects, quantities in tons and cubic metres, drivers, resources — `0` until trips and contracts exist) + a table of **its sub-services** (search, asset-type and status filters, paging, and **Export** to Excel / PDF with exactly the rows on screen) and a "Create New Sub-service" button with the service pre-selected.
- **Edit:** the name and the additional data only. **The type of service and the supported vehicle categories are locked after creation.** (Once contracts exist, a service in a PO becomes fully locked and can't be deactivated.)
- **Status:** from the "Service Status" card on the edit page. **Deactivating a main service deactivates all its sub-services**; reactivating it **brings those sub-services back** — any that were deactivated on their own before stay deactivated (reactivate them from their own page). A deactivated main service isn't offered for new sub-services.

### Sub-services
- **Page:** an accordion with one tab per main service, open by default and collapsible, listing its sub-services with a view and an edit icon (the first 3, then **Show more** reveals 10 at a time with an "x of N" counter; a service with more than 20 also has a **View all N** link to its details page, whose table has search, asset-type/status filters and paging). It is **paginated by main service** (10 per page by default; 10 / 25 / 50 / 100), with a search box and a **"Search in"** selector (all / main services / sub-services — across all pages) and filters by type of service and status.
- **Create:** name, **main service** (live search — active only; its supported vehicle categories show automatically), **type** (container / tank), **compatible capacities** (listed by type; embedded containers of vehicles in the supported categories show underneath as information only), **intermediate facilities** (active ones — required unless the main service is "supplying"), **requires recycle receipt** (off by default), status, **skip colour code** (optional), additional data.
- **Details:** the data + statistics (`0` for now) + the compatible capacities table + the intermediate facilities table.
- **Edit:** the name, the skip colour code and the additional data only. **The main service, type, capacities, facilities and the recycle-receipt setting are locked after creation.** (Once trips and contracts exist, a sub-service tied to an ongoing trip — or whose main service is in a PO — becomes fully locked and can't be deactivated.)
- **Status:** from the "Service Status" card on the edit page. **A sub-service can't be reactivated while its main service is deactivated.**
- A vehicle category that a main service supports **can't be deleted**.

## 11. Contractor management — `/app/contractor/list`

Sidebar: **Operations ← Contractors** (List · Add). System Admin: view, create, edit, deactivate / reactivate, export. A contractor is a company bound to GCM by a contract that supplies **drivers, vehicles and containers / tanks** for its trips. Contractors are never deleted.
- **List:** four statistic cards (contractors with the active count, the vehicles, the assets — containers and tanks — and the drivers that contractors supply), then a server-side table: ID, contractor (with its trading name under it), drivers, vehicles, containers, tanks, status, view / edit. Search covers the name, trading name, short name and ID; filter by status; Excel / PDF export honouring them.
- **Create:** name, **short name** (3 unique English letters), trading name, logo, phone, address, map link, **certified services**, contract (number, start / end dates, copy), commercial and tax registration (numbers + copies), status (active by default), additional data. The **contractor ID** is the short name + **C** + a running number — e.g. `DSH-C0001` — so it can never equal a client company's ID.
- **Certified services:** tick the main services the contractor supports; **ticking none means it supports every service** (the details page then says "All services").
- **Details:** logo, name, trading name, status, ID, contact data, certified services, contract data with download links for the attachments, and the counters of drivers / vehicles / containers / tanks it supplies.
- **Edit:** every field except the **short name** (locked — the ID is built from it). A file left empty keeps the stored one. The certified services are replaced by whatever is ticked.
- **Status:** from the "Contractor Status" card on the edit page (confirm checkbox). **Nothing cascades** — deactivating a contractor does not deactivate its drivers, vehicles or assets; a deactivated contractor just isn't offered when you pick a contractor for a new driver / vehicle / asset.
- **Affiliation:** the driver, vehicle and asset forms now have **Belongs to GCM / Belongs to a Contractor** — choosing a contractor shows a searchable list of the active contractors to pick one. Lists and details then show the **contractor's name** in the affiliation column, and the Users / Drivers / Vehicles / Assets lists filter by it. A vehicle's or driver's affiliation can be changed on edit; an asset's is locked after creation (only its name is editable).
- **Representative and contractor users:** the "Contractor representative account" field is on the contractor's edit page, and the user accounts come from Users → Add User → Contractor User — see "Contractor users" in the Users section.

## 12. Client company management — `/app/company/list`

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

## 13. Client project management — `/app/project/list`

Sidebar: heading **Clients & Projects** → **Client Projects** (List + Add). A project is a work site of a client company (the place trips leave from).

### List
- Server-side table: ID, Company, Project, Contracts (`0` until that module exists) and Users (client accounts that can see the project), Status, Actions. Search by name, ID or company name + a company filter + a status filter + Excel/PDF export honouring the same filters.

### Create a project
- **Required:** name, **client company** (live search — **active** companies only), status (active by default).
- **The ID is generated automatically = the company's short name + `P` + a running number per company** (`ALN-P0001`, `ALN-P0002`…). That is why **the company is locked after creation**.
- **Optional:** operational region, phone, email, address, map link (`http`/`https` only), additional data, and the **project representative account** (an active client account that can see the project; on create only the chosen company's "all projects" accounts are listed).

### Details and edit
- Details page: the data + a **Google map of the project's location** (built from the pasted map link, or from the address when the link is a shortened one — the link stays visible) + statistics (contracts, trips, waste — `0` for now) + the contracts section (not built yet) + a table of the **assets in the project** (type filter + export + an insert-asset button).
- Edit page: every field except the company (locked; the ID is shown above the form).
- **Deactivate / reactivate** from the "Project Status" card on the edit page (with a confirmation). There is no delete. Deactivating a project does **not** deactivate the company, and deactivating a company does **not** deactivate its projects; a deactivated project is not offered when inserting an asset.

## 14. Purchase orders (contracts) — `/app/po/list`

Sidebar: heading **Clients & Projects** → **Purchase Orders** (List + Add). A purchase order (PO) is the **quota** of main services a client company buys for **one project** over a date range. Only the System Admin creates, edits and deactivates them (Data Entry has no access to POs at all, not even to read them).

### List
- Five cards: the **active** POs, the client companies and projects they cover, the trips recorded on them (0 until trips exist) and the total of their cost items (SAR).
- Server-side table: ID (`ALN-1200`), client company, project, duration, **days left**, **items status** (In force / About to run out / Fully exhausted) and account status (Active / Deactivated). Search by number, company or project; filters for company, project, time range, items status and account status; sort by number, dates, status. Excel/PDF export of exactly what is on screen.

### Create a PO
- **PO No.**: the number on the paper contract (digits only, up to 10, leading zeros kept). The system ID is the company's short name + it (`ALN-1200`); a number is never reused, even after the PO is deactivated.
- Pick the company, then one of its projects (the operational region fills in from the project and can be changed), the duration, status, an optional attachment (PDF or image, up to 5 MB) and notes.
- **Contract services**: "Add Service" adds a block per main service with how its quota is counted — *trips cost only* (max trips × trip cost) or *trips and ancillary services* (plus the ancillary cost per trip). The maximum totals are calculated live. The **alert percentage** of each service warns when the days, the trips or a cost total left fall below that share.
- **Overlap rule**: you can't add a main service to a PO of the same project while another active PO of that project already holds it over a shared day — unless one of its parts (trips, cost or date) has run out.

### Details
Left card: number, both statuses, company, project, region, duration, days left ("Starts in X days" / "Ended X days ago" when outside the range), attachment. Right: cards for the trips and cost used, and **one card per service** with its quota and a bar for what is left of each part (days, trips, trip cost, ancillary cost), coloured by its state.

### Edit and deactivate
- **No trip on the PO yet:** everything can be edited.
- **Once a trip is recorded:** only the **quota** of each service (max trips — never below what was used —, trip cost, ancillary cost, alert percentage). The number, company, project, dates, notes, attachment and the services themselves lock. A new trip cost applies to later trips only.
- **Deactivate / reactivate** is its own card on the edit page, allowed with or without trips. A deactivated PO is not offered for new trips, doesn't block another PO, and doesn't count in the cards. Reactivating is refused if another active PO now holds one of its services over a shared day.
- A main service that is in an active PO can't be edited or deactivated.

### In the company and project pages
The company page and the project page each have a **Contracts** section — a table of that company's (or project's) POs with search, items-status filter, export and "Add Purchase Order" (company/project already chosen) — and the **Contracts** card and the list column show the real count.
## 15. Trips — `/app/trip/list`

Sidebar: **Trips** → List + Add.

- **List:** five counters (awaiting client approval / approved / rejected / completed / cancelled), a server-side table (newest first) with the trip number, date, driver and vehicle with their affiliation, client, project, sub-service, status and PO link; filters (time range, status, client → project, driver, vehicle, affiliations, main → sub-service), search, and **Excel / PDF export** that matches the screen.
- **Create a trip (`/app/trip/add`):** pick in order driver → vehicle → client → project → main service → sub-service → facility; each list offers only what fits (active driver, vehicle within the driver's categories, service within the vehicle's category, containers that are free and fit). A vehicle with a built-in container or tank takes no external containers. The location is a map link or `lat, lng` and the photo a normal upload (no GPS or camera for staff). The PO quota is deducted when the trip is saved; with no PO the trip goes without one and the cost is typed. Choosing **No** for client approval records a completed trip directly (client and GCM signatures required, no code).
- **Edit:** changing date / project / service re-deducts the quota and recomputes the cost; an approved or completed trip keeps its status. Every change is written to the trip history.
- **Final approval, accepting / rejecting the client's objection, cancelling** (cancel gives the quota and containers back) and **reminding the client** are on the details page. Final approval, rejecting the objection and cancelling ask for a **verification code** sent to your email.
- **Trip documents:** a completed trip shows its Delivery Note, Waste Manifest (not for supply trips) and the combined PDF.

## 16. Notifications — `/app/notifications`

The **bell** in the top bar shows a dot when something is unread and lists the **latest five** notifications (type, sentence, date and time), each linking to the trip (or contract); "Mark all as read" and "View all notifications" are in its menu. The **Notifications page** has a table of all your own notifications (newest first) with a filter by type, a time range and a search box (trip code, contract number, type). They are ordinary notifications, not instant ones — they appear when the page loads or the bell opens. You only ever see your own, and never the person who caused the change.

The notices you get: a trip made by the driver (new, rejected by the client, receipt photo added), the client's approval of any trip, and the **contract alerts** — a PO service about to run out or exhausted, once per change (from trips and from a daily check). The contract notice opens the PO.

## 17. Features available now

| Module | Available | Deferred |
|---|---|---|
| Users | Full CRUD + status + export + **client accounts** (company, all/specific projects, signature & stamp) + **contractor users** | View/export pages of a contractor user for its contractor's resources (with Trips) |
| Vehicles | Full CRUD + status + export + embedded container | "on a trip" column/counter + vehicle trip log (Week 7) |
| Drivers | Full CRUD + default vehicle + documents + permits | "on a trip" status (Week 7), mobile app |
| Assets | Full CRUD + capacities + status + export + **insert an asset into a project** + "in a project" availability | Taking an asset out of a project (with trips) |
| Intermediate facilities | Create / edit / deactivate + prefix + recycling efficiency + contract + export + supported sub-services | — |
| Main & sub-services | Create / edit / deactivate (cascading to sub-services) + categories, capacities and facilities + recycle receipt + skip colour + main-services export + **lock while the service is in an active PO** | Real statistics and the trip lock (Week 7) |
| Contractors | Full CRUD + deactivate/reactivate + certified services + attachments + statistics + export + **driver / vehicle / asset affiliation to a contractor** + **contractor representative account** | — |
| Client companies | Full CRUD + deactivate/reactivate + attachments + export + projects count + company projects table + **representative + users count** + **contracts count and the company's contracts table** | Trip and waste statistics (with Trips) |
| Client projects | Full CRUD + deactivate/reactivate + ID from the company short name + project assets + export + **representative + users count** + **contracts count and the project's contracts table** | Trip and waste statistics (with Trips) |
| Purchase orders | Create / edit (locks to the quota once a trip is recorded) / deactivate + quota per service + computed status + overlap rule + attachment + stats + export + contracts section on the company and project pages | — |
| General Settings | Warning period for driver and vehicle document expiry (default 30 days, 1–365) — the "time left" bars on the driver and vehicle pages turn yellow (tag "Need review") from then on | — |
| Trips | List + counters + filters + export, create / edit (cascading lists, quota deduction), final approval, objection handling, cancel, remind the client, documents; the services lock after the first trip and the company / project / service / vehicle / PO pages show real trip figures | Notifications, dashboard and reports (Week 8) |
| Notifications | The bell (latest five + unread dot), the notifications page (filters + search), and contract alerts | — |
| Dashboard | The real dashboard: the five trip statuses, a 30-day trips chart, the main services' share, resources by status, purchase-order states and the latest ten notifications — each role gets only its own sections | — |
