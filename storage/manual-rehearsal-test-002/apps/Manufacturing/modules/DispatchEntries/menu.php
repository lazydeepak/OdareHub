<?php
return [
  ['key' => 'ipm.root', 'label_key' => 'nav.ipm', 'label' => 'IPM', 'url' => null, 'parent' => null, 'order' => 30, 'perm' => null],
  ['key' => 'ipm.dispatch_leader', 'label_key' => 'nav.dispatch_leader', 'label' => 'Dispatch', 'url' => '/apps/manufacturing/dispatch-ops', 'parent' => 'ipm.root', 'order' => 98, 'perm' => null],
  ['key' => 'ipm.dispatch_entries', 'label_key' => 'nav.dispatch_entries', 'label' => 'Dispatch Entries', 'url' => '/dispatch-entries', 'parent' => 'ipm.root', 'order' => 100, 'perm' => null],
];
