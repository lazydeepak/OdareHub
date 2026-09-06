<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

enum ValidationStage: string
{
    case DECLARATION_PARSE = 'declaration_parse';
    case COMPILATION = 'compilation';
    case SYSTEM_TOOLS = 'system_tools';
    case RUNTIME = 'runtime';
}
