<?php
// REGRESSION PROOF: file_put_contents to registry path must still be detected
file_put_contents('platform/Style/Registry/registry.json', '{"bad":"mutation"}');
