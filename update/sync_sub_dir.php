<?php

$selfDir = dirname(__FILE__);
require_once $selfDir . '/config.php';
require_once $selfDir . '/helper.php';

$dir = isset($_POST['dir']) && is_string($_POST['dir']) ? $_POST['dir'] : '';
$token = filterAccessToken(isset($_POST['access_token']) ? (string) $_POST['access_token'] : '');

$dlHandler = null;

try {
    if (!preg_match("/^[a-z]{3,4}$/", $dir)) {
        throw new RuntimeException('Невалидное значение для синхронизируемой суб-директории');
    }

    $headers = array('Accept: application/json', 'Authorization: Bearer ' . $token);
    $response = remoteRequest($filemanagerApiDomen . 'sync/getFileNamesFromSubDirectory?dir=' . $dir, true, false, $headers);
    if ($response->curlHasError) {
        throw new RuntimeException('CURL ' . $response->curlErrorTxt);
    }
    if (
        $response->code != 200
        || !property_exists($response->responseBody, 'files')
        || !is_array($response->responseBody->files)
    ) {
        $resultBody = array(
            'success' => false,
            'forward_code' => $response->code,
            'message' => 'Не удалось получить список файлов с файлового сервера'
                . tryExtractFmErrorMessage($response, '. '),
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    $filesByNamesFromFm = array();
    foreach ($response->responseBody->files as $row) {
        $filesByNamesFromFm[(string) $row->n] = $row->i;
    }
    $response = null;

    $dirPath = $selfDir . '/../files/' . $dir;
    if (!file_exists($dirPath)) {
        if (!mkdir($dirPath, 0775)) {
            throw new RuntimeException('Не удалось создать папку "' . $dirPath . '" на вашем сервере');
        }
    }

    $dh = opendir($dirPath);
    if (!$dh) {
        $resultBody = array(
            'success' => false,
            'forward_code' => 500,
            'message' => 'Не удалось просканировать существующие файлы в директории:' . $dirPath,
        );
        loadHeaders();
        setResponseCode(200);
        echo json_encode($resultBody);
        die();
    }

    $existingItemsByNames = array();
    while (($fName = readdir($dh)) !== false) {
        $existingItemsByNames[$fName] = null;
    }
    closedir($dh);

    foreach ($existingItemsByNames as $subDirItem => $_) {
        if ($subDirItem == '.' || $subDirItem == '..') {
            unset($existingItemsByNames[$subDirItem]);
            continue;
        }

        $filePath = $dirPath . '/' . $subDirItem;
        if (!array_key_exists((string) $subDirItem, $filesByNamesFromFm)) {
            if (is_dir($filePath)) {
                removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        } else {
            //todo next новый механизм сравнения хэшей
            if (!filesize($filePath)) {
                unset($existingItemsByNames[$subDirItem]);
            }
        }
    }

    $filesForSync = array();
    foreach ($filesByNamesFromFm as $fileName => $identity) {
        if (!array_key_exists($fileName, $existingItemsByNames)) {
            $filesForSync[] = $identity;
        }
    }

    $resultBody = array('success' => true, 'forward_code' => 200, 'files' => $filesForSync);
} catch (Exception $ex) {
    $resultBody = array('success' => false, 'forward_code' => 500, 'message' => $ex->getMessage());
}

loadHeaders();
setResponseCode(200);
echo json_encode($resultBody);
