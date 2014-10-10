<?php
/*
    PufferPanel - A Minecraft Server Management Panel
    Copyright (c) 2013 Dane Everitt

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see http://www.gnu.org/licenses/.
 */
session_start();
require_once('../../../src/framework/framework.core.php');

$filesIncluded = true;

if($core->auth->isLoggedIn($_SERVER['REMOTE_ADDR'], $core->auth->getCookie('pp_auth_token'), $core->auth->getCookie('pp_server_hash')) === false)
	Page\components::redirect($core->settings->get('master_url').'index.php?login');

/*
 * Display Page
 */
echo $twig->render(
		'node/plugins/index.html', array(
            'server' => array(
                'name' => $core->server->getData('name')
            ),
			'footer' => array(
				'queries' => Database\databaseInit::getCount(),
				'seconds' => number_format((microtime(true) - $pageStartTime), 4)
			)
	));
?>
