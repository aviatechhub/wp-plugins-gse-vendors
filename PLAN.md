# PLAN.md — GSE Vendors Plugin (WordPress-only)

> Scope: Implement **REST endpoints** and **WP Admin UI** to manage Vendors’ **Basic Information**.
> **Only Administrators** can edit anything in wp-admin. Non-admins must not see or access vendor admin UI.
> (Next.js is out of scope; design REST to be consumed externally.)

---

## 0) Plugin Bootstrap & Project Setup

* [x] **Create plugin folder & files**: `wp-content/plugins/gse-vendors/` with `gse-vendors.php`, `includes/`, `assets/`, `readme.md`.
* [x] **Add plugin header** in `gse-vendors.php` and block direct access with `ABSPATH` check.
* [x] **Wire class autoloads**: require files from `includes/` (CPT, taxonomies, meta, REST, admin, roles/caps, activator).
* [x] **Define constants**: `GSE_VENDORS_PATH`, `GSE_VENDORS_URL`, `GSE_VENDORS_VERSION`.
* [x] **Register hooks**: `init` (CPT/tax/meta), `rest_api_init` (routes), `admin_init` (admin UI boot), activation hook (db/migrations).

---

## 1) Data Model — Core Vendor Entity

* [x] **Register CPT `vendor`**: public=true (front), `show_in_rest=true`, supports `title`, `editor`, `thumbnail`, archive `vendors`.
* [x] **Define slug rules**: rewrite slug `vendors`, ensure pretty permalinks behavior.
* [x] **(Optional) Register taxonomies**: `gse_location` (hierarchical), `gse_certification` (flat), `show_in_rest=true`.
* [x] **Register basic info meta**:

  * [x] `headquarters` (string, single, show\_in\_rest)
  * [x] `years_in_operation` (integer, single, show\_in\_rest)
  * [x] `website_url` (string/url, single, show\_in\_rest)
  * [x] `contact` (object `{email, phone, whatsapp}`, single, show\_in\_rest with schema)
* [ ] **Sanitizers**: text, `absint`, URL sanitizer, phone sanitizer (strip non-dial digits).
* [ ] **Auth callbacks** for meta update: require `edit_post` on vendor (will later be constrained to admins via capability mapping).

---

## 2) Per-Vendor Membership & Roles (for REST auth; no wp-admin access for non-admins)

* [ ] **Create custom table** `wp_gse_vendor_user_roles` via dbDelta on activation:

  * [ ] Columns: `id` PK, `vendor_id` BIGINT, `user_id` BIGINT, `role` VARCHAR(32), `assigned_at` DATETIME.
  * [ ] **Unique index** on (`vendor_id`, `user_id`).
  * [ ] Indexes on `vendor_id`, `user_id`, `role`.
* [ ] **Seed owner**: on vendor publish when no memberships exist, assign the creator as `owner`.
* [ ] **Role catalog** (internal, filterable): `owner`, `manager`, `editor`, `viewer`.
* [ ] **Capability matrix** (internal, filterable) mapping role → caps (e.g., `can_manage_members`, `can_edit_basic`, `can_delete_vendor`).
* [ ] **Guard function** `user_can_vendor(user_id, vendor_id, capability)` for REST permissions.

> Note: wp-admin edit screens will still be **admins only**; these roles only affect REST permissions.

---

## 3) REST API — Vendors CRUD & Search

* [ ] **Expose core endpoints** via WP REST (`/wp/v2/vendors`) for read (public) and write (guarded).
* [ ] **Computed field** `basic_info_summary` (read-only) on `vendor` entity (aggregates meta, tax term names, logo media id).
* [ ] **Custom route: search** `GET /wp-json/gse/v1/vendors/search`

  * [ ] Query params: `q`, `location`, `cert`, `per_page`, `page`.
  * [ ] Response: items (id, title, permalink, `basic_info_summary`), total, pages.
* [ ] **Custom route: vendor GET** `GET /gse/v1/vendors/{id}`

  * [ ] Return vendor with meta, tax, featured media URL, `basic_info_summary`.
* [ ] **Custom route: vendor CREATE** `POST /gse/v1/vendors`

  * [ ] Body: title, status (default `publish`), meta fields, taxonomy IDs.
  * [ ] **Permission**: `user_can_vendor(..., 'can_create_vendor')` OR site admin.
* [ ] **Custom route: vendor UPDATE** `PATCH /gse/v1/vendors/{id}`

  * [ ] Body: partial updates for title/meta/tax.
  * [ ] **Permission**: `user_can_vendor(user, vendor, 'can_edit_basic')` OR site admin.
* [ ] **Custom route: vendor DELETE** `DELETE /gse/v1/vendors/{id}`

  * [ ] **Permission**: `user_can_vendor(user, vendor, 'can_delete_vendor')` OR site admin.
* [ ] **Error semantics**: 403 (forbidden), 404 (missing vendor), 409 (conflicts), 422 (validation).
* [ ] **Schema docs**: attach argument schemas to routes (types, formats).

---

## 4) REST API — Membership Management (Admins or Vendor Owners/Managers via REST)

