<?php
return [
  ['key' => 'ipm.root', 'label_key' => 'nav.ipm', 'label' => 'IPM', 'url' => null, 'parent' => null, 'order' => 30, 'perm' => null],
  ['key' => 'ipm.qc_leader', 'label_key' => 'nav.qc_leader', 'label' => 'QC', 'url' => '/apps/manufacturing/qc-workboard', 'parent' => 'ipm.root', 'order' => 88, 'perm' => null],
  ['key' => 'ipm.qc_entries', 'label_key' => 'nav.qc_entries', 'label' => 'QC Entries', 'url' => '/qc-entries', 'parent' => 'ipm.root', 'order' => 90, 'perm' => null],
];
