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
namespace PufferPanel\Core;
use \ORM;

$klein->respond(array('GET', 'POST'), '/node/files/[*]', function($request, $response, $service, $app, $klein) use($core) {

	if(!$core->permissions->has('files.view')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		$klein->skipRemaining();

	}

});

$klein->respond('GET', '/node/files', function($request, $response, $service) use($core) {

	$response->body($core->twig->render('node/files/index.html', array(
		'server' => $core->server->getData(),
		'flash' => $service->flashes()
	)))->send();

});

$klein->respond('GET', '/node/files/download/[*:file]', function($request, $response, $service) use($core) {

	if(!$core->permissions->has('files.download')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	}

	if(!$request->param('file')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	} else {

		if(!$core->gsd->avaliable($core->server->nodeData('ip'), $core->server->nodeData('gsd_listen'))) {

			$service->flash('<div class="alert alert-danger">Unable to access the server daemon to process file downloads.</div>');
			$response->redirect('/node/files')->send();
			return;

		}

		$downloadToken = $core->auth->keygen(32);

		$download = ORM::forTable('downloads')->create();
		$download->set(array(
			'server' => $core->server->getData('hash'),
			'token' => $downloadToken,
			'path' => str_replace("../", "", $request->param('file'))
		));
		$download->save();

		$response->redirect("https://".$core->server->nodeData('ip').":".$core->server->nodeData('gsd_listen')."/server/download/".$downloadToken)->send();

	}

});

$klein->respond('GET', '/node/files/edit/[*:file]', function($request, $response, $service) use ($core) {

	if(!$core->permissions->has('files.edit')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	}

	$file = (object) pathinfo($request->param('file'));
	if(!in_array($file->extension, $core->files->editable())) {

		$service->flash('<div class="alert alert-danger">You do not have permission to edit files with that extension.</div>');
		$response->redirect('/node/files')->send();
		return;

	}

	if(in_array($file->dirname, array(".", "./", "/"))) {
		$file->dirname = "";
	} else {
		$file->dirname = trim($file->dirname, '/')."/";
	}

	try {

		$unirest = \Unirest\Request::get(
			"https://".$core->server->nodeData('ip').":".$core->server->nodeData('gsd_listen')."/server/file/".rawurlencode($file->dirname.$file->basename),
			array(
				"X-Access-Token" => $core->server->getData('gsd_secret'),
				"X-Access-Server" => $core->server->getData('hash')
			)
		);

		if($unirest->code !== 200 || !isset($unirest->body->contents)) {

			$service->flash('<div class="alert alert-danger">An error was encountered when trying to retrieve this file for editing. [HTTP\1.1 '.$unirest->code.']</div>');
			$response->redirect('/node/files')->send();
			return;

		}

		/*
		* Display Page
		*/
		$response->body($core->twig->render('node/files/edit.html', array(
			'flash' => $service->flashes(),
			'server' => $core->server->getData(),
			'xsrf' => $core->auth->XSRF(),
			'file' => $request->param('file'),
			'extension' => $file->extension,
			'directory' => $file->dirname,
			'contents' => $unirest->body->contents
		)))->send();

	} catch(\Exception $e) {

		\Tracy\Debugger::log($e);
		$service->flash('<div class="alert alert-danger">The daemon does not appear to be online currently. Please try again.</div>');
		$response->redirect('/node/files')->send();
		return;

	}

});

$klein->respond('GET', '/node/files/add/[*:directory]?', function($request, $response, $service) use($core) {

	if(!$core->permissions->has('files.create') || !$core->permissions->has('files.upload')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	}

	$response->body($core->twig->render(
		'node/files/add.html',
		array(
			'flash' => $service->flashes(),
			'directory' => $request->param('directory'),
			'server' => $core->server->getData(),
			'node' => $core->server->nodeData()
		)
	))->send();

});

$klein->respond('POST', '/node/files/add', function($request, $response, $service) use($core) {

	if(!$core->permissions->has('files.create')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	}

	try {

		$unirest = \Unirest\Request::put(
			"https://".$core->server->nodeData('ip').":".$core->server->nodeData('gsd_listen')."/server/file/".rawurlencode($request->param('newFilePath')),
			array(
				"X-Access-Token" => $core->server->getData('gsd_secret'),
				"X-Access-Server" => $core->server->getData('hash')
			),
			array(
				"contents" => $request->param('newFileContents')
			)
		);

		if($unirest->code !== 204) {

			$response->code($unirest->code);
			$response->body('An error occured while trying to write the file to the server. ['.$unirest->body->message.']')->send();
			return;

		}

		$response->code(200);
		$response->body('ok')->send();
		return;

	} catch(\Exception $e) {

		\Tracy\Debugger::log($e);
		$response->code(500);
		$response->body('An execption occured when trying to connect to the server.')->send();
		return;

	}

});

$klein->respond('/node/files/upload', function($request, $response, $service) use($core) {

	if(!$core->permissions->has('files.upload')) {

		$response->code(403);
		$response->body('you don\'t have permission to do that')->send();
		return;

	}

	// prevent output buffering
	ini_set('upload_max_filesize', '100M');
	ini_set('post_max_size', '110M');
	set_time_limit(0);

	if($request->param('newFilePath') === null) {

		$response->code(404);
		$response->body('missing parameters')->send();
		return;

	}

	if(($request->param('flowTotalSize') / (1024 * 1024)) > 100) {

		$response->code(500);
		$response->body('this file is too large to upload (max size: 100MB)')->send();
		return;

	}

	if(ob_get_level()) {
		ob_end_clean();
	}

	$tempDir = '/tmp/'.$core->server->getData('hash');
	$uploadPath = SRC_DIR.'cache/uploads/'.$core->server->getData('hash').'/';

	if(!is_dir($tempDir)) {
		mkdir($tempDir, 0777);
	}

	if(!is_dir($uploadPath)) {
		mkdir($uploadPath, 0777);
	}

	$config = new Flow\Config();
	$config->setTempDir($tempDir);
	$file = new Flow\File($config);


	if($request->method('get')) {

		if($file->checkChunk()) {
			$response->code(200)->body('chunked')->send();
			return;
		} else {

			$response->code(404);
			$response->body('unable to work with chunk')->send();
			return;

		}

	} else {

		if($file->validateChunk()) {
			$file->saveChunk();
		} else {

			$response->code(400);
			$response->body('an error occured')->send();
			return;

		}

	}

	try {

		if($file->validateFile() && $file->save($uploadPath.$request->param('flowFilename'))) {

			// Handle Upload Here
			$response->code(400);
			$response->body('file uploading is disabled currently')->send();
			return;
			// $stream = fopen($uploadPath.$request->param('flowFilename'), 'r');
			// $filesystem->writeStream(rtrim($request->param('newFilePath'), '/').'/'.$request->param('flowFilename'), $stream);
			// unlink($uploadPath.$request->param('flowFilename'));
			//
			// $response->code(200)->body('done')->send();
			// return;

		}

	} catch(\Exception $e) {

		\Tracy\Debugger::log($e);
		unlink($uploadPath.$request->param('flowFilename'));

		$response->code(400);
		$response->body('unable to write file to server')->send();
		return;

	}

	exit();

});