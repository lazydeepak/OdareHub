<?php
return [
  ['key' => 'ipm.root', 'label_key' => 'nav.ipm', 'label' => 'IPM', 'url' => null, 'parent' => null, 'order' => 30, 'perm' => null],
  ['key' => 'ipm.machine_leader', 'label_key' => 'nav.machine_leader', 'label' => 'Production', 'url' => '/apps/manufacturing/production-workboard', 'parent' => 'ipm.root', 'order' => 18, 'perm' => null],
  ['key' => 'ipm.machines', 'label_key' => 'nav.machines', 'label' => 'Machines', 'url' => '/machines', 'parent' => 'ipm.root', 'order' => 20, 'perm' => null],
];
