<?PHP
/* Copyright 2005-2021, Lime Technology
 * Copyright 2014-2021, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2021, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
// add translations
$_SERVER['REQUEST_URI'] = 'docker';
require_once "$docroot/webGui/include/Translations.php";

require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

function safe($text) {
  return preg_replace('/[!@#$%^&\*\(\)\[\]{}"\|\?<>\/;]+/','',html_entity_decode($text));
}

$DockerClient = new DockerClient();
$_REQUEST     = array_merge(array_map('safe',$_GET), $_POST);
$action       = $_REQUEST['action'] ?? '';
$container    = $_REQUEST['container'] ?? '';
$name         = $_REQUEST['name'] ?? '';
$image        = $_REQUEST['image'] ?? '';
$arrResponse  = ['error' => _('Missing parameters')];

switch ($action) {
	case 'start':
		if ($container) $arrResponse = ['success' => $DockerClient->startContainer($container)];
		break;
	case 'pause':
		if ($container) $arrResponse = ['success' => $DockerClient->pauseContainer($container)];
		break;
	case 'stop':
		if ($container) $arrResponse = ['success' => $DockerClient->stopContainer($container)];
		break;
	case 'resume':
		if ($container) $arrResponse = ['success' => $DockerClient->resumeContainer($container)];
		break;
	case 'restart':
		if ($container) $arrResponse = ['success' => $DockerClient->restartContainer($container)];
		break;
	case 'remove_container':
		if ($container) $arrResponse = ['success' => $DockerClient->removeContainer($name, $container, 1)];
		break;
	case 'remove_image':
		if ($image) $arrResponse = ['success' => $DockerClient->removeImage($image)];
		break;
	case 'remove_all':
		if ($container && $image) {
			// first: try to remove container
			$ret = $DockerClient->removeContainer($name, $container, 2);
			if ($ret === true) {
				// next: try to remove image
				$arrResponse = ['success' => $DockerClient->removeImage($image)];
			} else {
				// error: container failed to remove
				$arrResponse = ['success' => $ret];
			}
		}
		break;
	case 'log':
		if ($container) {
			$since = $_REQUEST['since'] ?? '';
			$title = $_REQUEST['title'] ?? '';
			require_once "$docroot/webGui/include/ColorCoding.php";
			if (!$since) {
				readfile("$docroot/plugins/dynamix.docker.manager/log.htm");
				echo "<script>document.title = '$title';</script>";
				echo "<script>addLog('".addslashes("<p style='text-align:center'><span class='error label'>"._('Error')."</span><span class='warn label'>"._('Warning')."</span><span class='system label'>"._('System')."</span><span class='array label'>"._('Array')."</span><span class='login label'>"._('Login')."</span></p>")."');</script>";
				$tail = 350;
			} else {
				$tail = null;
			}
			$echo = function($s) use ($match) {
				$line = substr(trim($s), 8);
				$span = "span";
				foreach ($match as $type) {
					foreach ($type['text'] as $text) {
						if (preg_match("/$text/i",$line)) {
							$span = "span class='{$type['class']}'";
							break 2;
						}
					}
				}
				echo "<script>addLog('".addslashes("<$span>".htmlspecialchars($line)."</span>")."');</script>";
				@flush();
			};
			$DockerClient->getContainerLog($container, $echo, $tail, $since);
			echo '<script>setTimeout("loadLog(\''.addslashes(htmlspecialchars($container)).'\',\''.time().'\')", 2000);</script>';
			@flush();
			exit;
		}
		break;
	case 'terminal':
		$shell = $_REQUEST['shell'] ?: 'sh';
		$pid = exec("pgrep -a ttyd|awk '/\\/$name\\.sock/{print \$1}'");
		if ($pid) exec("kill $pid");
		@unlink("/var/tmp/$name.sock");
		exec("ttyd-exec -o -i '/var/tmp/$name.sock' docker exec -it '$name' $shell");
		break;
	default:
		$arrResponse = ['error' => _('Unknown action')." '$action'"];
		break;
}

header('Content-Type: application/json');
die(json_encode($arrResponse));
