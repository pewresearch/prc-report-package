# PRC Report Package

Manages multi-post research report packages on the PRC Platform. A report package is a parent post linked to one or more chapter (child) posts, with shared taxonomy terms, publication dates, associated materials, and a table of contents. The plugin handles relationship enforcement, REST exposure, block editor UI, two frontend blocks, and Distributor-based cross-site distribution.

## What it does

- Stores chapter ordering (`multiSectionReport` meta), materials (`reportMaterials` meta), and TOC part groupings (`package_parts` meta) on `post` objects
- Syncs child posts to their parent on save: mirrors `post_status`, `post_date`, and all taxonomy terms (skips no-op writes and suppresses nested post-publish pipeline fan-out during sync)
- Sets `post_parent` for chapter posts on incremental save via async reconciliation: assigns listed chapters and clears `post_parent` on detached chapters
- Overrides `get_next_post_where` and `get_previous_post_where` so that adjacent-post navigation stays within the package sequence
- Exposes `table_of_contents`, `report_materials`, `report_pagination`, and `parent_info` as REST fields on all public post types (or `post` where applicable)
- Registers a block editor sidebar plugin ("Report Package") with panels for managing chapters and materials without leaving the editor
- Prepends an em-dash to chapter post titles in WP Admin list views to visually distinguish them from standalone posts
- Registers two dynamic blocks: `prc-block/report-materials` and `prc-block/report-pagination`
- Integrates with Distributor to cascade-distribute chapter posts, material attachments, and TOC parts across sites, remapping all IDs on the target

## Key files

| File                                              | Purpose                                                                                                                                 |
| ------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| `prc-report-package.php`                          | Plugin entry point; defines constants, registers activation/deactivation hooks, boots `Plugin`                                          |
| `includes/class-plugin.php`                       | Orchestrates all dependencies via `Loader`; initializes blocks via `PRC\BlockUtils\load_blocks`                                         |
| `includes/class-loader.php`                       | Hook registration queue (standard PRC loader pattern)                                                                                   |
| `includes/class-rest-api.php`                     | Registers post meta fields and REST fields; defines meta key constants                                                                  |
| `includes/class-relationship-manager.php`         | Syncs child posts on parent update/publish; async reconcile of chapter `post_parent`; overrides adjacent-post WHERE clauses |
| `includes/class-wp-admin.php`                     | Enqueues inspector sidebar panel; modifies admin post titles for chapter posts                                                          |
| `includes/class-distributor.php`                  | Distributor data handlers: pre/post-distribute callbacks for chapters, materials, parts; post_parent restoration                        |
| `includes/utils.php`                              | Public helper functions: `get_package_id`, `is_report_package`, `get_package_chapters`, `get_package_materials`, `get_pagination`, etc. |
| `includes/inspector-sidebar-panel/src/index.js`   | Block editor plugin entry; renders `PluginSidebar` + `PluginPrePublishPanel`                                                            |
| `includes/inspector-sidebar-panel/src/chapters/`  | Chapters management UI (add/search existing, create draft, reorder)                                                                     |
| `includes/inspector-sidebar-panel/src/materials/` | Materials management UI (add, reorder, set type)                                                                                        |
| `build/report-materials/`                         | `prc-block/report-materials` block (block.json + PHP render class + JS editor)                                                          |
| `build/report-pagination/`                        | `prc-block/report-pagination` block (block.json + PHP render class + JS editor)                                                         |

## Blocks

| Block                         | Name              | Description                                                                                                                  |
| ----------------------------- | ----------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `prc-block/report-materials`  | Report Materials  | Renders a `<ul>` of all materials for the current post's package; reads `reportMaterials` meta via `get_package_materials()` |
| `prc-block/report-pagination` | Report Pagination | Renders chapter pagination and a "Next: …" button; reads from `get_pagination()` which wraps `PRC\BlockUtils\Pagination`     |

Both blocks are dynamic (PHP-rendered), use `postId` context, and support color, spacing, and typography controls.

## Filters / hooks

### Actions consumed

| Hook                                | Description                                                                                                   |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `init`                              | Registers post meta fields, block types, and Distributor data handlers                                        |
| `rest_api_init`                     | Registers REST fields (`table_of_contents`, `report_materials`, `report_pagination`, `parent_info`) on `post` |
| `enqueue_block_editor_assets`       | Enqueues the inspector sidebar panel script for enabled post types                                            |
| `prc_platform_on_incremental_save`  | Enqueues async reconcile of chapter `post_parent` (assign listed chapters, clear detached)                    |
| `prc_platform_async_on_incremental_save` | Reconciles chapter `post_parent` from `multiSectionReport` meta (server-side via Action Scheduler)       |
| `prc_platform_on_update`            | Propagates parent's `post_status`, `post_date`, and taxonomy terms to chapters (skips no-ops; suppresses nested pipeline) |
| `prc_platform_on_publish`           | Same as `prc_platform_on_update`, then enqueues async publish side-effects for each chapter |
| `dt_process_distributor_attributes` | Restores `post_parent` relationships on the target site after Distributor push                                |

### Filters consumed

| Hook                                                 | Direction | Description                                                                                                  |
| ---------------------------------------------------- | --------- | ------------------------------------------------------------------------------------------------------------ |
| `prc_platform_post_publish_pipeline_should_process`  | Filter    | Temporarily returns `false` during `update_children` so chapter `wp_update_post` calls do not re-enter the sync or async pipeline |
| `get_next_post_where`                                | Filter    | Replaces the standard WHERE clause with a package-aware one so `get_next_post()` traverses chapters in order |
| `get_previous_post_where`                            | Filter    | Same as above for `get_previous_post()`                                                                      |
| `the_title`                                          | Filter    | In WP Admin list views, prepends `&mdash; ` to titles of chapter posts                                       |

