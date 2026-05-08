<?php

require __DIR__ . '/bootstrap.php';

echo "\nRunning tests:\n";

require __DIR__ . '/time_based_access_test.php';

require __DIR__ . '/readable_share_id_test.php';

require __DIR__ . '/document_search_test.php';

require __DIR__ . '/migration_test.php';

finish_tests();
