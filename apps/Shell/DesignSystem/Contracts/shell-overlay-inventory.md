# Shell Overlay Candidate Inventory

| Candidate | Definition | Generic controller ownership | Local ownership |
|---|---|---|---|
| Public account overflow | dropdown | Escape, outside, focus, visual | DOM/ARIA |
| Public notifications | dropdown | Escape, outside, focus, visual | content/DOM/ARIA |
| Public admin actions | dropdown | Escape, outside, focus, visual | actions/DOM/ARIA |
| Public topbar search | dropdown | outside, visual | Escape/query/listbox/navigation |
| Operator search | dropdown | outside, visual | Escape/query/listbox/navigation |
| Public mobile sidebar | sidebar | outside/backdrop, focus, visual | responsive DOM/navigation |
| Operator avatar | drawer | Escape, outside, focus, scroll, visual | preferences/content/DOM |
| Operator hamburger | drawer | Escape, outside, focus, scroll, visual | responsive DOM/navigation |
| Operator action sheet | drawer | Escape, outside, focus, scroll, visual | actions/DOM |
| Shared camera scan | viewport | root/backdrop outside, focus, visual | camera lifecycle/results/DOM |

All ten candidates use `OdareHubOS.ShellOverlay`; no candidate policy registry remains.
