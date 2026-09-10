<?php
// REGRESSION PROOF: A Shell composer that actually consumes Studio must fail
// (Not using exclusion — this tests that exclusion is narrow, not broad)
$studioConsumer = new \Apps\Studio\Tools\CustomizationStudio\Services\SomeRuntimeService();
