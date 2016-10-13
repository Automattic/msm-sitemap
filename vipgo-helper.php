<?php

// On VIP Go we're blocking using cron as it creates a mess of cron for large datasets
add_filter( 'msm_sitemap_use_cron_builder', '__return_false', 9999 );
