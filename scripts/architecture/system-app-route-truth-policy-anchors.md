# System App Route and Source-of-Truth Policy Anchors

This note records the stable route-policy and source-of-truth anchors enforced for Shell and Platform system apps.

## Shell

```text
source_of_truth_policy: compose_resolved_contracts_only
canonical_prefix: /u/{username}
canonical_prefix: /admin/{username}
canonical_prefix: /displays
compatibility_surface: /me
compatibility_surface: /me2
must_not_reinterpret_routes: true
```

## Platform

```text
source_of_truth_policy: govern_and_diagnose_without_owning_runtime_business_meaning
canonical_prefix: /apps/platform
canonical_prefix: /ops/*
canonical_prefix: /admin/*
compatibility_surface: /ops/dashboard
must_not_reinterpret_routes: true
```

These anchors preserve the current route model and prevent Shell or Platform from becoming a hidden source of route truth.