### Filters provided

| Hook                                         | Description                                                                                                                         |
| -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `prc_platform_post_report_package_materials` | Filters the materials array before it is returned by `get_package_materials()`. Receives `$materials` (array) and `$post_id` (int). |

## REST fields

All fields are read-only (GET only) and appended to existing post REST responses.

| Field               | Post types            | Callback                                                                                       |
| ------------------- | --------------------- | ---------------------------------------------------------------------------------------------- |
| `table_of_contents` | All public post types | Returns ordered chapter array from `get_package_chapters()`                                    |
| `report_materials`  | `post`                | Returns materials array from `get_package_materials()`                                         |
| `report_pagination` | `post`                | Returns `{ current_post, next_post, previous_post, pagination_items }` from `get_pagination()` |
| `parent_info`       | `post`                | Returns `{ parent_id, parent_title }` for child posts                                          |

## Post meta

All meta is registered on `post`, exposed via REST, and revision-enabled.

| Meta key                 | Type      | Description                                                                        |
| ------------------------ | --------- | ---------------------------------------------------------------------------------- |
| `multiSectionReport`     | `array`   | Ordered list of chapter objects: `[{ key: string, postId: int }]`                  |
| `reportMaterials`        | `array`   | Materials: `[{ key, type, url, label, attachmentId, icon }]`                       |
| `package_parts`          | `array`   | TOC groupings: `[{ key, label, items: int[] }]` where `items` are chapter post IDs |
| `package_parts__enabled` | `boolean` | Whether TOC part grouping is active for this post                                  |

> The meta keys `multiSectionReport` and `reportMaterials` use camelCase for legacy reasons. A TODO exists to rename them to snake_case (`package_chapters`, `package_materials`). Do not rename without a migration.

## Helper functions

Defined in `includes/utils.php` under the `PRC\Platform\Report_Package` namespace.

| Function                                        | Returns | Description                                                                                                   |
| ----------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------- |
| `get_package_id( $post_id )`                    | `int`   | Returns the package root ID. If `$post_id` is a child, returns its parent.                                    |
| `is_report_package( $post_id )`                 | `bool`  | True if the post is a top-level package (has chapters, no parent).                                            |
| `is_chapter_part_of_report_package( $post_id )` | `bool`  | True if the post (or its parent) has `multiSectionReport` meta.                                               |
| `is_part_of_a_report_package( $post_id )`       | `bool`  | True for both root packages and chapter posts.                                                                |
| `get_package_chapters( $post_id )`              | `array` | Returns ordered TOC array including root post, using `construct_chapter()`.                                   |
| `get_package_materials( $post_id )`             | `array` | Returns materials for the package root. Handles meta normalization and Print Engine beta injection.           |
| `get_pagination( $post_id )`                    | `array` | Wraps `PRC\BlockUtils\Pagination` to return `current_post`, `next_post`, `previous_post`, `pagination_items`. |

## Constants

Defined in `prc-report-package.php`.

| Constant                                | Value                                 |
| --------------------------------------- | ------------------------------------- |
| `PRC_REPORT_PACKAGE_FILE`               | Absolute path to the main plugin file |
| `PRC_REPORT_PACKAGE_DIR`                | Absolute path to the plugin directory |
| `PRC_REPORT_PACKAGE_VERSION`            | `1.0.0`                               |
| `PRC_REPORT_PACKAGE_BLOCKS_DIR`         | `{PRC_REPORT_PACKAGE_DIR}/build`      |
| `PRC_REPORT_PACKAGE_ENABLED_POST_TYPES` | `['post']`                            |

## Distributor integration

When a report package post is pushed via Distributor, three data handlers cascade-distribute related content:

| Handler                        | Meta key             | What it does                                                                                                                                                                        |
| ------------------------------ | -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `prc_report_package_chapters`  | `multiSectionReport` | Distributes each chapter post and remaps `postId` values; also distributes `prc-chart-builder/synced-chart` and `prc-chart-builder/chart` block references found in chapter content |
| `prc_report_package_materials` | `reportMaterials`    | Distributes each material attachment and remaps `attachmentId` values                                                                                                               |
| `prc_report_package_parts`     | `package_parts`      | Remaps chapter post IDs stored in `items` arrays                                                                                                                                    |

After distribution, `dt_process_distributor_attributes` restores `post_parent` on each chapter to point to the new parent ID on the target site. If meta is unavailable at hook time, a `shutdown` fallback handles the update.

Uses `WP_Block_Processor` (WordPress 6.9+) for efficient block traversal; falls back to `parse_blocks()` on older versions.

## Dependencies

- **Required plugin:** `prc-platform-core`
- **PHP:** 8.2+, **WordPress:** 6.7+
- **PHP classes:** `PRC\BlockUtils\load_blocks`, `PRC\BlockUtils\Pagination`, `PRC\BlockUtils\get_block_gap_support_value`, `PRC\Platform\Icons\render`
- **Optional:** Distributor plugin — integration is gated on `function_exists( 'distributor_register_data' )`

## Development

```bash
# Build all assets (blocks + inspector panel)
npm run build -w @prc/report-package

# Watch mode
npm run start:blocks -w @prc/report-package
npm run start:inspector-panel -w @prc/report-package

# Run Playwright tests (from monorepo root; VIP dev-env + Playwright are centralized)
npm run vip:start
npm test -- tests/prc-report-package/e2e/
```

The inspector panel (`includes/inspector-sidebar-panel/`) and the blocks (`src/report-materials/`, `src/report-pagination/`) have separate build pipelines. Both are invoked by the top-level `build` script.
