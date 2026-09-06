<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class NavigationContract
{
    public readonly string $report_key;
    public readonly string $title;
    public readonly string $permission;
    public readonly string $lifecycle;
    public readonly string $category;
    public readonly string $visualization;

    public function __construct(ReportResource $r)
    {
        $this->report_key = $r->report_key;
        $this->title = $r->title;
        $this->permission = $r->permission;
        $this->lifecycle = $r->lifecycle;
        $this->category = $r->category;
        $this->visualization = $r->visualization;
    }

    public function toArray(): array
    {
        return [
            'report_key' => $this->report_key,
            'title' => $this->title,
            'permission' => $this->permission,
            'lifecycle' => $this->lifecycle,
            'category' => $this->category,
            'visualization' => $this->visualization,
        ];
    }
}