* [ ] **List members** `GET /gse/v1/vendors/{id}/members`

  * [ ] Returns: `{ user_id, display_name, email, role, assigned_at }[]`.
  * [ ] **Permission**: owner/manager or admin; viewers/editors may be denied or given subset (configurable).
* [ ] **Add/Invite member** `POST /gse/v1/vendors/{id}/members`

  * [ ] Body: `user_id`, `role`.
  * [ ] Enforce unique `(vendor_id, user_id)`.
  * [ ] **Permission**: owner/manager; only owner may create another owner.
* [ ] **Update member role** `PATCH /gse/v1/vendors/{id}/members/{user_id}`

  * [ ] Body: `role`.
  * [ ] Prevent demoting last owner.
  * [ ] **Permission**: owner (for owner promotions/demotions); manager for non-owner changes.
* [ ] **Remove member** `DELETE /gse/v1/vendors/{id}/members/{user_id}`

  * [ ] Prevent removing last owner.
  * [ ] **Permission**: owner/manager (subject to rules above).
* [ ] **Get my role** `GET /gse/v1/vendors/{id}/my-role`

  * [ ] Returns: `{ role }` or `null`.

---

## 5) WP Admin UI — Admin-Only Vendor Management

* [ ] **Restrict menu visibility**: add admin menu for Vendors only for `administrator` capability; hide for all other roles.
* [ ] **Enforce capability checks**: editing screens only accessible if `current_user_can('administrator')`.
* [ ] **Metabox: “Vendor — Basic Information”** on vendor edit:

  * [ ] Fields: Headquarters, Years in Operation, Website URL, Contact (email/phone/whatsapp).
  * [ ] Save handler: sanitize + update meta; nonce verification; autosave guard.
* [ ] **Taxonomies UI**: ensure “Service Locations” and “Certifications” meta boxes appear (admin only).
* [ ] **Featured Image as Logo**: add admin notice/instruction to use Featured Image for vendor logo.
* [ ] **Admin column enhancements**: add columns for HQ, Years, Website for vendor list table (sortable where applicable).
* [ ] **Admin scripts/styles**: enqueue minimal CSS/JS on vendor edit screens only.

---

## 6) Capabilities, Permissions & Hardening

* [ ] **Map CPT capabilities** to built-in caps but **limit UI access** strictly to `administrator`.
* [ ] **Nonces**: add and verify for all admin forms/metabox saves.
* [ ] **Data validation**: email format, URL format, numeric bounds (years ≥ 0), phone normalization.
* [ ] **REST permissions**: every **write** route checks either admin or `user_can_vendor` for the specific vendor.
* [ ] **Error messages**: consistent JSON error payloads with codes (e.g., `gse_forbidden`, `gse_conflict`, `gse_validation_error`).
* [ ] **(Optional) CORS headers**: document where to configure (server or plugin filter) for cross-origin REST access; default off.

---

## 7) Activation, Migrations & Uninstall

* [ ] **Activation routine**: create `wp_gse_vendor_user_roles` table (dbDelta), version option set.
* [ ] **Post-activation sanity**: ensure CPT/tax registered, flush rewrite rules.
* [ ] **Backfill owners**: assign creator as owner for existing vendors with no membership rows.
* [ ] **Plugin version upgrades**: migration runner if schema changes in future.
* [ ] **Uninstall behavior**: decide whether to drop custom table; provide uninstall script accordingly (document risks).

---

## 8) QA & Acceptance Checklist

* [ ] **Admin-only UI**: log in as non-admin → vendor menu hidden; direct URL to edit screen returns 403.
* [ ] **Create/Edit vendor** (admin): set meta, tax, featured image; data persists and appears in REST `wp/v2/vendors/{id}`.
* [ ] **Search route**: returns paginated results with `basic_info_summary`.
* [ ] **REST write guard**: non-member non-admin cannot `POST/PATCH/DELETE`; receives 403.
* [ ] **Membership routes**: add/update/remove roles; duplicate add blocked with 409; last owner removal blocked.
* [ ] **Computed field**: `basic_info_summary` contains meta, locations, certifications, logo media id.
* [ ] **Data validation**: bad inputs produce 422 with clear messages.
* [ ] **Rewrite rules**: vendor archive and single pages resolve; REST endpoints reachable.

---

## 9) Documentation (in `readme.md`)

* [ ] **Overview**: what the plugin does; admin-only UI, REST endpoints.
* [ ] **CPT & Meta schema**: fields and types.
* [ ] **Taxonomies**: names and intended usage.
* [ ] **REST endpoints**: paths, methods, params, responses, error codes.
* [ ] **Permissions**: who can call what; wp-admin vs REST rules.
* [ ] **Activation steps**: install, activate, permalinks flush, initial checks.
* [ ] **Versioning & Changelog**: maintain semantic changes.

---

### Notes

* The **wp-admin** editing experience is locked to **Administrators**.
* **Per-vendor roles** exist solely to authorize **REST** write operations for non-admins.
* No Next.js code or configuration is included here by design.
