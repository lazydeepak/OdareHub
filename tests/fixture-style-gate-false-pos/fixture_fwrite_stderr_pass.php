<?php
// REGRESSION PROOF: fwrite(STDERR, ...) is diagnostic output, not registry mutation
fwrite(STDERR, "FAIL: test label: expected value\n");
