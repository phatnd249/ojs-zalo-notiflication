<?php

/**
 * @file plugins/generic/zaloNotification/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_zaloNotification
 * @brief Wrapper for ZaloNotification plugin.
 *
 */

require_once('ZaloNotificationPlugin.inc.php');

return new ZaloNotificationPlugin();
