<?php

$selfDir = dirname(__FILE__);
require_once $selfDir . '/config.php';
require_once $selfDir . '/helper.php';

$token = filterAccessToken(isset($_POST['access_token']) ? (string) $_POST['access_token'] : '');
//через jquery нельзя послать пустой массив, если это не json.
// json посылать не будем, похоже при некоторых настройках старых серверов есть проблемы с чтением raw-body через php://input($HTTP_RAW_POST_DATA)
$isHasSubDirs = isset($_POST['isHasSubDirs']) && is_numeric($_POST['isHasSubDirs'])
    ? (int) $_POST['isHasSubDirs']
    : null;
$knownSubDirs = isset($_POST['knownSubDirs']) && is_array($_POST['knownSubDirs'])
    ? $_POST['knownSubDirs']
    : array();

$dlHandler = null;

try {
    if ($isHasSubDirs === null || ($isHasSubDirs && !$knownSubDirs) || (!$isHasSubDirs && $knownSubDirs)) {
        throw new RuntimeException('Не может быть обработано. Неверные параметры запроса.');
    }

    $headers = array('Accept: application/json', 'Authorization: Bearer ' . $token);
    $response = remoteRequest($filemanagerApiDomen . 'sync/getFileNamesFromRootDirectory', true, false, $headers);
    if ($response->curlHasError) {
        throw new RuntimeException('CURL ' . $response->curlErrorTxt);
    }

    if (
        $response->code != 200
        || !property_exists($response->responseBody, 'files')
        || !is_array($response->responseBody->files)) {
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

    $fsDirPath = $selfDir . '/../files';
    if (!file_exists($fsDirPath)) {
        if (!mkdir($fsDirPath, 0775)) {
            throw new RuntimeException('Не удалось создать папку "' . $fsDirPath . '" на вашем сервере');
        }
    }

    $dh = opendir($fsDirPath);
    if (!$dh) {
        $resultBody = array(
            'success' => false,
            'forward_code' => 500,
            'message' => 'Не удалось просканировать существующие файлы',
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

    $knownSubDirsByNames = array_flip($knownSubDirs);
    foreach ($existingItemsByNames as $dirItem => $_) {
        $fsItemPath = $fsDirPath . '/' . $dirItem;
        if (
            $dirItem == '.'
            || $dirItem == '..'
            || (array_key_exists($dirItem, $knownSubDirsByNames) && is_dir($fsItemPath))
        ) {
            unset($existingItemsByNames[$dirItem]);
            continue;
        }

        if (!array_key_exists((string) $dirItem, $filesByNamesFromFm)) {
            //удаляем только файлы, ненужные папки были удалены в start_sync_files
            if (is_file($fsItemPath)) {
                unlink($fsItemPath);
            }
        } else {
            //todo next новый механизм сравнения хэшей
            if (!filesize($fsItemPath)) {
                unset($existingItemsByNames[$dirItem]);
                continue;
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
