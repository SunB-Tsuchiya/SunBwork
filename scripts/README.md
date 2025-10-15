# Export scripts

This folder contains scripts for exporting `attachments` data.

Files

- `attachments_export.php` - Legacy CSV export that includes legacy columns (message_id, diary_id, event_id). Kept for backwards compatibility with external workflows.
- `attachments_export_with_pivots.php` - New pivot-aware export. Produces two files in `exports/`:
    - `attachments.csv` - attachments table (id, path, original_name, mime_type, size, user_id, owner_type, owner_id, status, created_at, updated_at)
    - `attachmentables.csv` - attachmentables pivot (id, attachment_id, attachable_type, attachable_id, created_at, updated_at)

Usage

Run inside the project root (container or local PHP with project deps):

php scripts/attachments_export_with_pivots.php

Notes

- Do not remove the legacy export script if external jobs depend on it. Prefer the pivot-aware export for new tooling.
- `dev_backups/` contains archived/partial scripts that should not be executed.
