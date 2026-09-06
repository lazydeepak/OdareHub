<?php
return [
    ['key'=>'admin.root','label'=>'Admin Tools','url'=>null,'parent'=>null,'order'=>10,'perm'=>null],
    ['key'=>'admin.apps','label'=>'Admin Tools','url'=>'/admin/apps','parent'=>'admin.root','order'=>10,'perm'=>null],
    ['key'=>'admin.app_manager','label'=>'App Manager','url'=>'/admin/app-manager','parent'=>'admin.root','order'=>15,'perm'=>null],
    ['key'=>'admin.routes','label'=>'Routes','url'=>'/admin/routes','parent'=>'admin.root','order'=>20,'perm'=>null],
    ['key'=>'admin.base_builder','label'=>'Base','url'=>'/admin/base','parent'=>'admin.root','order'=>30,'perm'=>null],
    ['key'=>'apps.root','label'=>'Apps','url'=>null,'parent'=>null,'order'=>20,'perm'=>null],
    ['key'=>'apps.cockpit','label'=>'Manufacturing Workspace','url'=>'/apps/manufacturing','parent'=>'apps.root','order'=>23,'perm'=>null],
    ['key'=>'apps.approval_inbox','label'=>'Approval Inbox','url'=>'/ops/approval-inbox','parent'=>'apps.root','order'=>24,'perm'=>null],
    ['key'=>'apps.my_work','label'=>'Home','url'=>'/','parent'=>'apps.root','order'=>24,'perm'=>null],
    ['key'=>'apps.notifications','label'=>'Notifications','url'=>'/ops/notifications','parent'=>'apps.root','order'=>24,'perm'=>null],
    ['key'=>'apps.audit_explorer','label'=>'Audit Explorer','url'=>'/ops/audit-log','parent'=>'apps.root','order'=>25,'perm'=>'ops.audit_explorer.view'],
];
