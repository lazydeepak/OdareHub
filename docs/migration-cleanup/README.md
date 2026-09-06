# Migration Cleanup Workspace

Purpose: prepare a safe, staged cleanup from repository root into a maintainable documentation structure.

This workspace is non-destructive:
- Existing root files are not moved yet.
- This area tracks grouping, ownership, and move order.
- Future slices can relocate files in small, reviewable commits.

## Structure

- `maps/root-file-inventory.md`: current root migration/report docs inventory.
- `maps/category-map.md`: grouping by domain/owner.
- `maps/folder-walkthrough.md`: full-system migration cleanup walkthrough across docs, runtime owners, generated artifacts, public delivery output, scripts, packages, and storage references.
- `maps/batch-1-docs-cleanup.md`: proof record for the docs-only operator documentation cleanup batch.
- `maps/batch-2-navigation-composition-inventory.md`: navigation/composition inventory labels and diagnostic results.
- `maps/batch-3-generated-apps-archive-policy.md`: generated apps archive/delete policy and dry-run diagnostic evidence.
- `maps/batch-4-runtime-registry-source-of-truth.md`: runtime registry and source-of-truth classification.
- `maps/batch-5-db-menus-runtime-fallback.md`: DB menus fallback/search/compatibility diagnostic.
- `maps/batch-6-public-assets-delivery-output.md`: public asset delivery-output/source classification.
- `maps/batch-7-storage-runtime-retention-policy.md`: storage runtime artifact retention classification.
- `maps/batch-8-studio-boundary-priority-inventory.md`: Studio ownership boundary and bridge classification.
- `maps/batch-9-studio-runtime-adjacent-separation-map.md`: Studio runtime-adjacent separation boundaries and next split sequencing.
- `phases/move-plan.md`: staged relocation plan.
- `backlog/*.md`: per-domain follow-up tasks.

## Suggested Workflow

1. Pick one category from `maps/category-map.md`.
2. Move only that category files in one commit.
3. Update internal links and root references.
4. Run architecture/deployment gates.
5. Record result in related `backlog/*.md` file.
